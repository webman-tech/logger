<?php

namespace WebmanTech\Logger\Helper;

/**
 * @internal
 */
final class StringHelper
{
    public static function limit(string $value, int $limit): string
    {
        return (strlen($value) > $limit) ? (substr($value, 0, $limit) . '...') : $value;
    }
}
