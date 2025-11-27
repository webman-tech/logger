<?php

namespace WebmanTech\Logger\Mode;

use Monolog\Handler\RotatingFileHandler;
use function WebmanTech\CommonUtils\runtime_path;

class SplitMode extends BaseMode
{
    /**
     * @var array
     */
    protected $config = [
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
                'filename' => runtime_path("logs/{$channelName}/{$channelName}.log"),
                'maxFiles' => $this->config['max_files'],
                'level' => $level,
            ],
            'formatter' => $this->getFormatter(),
        ];
    }
}
