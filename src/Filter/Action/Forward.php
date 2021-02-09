<?php

declare(strict_types=1);

namespace TBF2GM\Filter\Action;

use RuntimeException;
use TBF2GM\Filter\Action;

class Forward implements Action
{
    private string $recipient;

    public function __construct(string $recipient)
    {
        $this->recipient = $recipient;
    }

    public function __toString(): string
    {
        return "Send a copy to {$this->getRecipient()}";
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public static function create(?string $value): Action
    {
        if ($value === null || $value === '') {
            throw new RuntimeException('Missing recipient specification');
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Invalid email recipient: {$value}");
        }

        return new static($value);
    }
}
