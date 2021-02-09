<?php

declare(strict_types=1);

namespace TBF2GM\Filter;

use ReflectionClass;
use ReflectionClassConstant;

/**
 * @see https://searchfox.org/comm-central/source/mailnews/search/public/nsMsgFilterCore.idl the nsMsgFilterType interface
 */
class Type
{
    public const NONE = 0x00;

    public const INBOX_RULE = 0x01;

    public const INBOX_JAVASCRIPT = 0x02;

    public const INBOX = self::INBOX_RULE | self::INBOX_JAVASCRIPT;

    public const NEWS_RULE = 0x04;

    public const NEWS_JAVASCRIPT = 0x08;

    public const NEWS = self::NEWS_RULE | self::NEWS_JAVASCRIPT;

    public const INCOMING = self::INBOX | self::NEWS;

    public const MANUAL = 0x10;

    /**
     * After bayes filtering.
     */
    public const POST_PLUGIN = 0x20;

    /**
     * After sending.
     */
    public const POST_OUTGOING = 0x40;

    /**
     * Before archiving.
     */
    public const ARCHIVE = 0x80;

    /**
     * On a repeating timer.
     */
    public const PERIODIC = 0x100;

    public const ALL = self::INCOMING | self::MANUAL;

    public static function getALl(): array
    {
        static $result = [];
        $className = get_called_class();
        if (!isset($result[$className])) {
            $list = [];
            $class = new ReflectionClass($className);
            foreach ($class->getReflectionConstants() as $constant) {
                /** @var ReflectionClassConstant $constant */
                if ($constant->isPublic()) {
                    $list[$constant->getName()] = $constant->getValue();
                }
            }
            $result[$className] = $list;
        }

        return $result[$className];
    }

    public static function getNames(int $type): ?array
    {
        $types = static::getALl();
        if ($type === 0) {
            foreach ($types as $name => $value) {
                if ($value === 0) {
                    return [$name];
                }
            }
            return [];
        }
        $types = array_filter($types);
        uasort(
            $types,
            static function (int $a, int $b) use ($types): int {
                $aBits = strlen(str_replace('0', '', decbin($a)));
                $bBits = strlen(str_replace('0', '', decbin($b)));
                $delta = $bBits - $aBits;
                if ($delta === 0) {
                    $delta = $a - $b;
                }
                return $delta;
            }
        );
        foreach ($types as $name => $value) {
            if (($type & $value) === $value) {
                $result[] = $name;
                $type = $type & ~$value;
                if ($type === 0) {
                    break;
                }
            }
        }

        return $type === 0 ? $result : null;
    }
}
