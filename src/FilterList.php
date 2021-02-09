<?php

declare(strict_types=1);

namespace TBF2GM;

use ArrayObject;

class FilterList extends ArrayObject
{
    private string $version;

    private ?bool $logging;

    public function __construct(string $version, ?bool $logging = null)
    {
        $this->version = $version;
        $this->logging = $logging;
    }

    public function __toString(): string
    {
        if ($this->count() === 0) {
            return '(empty)';
        }
        $chunks = [];
        foreach ($this->getIterator() as $filter) {
            $chunks[] = (string) $filter;
        }

        return implode("\n", $chunks);
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function isLogging(): ?bool
    {
        return $this->logging;
    }

    /**
     * @return $this
     */
    public function setLogging(bool $value): self
    {
        $this->logging = $value;

        return $this;
    }
}
