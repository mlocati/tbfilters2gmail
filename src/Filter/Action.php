<?php

declare(strict_types=1);

namespace TBF2GM\Filter;

interface Action
{
    public function __toString(): string;

    public static function create(?string $value): Action;
}
