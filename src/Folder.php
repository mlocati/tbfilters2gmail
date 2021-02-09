<?php

declare(strict_types=1);

namespace TBF2GM;

use RuntimeException;

class Folder
{
    private string $host;

    private ?int $port;

    private string $user;

    private string $path;

    private array $names;

    public function __construct(string $host, ?int $port, string $user, string $path)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->path = $path;
        $this->rebuildNames();
    }

    public function __toString(): string
    {
        return implode(' / ', $this->getNames());
    }

    /**
     * @return string[]
     */
    public function getNames(): array
    {
        return $this->names;
    }

    /**
     * @example mailbox://user@host/folder
     *
     * @return static
     */
    public static function parse(?string $value): self
    {
        if ($value === null || $value === '') {
            throw new RuntimeException('Missing folder specification');
        }
        $parts = parse_url($value);
        if ($parts === false) {
            throw new RuntimeException("Invalid folder specification: {$value}");
        }
        if (($parts['scheme'] ?? '') !== 'mailbox') {
            throw new RuntimeException('Unsupported folder scheme: ' . ($parts['scheme'] ?? ''));
        }
        $host = $parts['host'] ?? '';
        $port = empty($parts['port']) ? null : (int) $parts['port'];
        $user = $parts['user'] ?? '';
        if (($parts['password'] ?? '') !== '') {
            throw new RuntimeException("Folder path with password: {$value}");
        }
        $path = $parts['path'] ?? '';
        if (!preg_match('_^/_', $path)) {
            throw new RuntimeException('Unsupported folder path: ' . ($parts['path'] ?? ''));
        }
        if (($parts['query'] ?? '') !== '') {
            throw new RuntimeException("Folder path with querystring: {$value}");
        }
        if (($parts['fragment'] ?? '') !== '') {
            throw new RuntimeException('Folder path with fragment: {fragment}');
        }

        return new static($host, $port, $user, $path);
    }

    protected function rebuildNames()
    {
        $chunks = explode('/', trim($this->path, '/'));
        $this->names = array_map('urldecode', $chunks);
    }
}
