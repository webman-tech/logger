<?php

namespace Kriss\WebmanLogger;

use ErrorException;
use InvalidArgumentException;
use support\Log;
use Throwable;

class Logger
{
    protected static string $defaultLevel = 'info';

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
                throw new InvalidArgumentException('请先在 config/plugin/kriss/webman-logger/log-channel.php 配置中配置 channels');
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