<?php

namespace WebmanTech\Logger\Processors;

use Monolog\LogRecord;

/**
 * @internal
 */
trait ExtraGetSetTrait
{
    private function withRecordExtra(array|LogRecord $record, string $key, \Closure $fn): array|LogRecord
    {
        $extra = $record['extra'] ?? [];

        if (!isset($extra[$key])) {
            $extra[$key] = $fn();
        }

        $record['extra'] = $extra;

        return $record;
    }
}
