<?php

declare(strict_types=1);

namespace TBF2GM\Filter\Action;

use RuntimeException;
use TBF2GM\Filter\Action;

class MarkFlagged implements Action
{
    public function __construct()
    {
    }

    public function __toString(): string
    {
        return 'Mark flagged';
    }

    public static function create(?string $value): Action
    {
        if ($value !== null) {
            throw new RuntimeException('Unexpected argument for action ' . __CLASS__);
        }
        return new static();
    }
}
