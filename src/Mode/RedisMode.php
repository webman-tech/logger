<?php

namespace WebmanTech\Logger\Mode;

use InvalidArgumentException;
use Monolog\Handler\RedisHandler;

class RedisMode extends BaseMode
{
    /**
     * @var array
     */
    protected $config = [
        'redis' => null,
        'redis_key_prefix' => 'webmanLog:',
        'redis_size' => 0,
    ];

    /**
     * @inheritDoc
     */
    public function getHandler(string $channelName, string $level): array
    {
        if (!$this->checkHandlerUsefulForChannel($channelName)) {
            return [];
        }
        $redis = $this->config['redis'];
        if (is_callable($redis)) {
            $redis = call_user_func($redis);
        }
        if (!$redis) {
            throw new InvalidArgumentException('redis 配置错误');
        }

        return [
            'class' => RedisHandler::class,
            'constructor' => [
                'redis' => $redis,
                'key' => $this->config['redis_key_prefix'] . $channelName,
                'level' => $level,
                'bubble' => true,
                'capSize' => $this->config['redis_size'],
            ],
            'formatter' => $this->getFormatter(),
        ];
    }
}