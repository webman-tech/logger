<?php

namespace WebmanTech\Logger;

use InvalidArgumentException;
use support\Log;
use Throwable;

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
        $logChannelManager = new LogChannelManager(config('plugin.kriss.webman-logger.log-channel'));
        return $logChannelManager->buildLogChannelConfigs();
    }

    public static function __callStatic($name, $arguments)
    {
        $level = $arguments[1] ?? static::$defaultLevel;
        $context = $arguments[2] ?? [];
        try {
            $logChannel = Log::channel($name);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'Undefined index: ' . $name) {
                if (!in_array($name, config('plugin.kriss.webman-logger.log-channel.channels', []))) {
                    // 未在 channels 中配置的
                    throw new InvalidArgumentException('请先在 config/plugin/webman-tech/logger/log-channel.php 配置中配置 channels');
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
     * @param $message
     * @return string|\Stringable
     */
    protected static function formatMessage($message)
    {
        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $message;
    }
}