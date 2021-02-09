<?php

declare(strict_types=1);

namespace TBF2GM\Exception;

use TBF2GM\Exception;
use TBF2GM\Filter;

class FilterNotCreableException extends Exception
{
    private ?Filter $filter = null;

    public function __toString(): string
    {
        return $this->getMessage() . "\n" . (string) $this->getFilter();
    }

    /**
     * @return $this
     */
    public function setFilter(?Filter $value): self
    {
        $this->filter = $value;

        return $this;
    }

    public function getFilter(): ?Filter
    {
        return $this->filter;
    }
}
