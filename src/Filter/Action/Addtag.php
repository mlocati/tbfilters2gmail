<?php

declare(strict_types=1);

namespace TBF2GM\Filter\Action;

use RuntimeException;
use TBF2GM\Filter\Action;
use TBF2GM\Filter\TagAction;

class Addtag implements Action, TagAction
{
    private string $tag;

    public function __construct(string $tag)
    {
        $this->tag = $tag;
    }

    public function __toString(): string
    {
        return "Add tag \"{$this->getTag()}\"";
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public static function create(?string $value): Action
    {
        if ($value === null || $value === '') {
            throw new RuntimeException('Missing tag');
        }

        return new static($value);
    }
}
