<?php

namespace WebmanTech\Logger\Mode;

use Monolog\Handler\RotatingFileHandler;

class MixMode extends BaseMode
{
    protected array $config = [
        'max_files' => 30,
        'name' => 'channelMixed', // 合并时的日志文件名
    ];

    /**
     * @inheritDoc
     */
    public function getHandler(string $channelName, string $level): array
    {
        if (!$this->checkHandlerUsefulForChannel($channelName)) {
            return [];
        }

        $name = $this->config['name'];
        return [
            'class' => RotatingFileHandler::class,
            'constructor' => [
                'filename' => runtime_path() . "/logs/{$name}/{$name}.log",
                'maxFiles' => $this->config['max_files'],
                'level' => $level,
            ],
            'formatter' => $this->getFormatter(),
        ];
    }
}