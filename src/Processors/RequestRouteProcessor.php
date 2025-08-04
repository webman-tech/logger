<?php

namespace WebmanTech\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RequestRouteProcessor implements ProcessorInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(array|LogRecord $record): array|LogRecord
    {
        $extra = $record['extra'] ?? [];

        if (!isset($extra['route'])) {
            $path = '/';
            if ($request = request()) {
                $path = $request->method() . ':' . $request->path();
            }
            $extra['route'] = $path;
        }

        $record['extra'] = $extra;

        return $record;
    }
}
