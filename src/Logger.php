<?php

namespace WebmanTech\Logger;

use InvalidArgumentException;
use support\Log;
use Throwable;
use WebmanTech\Logger\Helper\ConfigHelper;

class Logger
{
    /**
     * @var string
     */
    protected static $defaultLevel = 'info';

    /**
     * 合并到 config/log.php 中的配置
     * @return array
     */
    public static function getLogChannelConfigs(): array
    {
        $logChannelManager = new LogChannelManager((array)ConfigHelper::get('log-channel'));
        return $logChannelManager->buildLogChannelConfigs();
    }

    public static function __callStatic(string $name, array $arguments): void
    {
        $level = $arguments[1] ?? static::$defaultLevel;
        $context = $arguments[2] ?? [];
        try {
            $logChannel = Log::channel($name);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'Undefined index: ' . $name) {
                if (!in_array($name, (array)ConfigHelper::get('log-channel.channels', []))) {
                    // 未在 channels 中配置的
                    throw new InvalidArgumentException('请先在 log-channel.php 配置中配置 channels');
                }
                // 在 channels 中配置了，但所有 handler 都关闭的情况
                return;
            }
            throw $e;
        }
        $logChannel->log($level, static::formatMessage($arguments[0]), (array)$context);
    }

    /**
     * 格式化日志信息
     * @param string|array|mixed $message
     * @return string|\Stringable
     */
    protected static function formatMessage($message)
    {
        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        /** @phpstan-ignore-next-line */
        return $message;
    }
}
