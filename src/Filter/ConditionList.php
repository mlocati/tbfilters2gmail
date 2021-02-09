<?php

declare(strict_types=1);

namespace TBF2GM\Filter;

use Google_Service_Gmail_FilterCriteria;
use RuntimeException;

class ConditionList
{
    /**
     * @var \TBF2GM\Filter\Condition[]
     */
    private array $conditions = [];

    /**
     * @return $this
     */
    public function add(Condition $value): self
    {
        if ($this->conditions !== [] && $this->conditions[0]->getType() !== $value->getType()) {
            throw new RuntimeException('Mixed AND/OR conditions is not supported');
        }
        $this->conditions[] = $value;

        return $this;
    }

    /**
     * @return \TBF2GM\Filter\Condition[]
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function isEmpty(): bool
    {
        return $this->getConditions() === [];
    }

    /**
     * @throws \TBF2GM\Exception\FilterNotCreableException
     */
    public function toGmailCriteria(): Google_Service_Gmail_FilterCriteria
    {
        $result = new Google_Service_Gmail_FilterCriteria();
        $chunks = [];
        foreach ($this->getConditions() as $condition) {
            if ($chunks !== [] && $condition->getType() === $condition::TYPE_OR) {
                $chunks[] = 'OR';
            }
            $chunks[] = $condition->asGmailFilter();
        }
        $result->setQuery(implode(' ', $chunks));

        return $result;
    }
}
