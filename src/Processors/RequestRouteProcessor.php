<?php

namespace WebmanTech\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RequestRouteProcessor implements ProcessorInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        if (!isset($record->extra['route'])) {
            $path = '/';
            if ($request = request()) {
                $path = $request->method() . ':' . $request->path();
            }
            $record->extra['route'] = $path;
        }
        return $record;
    }
}
