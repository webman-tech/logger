<?php

namespace Kriss\WebmanLogger\Mode;

use Monolog\Handler\StreamHandler;

class StdoutMode extends BaseMode
{
    /**
     * @inheritDoc
     */
    public function getHandler(string $channelName, string $level): array
    {
        if (!$this->checkHandlerUsefulForChannel($channelName)) {
            return [];
        }

        return [
            'class' => StreamHandler::class,
            'constructor' => [
                'stream' => "php://stdout",
                'level' => $level,
            ],
            'formatter' => $this->getFormatter(),
        ];
    }
}