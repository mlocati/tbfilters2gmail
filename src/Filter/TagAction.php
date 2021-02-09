<?php

declare(strict_types=1);

namespace TBF2GM\Filter;

interface TagAction extends Action
{
    public function getTag(): string;
}
