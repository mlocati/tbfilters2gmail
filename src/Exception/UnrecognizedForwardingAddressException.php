<?php

declare(strict_types=1);

namespace TBF2GM\Exception;

class UnrecognizedForwardingAddressException extends FilterNotCreableException
{
    private ?string $forwardingAddress = null;

    public function __toString(): string
    {
        $result = parent::__toString();
        $forwardingAddress = $this->getForwardingAddress();
        if ($forwardingAddress !== null) {
            $result .= "\nForwarding address: {$forwardingAddress}";
        }

        return $result;
    }

    /**
     * @return $this
     */
    public function setForwardingAddress(?string $value): self
    {
        $this->forwardingAddress = $value;

        return $this;
    }

    public function getForwardingAddress(): ?string
    {
        return $this->forwardingAddress;
    }
}
