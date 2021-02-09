<?php

declare(strict_types=1);

namespace TBF2GM\Filter\Action;

use RuntimeException;
use TBF2GM\Filter\Action;

class Junkscore implements Action
{
    private int $score;

    public function __construct(int $score)
    {
        $this->score = $score;
    }

    public function __toString(): string
    {
        return "Set junk score to {$this->getScore()}";
    }

    public function getScore(): int
    {
        return $this->score;
    }

    /**
     * @return $this
     */
    public function setScore(int $value): self
    {
        $this->score = $value;

        return $this;
    }

    public static function create(?string $value): Action
    {
        if ($value === null || $value === '') {
            throw new RuntimeException('Missing score in ' . __CLASS__ . ' action');
        }
        if (!preg_match('/^\d+/', $value)) {
            throw new RuntimeException("Invalid score '{$value}' for " . __CLASS__ . ' action');
        }
        return new static((int) $value);
    }
}
