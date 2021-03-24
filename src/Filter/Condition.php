<?php

declare(strict_types=1);

namespace TBF2GM\Filter;

use DateTimeImmutable;
use ReflectionClass;
use ReflectionClassConstant;
use RuntimeException;
use TBF2GM\Exception;
use TBF2GM\Exception\FilterNotCreableException;

class Condition
{
    public const TYPE_AND = 'and';

    public const TYPE_OR = 'or';

    public const WHERE_FROM = 'from';

    public const WHERE_TO = 'to';

    public const WHERE_CC = 'cc';

    public const WHERE_TO_CC = 'to or cc';

    public const WHERE_TO_FROM_CC_BCC = 'all addresses';

    public const WHERE_SUBJECT = 'subject';

    public const WHERE_BODY = 'body';

    public const WHERE_DATE = 'date';

    public const WHERE_JUNKSTATUS = 'junk status';

    public const HOW_CONTAINS = 'contains';

    public const HOW_DOESNOTCONTAIN = "doesn't contain";

    public const HOW_BEGINSWITH = 'begins with';

    public const HOW_ENDSWITH = 'ends with';

    public const HOW_IS = 'is';

    public const HOW_ISNOT = "isn't";

    public const HOW_ISBEFORE = 'is before';

    private string $type;

    private string $where;

    private string $how;

    private string $search;

    public function __construct(string $type, string $where, string $how, string $search)
    {
        $this->type = $type;
        $this->where = $where;
        $this->how = $how;
        $this->search = $search;
    }

