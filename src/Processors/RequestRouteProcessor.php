<?php

namespace Kriss\WebmanLogger\Processors;

use Monolog\Processor\ProcessorInterface;

class RequestRouteProcessor implements ProcessorInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(array $record): array
    {
        if (!isset($record['context']['route'])) {
            $path = '/';
            if ($request = request()) {
                $path = $request->method() . ':' . $request->path();
            }
            $record['context']['route'] = $path;
        }
        return $record;
    }
}
