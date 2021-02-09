<?php

declare(strict_types=1);

namespace TBF2GM;

use Google_Service_Gmail_FilterAction;
use RuntimeException;
use TBF2GM\Filter\Action;
use TBF2GM\Filter\Type;
use TBF2GM\Gmail\LabelManager;

class Filter
{
    private string $name;

    private bool $enabled;

    private int $type;

    private Filter\ConditionList $conditions;

    /**
     * @var \TBF2GM\Filter\Action[]
     */
    private array $actions = [];

    public function __construct(string $name, bool $enabled = false, int $type = Filter\Type::NONE)
    {
        $this->name = $name;
        $this->enabled = $enabled;
        $this->type = $type;
        $this->conditions = new Filter\ConditionList();
    }

    public function __toString(): string
    {
        $result = $this->getName();
        $result .= "\n - Enabled: " . ($this->isEnabled() ? 'true' : 'false');
        $result .= "\n - Type: " . implode(', ', $this->getTypeNames());
        $result .= "\n - Conditions:";
        if ($this->getConditions()->isEmpty()) {
            $result .= ' (none)';
        } else {
            foreach ($this->getConditions()->getConditions() as $condition) {
                $result .= "\n  - " . (string) $condition;
            }
        }
        $result .= "\n - Actions:";
        $actions = $this->getActions();
        if ($actions === []) {
            $result .= ' (none)';
        } else {
            foreach ($actions as $action) {
                $result .= "\n  - " . (string) $action;
            }
        }

        return $result;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return $this
     */
    public function setName(string $value): self
    {
        $this->name = $value;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return $this
     */
    public function setEnabled(bool $value): self
    {
        $this->enabled = $value;

        return $this;
    }

    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getTypeNames(): array
    {
        return Type::getNames($this->getType());
    }

    /**
     * @return $this
     */
    public function setType(int $value): self
    {
        $this->type = $value;

        return $this;
    }

    public function getConditions(): Filter\ConditionList
    {
        return $this->conditions;
    }

    /**
     * @return $this
     */
    public function addConditions(Filter\Condition $value): self
    {
        $this->conditions->add($value);

        return $this;
    }

    /**
     * @param \TBF2GM\Filter\Condition[] $value
     *
     * @return $this
     */
    public function addConditionsList(array $value): self
    {
        foreach ($value as $v) {
            $this->addConditions($v);
        }

        return $this;
    }

    /**
     * @return \TBF2GM\Filter\Action[]
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * @return $this
     */
    public function addAction(Filter\Action $value): self
    {
        $this->actions[] = $value;

        return $this;
    }

    public function toGmailAction(LabelManager $labelManager): Google_Service_Gmail_FilterAction
    {
        $result = new Google_Service_Gmail_FilterAction();
        $addLabelIDs = [];
        $removeLabelIDs = [];
        $canAddTags = true;
        foreach ($this->getActions() as $action) {
            switch (get_class($action)) {
                case Action\Addtag::class:
                    // Second pass later
                    break;
                case Action\CopyToFolder::class:
                    $addLabelIDs[] = $labelManager->getOrCreateLabelFromFolder($action->getFolder())->getId();
                    $canAddTags = false;
                    break;
                case Action\Delete::class:
                    $addLabelIDs[] = 'TRASH';
                    break;
                case Action\Forward::class:
                    $forward = (string) $result->getForward();
                    if ($forward !== '') {
                        throw new RuntimeException('Only one "forward to" action is supported by Gmail filters');
                    }
                    $result->setForward($action->getRecipient());
                    break;
                case Action\Junkscore::class:
                    if ($action->getScore() === 0) {
                        $removeLabelIDs[] = 'SPAM';
                    } elseif ($action->getScore() === 100) {
                        $addLabelIDs[] = 'SPAM';
                    } else {
                        throw new RuntimeException('Unsupported JunkScore score');
                    }
                    break;
                case Action\MarkRead::class:
                    $removeLabelIDs[] = 'UNREAD';
                    break;
                case Action\MoveToFolder::class:
                    $removeLabelIDs[] = 'INBOX';
                    $addLabelIDs[] = $labelManager->getOrCreateLabelFromFolder($action->getFolder())->getId();
                    $canAddTags = false;
                    break;
                case Action\StopExecution::class:
                    break;
                default:
                    throw new RuntimeException('Action not implemented: ' . get_class($action));
            }
        }
        foreach ($this->getActions() as $action) {
            switch (get_class($action)) {
                case Action\Addtag::class:
                    if ($action->getTag() === DefaultTags::IMPORTANT) {
                        $addLabelIDs[] = 'IMPORTANT';
                    } elseif ($canAddTags) {
                        $addLabelIDs[] = $labelManager->getOrCreateLabelFromPath($action->getTag())->getId();
                    }
                    break;
            }
        }
        $result->setAddLabelIds(array_values(array_unique($addLabelIDs)));
        $result->setRemoveLabelIds(array_values(array_unique($removeLabelIDs)));

        return $result;
    }
}
