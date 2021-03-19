<?php

declare(strict_types=1);

namespace TBF2GM\Filter\Action;

use RuntimeException;
use TBF2GM\Filter\Action;

class Reply implements Action
{
    private string $modelPath;

    public function __construct(string $modelPath)
    {
        $this->modelPath = $modelPath;
    }

    public function __toString(): string
    {
        return "Reply using \"{$this->getModelPath()}\"";
    }

    public function getModelPath(): string
    {
        return $this->modelPath;
    }

    public static function create(?string $value): Action
    {
        if ($value === null || $value === '') {
            throw new RuntimeException('Missing reply model');
        }
        return new static($value);
    }
}
