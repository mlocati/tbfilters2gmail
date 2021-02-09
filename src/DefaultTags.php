<?php

declare(strict_types=1);

namespace TBF2GM;

class DefaultTags
{
    public const IMPORTANT = '$label1';

    public const WORK = '$label2';

    public const PERSONAL = '$label3';

    public const TODO = '$label4';

    public const LATER = '$label5';

    public static function getEnglishNames(): array
    {
        return [
            static::IMPORTANT => 'Important',
            static::WORK => 'Work',
            static::PERSONAL => 'Personal',
            static::TODO => 'Todo',
            static::LATER => 'Later',
        ];
    }
}
