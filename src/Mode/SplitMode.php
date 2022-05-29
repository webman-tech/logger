<?php

namespace Kriss\WebmanLogger\Mode;

use Monolog\Handler\RotatingFileHandler;

class SplitMode extends BaseMode
{
    protected array $config = [
        'max_files' => 30,
    ];

    /**
     * @inheritDoc
     */
    public function getHandler(string $channelName, string $level): array
    {
        if (!$this->checkHandlerUsefulForChannel($channelName)) {
            return [];
        }

        return [
            'class' => RotatingFileHandler::class,
            'constructor' => [
                'filename' => runtime_path() . "/logs/{$channelName}/{$channelName}.log",
                'maxFiles' => $this->config['max_files'],
                'level' => $level,
            ],
            'formatter' => $this->getFormatter(),
        ];
    }
}