    public function __toString(): string
    {
        return "[{$this->getType()}] {$this->getWhere()} {$this->getHow()} \"{$this->getSearch()}\"";
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getWhere(): string
    {
        return $this->where;
    }

    public function getHow(): string
    {
        return $this->how;
    }

    public function getSearch(): string
    {
        return $this->search;
    }

    /**
     * @throws RuntimeException
     *
     * @return static
     *
     * @example AND (to,contains,someone@somewhere.com)
     */
    public static function parse(string $value): array
    {
        $result = [];
        $process = $value;
        for (;;) {
            $process = trim($process);
            if ($process === '') {
                break;
            }
            $type = static::extractType($process);
            if ($type === null) {
                throw new RuntimeException("Invalid condition string: {$value}");
            }
            $subject = static::extractWhere($process);
            if ($subject === null) {
                throw new RuntimeException("Invalid condition string: {$value}");
            }
            $how = static::extractHow($process);
            if ($how === null) {
                throw new RuntimeException("Invalid condition string: {$value}");
            }
            $search = static::extractSearch($process);
            if ($search === null) {
                throw new RuntimeException("Invalid condition string: {$value}");
            }
            $result[] = new static($type, $subject, $how, $search);
        }
        if ($result === []) {
            throw new RuntimeException("Invalid condition string: {$value}");
        }
        return $result;
    }

    /**
     * @throws \TBF2GM\Exception\FilterNotCreableException
     */
    public function asGmailFilter(): string
    {
        switch ($this->getWhere()) {
            case static::WHERE_FROM:
                return $this->buildGmailFilter('from');
            case static::WHERE_TO:
                return $this->buildGmailFilter('to');
            case static::WHERE_CC:
                return $this->buildGmailFilter('cc');
            case static::WHERE_TO_CC:
                return '(' . implode(' ', [
                    $this->buildGmailFilter('to'),
                    'OR',
                    $this->buildGmailFilter('cc'),
                ]) . ')';
            case static::WHERE_TO_FROM_CC_BCC:
                return '(' . implode(' ', [
                    $this->buildGmailFilter('from'),
                    'OR',
                    $this->buildGmailFilter('to'),
                    'OR',
                    $this->buildGmailFilter('cc'),
                    'OR',
                    $this->buildGmailFilter('bcc'),
                ]) . ')';
            case static::WHERE_SUBJECT:
                return $this->buildGmailFilter('subject');
            case static::WHERE_BODY:
                return $this->buildGmailFilter('');
            case static::WHERE_DATE:
                return $this->buildGmailDateFilter();
            default:
                throw new Exception\FilterNotCreableException("Not implemented: {$this->getWhere()}");
        }
    }

    protected static function extractType(string &$value): ?string
    {
        $m = null;
        if (!preg_match('/^(' . static::getQuotedConstantsForRegex('TYPE') . ')\s*\(\s*(.+)$/mis', $value, $m)) {
            return null;
        }
        $value = $m[2];

        return strtolower($m[1]);
    }

    protected static function extractWhere(string &$value): ?string
    {
        $rx = implode('', [
            '/^',
            '(',
            '(?<whereConstant>' . static::getQuotedConstantsForRegex('WHERE') . ')',
            '|',
            '(?<whereHeader>"[^"]*")',
            ')',
            '\s*,\s*',
            '(?<continue>.+)',
            '$/mis',
        ]);
        $m = null;
        if (!preg_match($rx, $value, $m, PREG_UNMATCHED_AS_NULL)) {
            return null;
        }
        $value = $m['continue'];

        return isset($m['whereConstant']) ? strtolower($m['whereConstant']) : $m['whereHeader'];
    }

    protected static function extractHow(string &$value): ?string
    {
        $m = null;
        if (!preg_match('/^(' . static::getQuotedConstantsForRegex('HOW') . ')\s*\,(.+)$/mis', $value, $m)) {
            return null;
        }
        $value = $m[2];

        return strtolower($m[1]);
    }

    protected static function extractSearch(string &$value): ?string
    {
        $v = ltrim($value);
        $m = null;
        if (($v[0] ?? '') === '"') {
            $rx = '/^\s*"(?<v>[^"]*)"\s*\)\s*(?<post>.*)$/mis';
        } else {
            $rx = '/^(?<v>[^\)]*)\s*\)\s*(?<post>.*)$/mis';
        }
        if (!preg_match($rx, $value, $m)) {
            return null;
        }
        $value = $m['post'];

        return $m['v'];
    }

    protected static function getQuotedConstantsForRegex(string $prefix, string $quoter = '/'): string
    {
        $constantValues = [];
        $class = new ReflectionClass(get_called_class());
        foreach ($class->getReflectionConstants() as $constant) {
            /** @var ReflectionClassConstant $constant */
            if (strpos($constant->getName(), "{$prefix}_") === 0) {
                $constantValues[] = $constant->getValue();
            }
        }
        $constantValues = array_map(
            static function (string $value) use ($quoter): string {
                return preg_quote($value, $quoter);
            },
            $constantValues
        );

        return implode('|', $constantValues);
    }

    protected function buildGmailFilter(string $key): string
    {
        $how = $this->getHow();
        switch ($how) {
            case static::HOW_DOESNOTCONTAIN:
                return '-{' . $this->buildGmailFilterHow($key, static::HOW_CONTAINS) . '}';
            default:
                return $this->buildGmailFilterHow($key, $how);
        }
    }

    protected function buildGmailFilterHow(string $key, string $how): string
    {
        switch ($how) {
            case static::HOW_BEGINSWITH:
            case static::HOW_CONTAINS:
            case static::HOW_ENDSWITH:
            case static::HOW_IS:
                return ($key === '' ? '' : "{$key}:") . '"' . str_replace('"', '', $this->getSearch()) . '"';
            default:
                throw new RuntimeException("Not implemented: {$how}");
        }
    }

    protected function buildGmailDateFilter(): string
    {
        $dateString = $this->getSearch();
        $dateTime = $dateString ? DateTimeImmutable::createFromFormat('d-M-Y', $dateString) : false;
        if (!$dateTime) {
            throw new FilterNotCreableException("'{$dateString}' is not a valid date");
        }
        switch ($this->getHow()) {
            case static::HOW_ISBEFORE:
                return 'before:' . $dateTime->format('Y/m/d');
            default:
                throw new RuntimeException('Not implemented');
        }
    }
}
