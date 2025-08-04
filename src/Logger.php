<?php

namespace WebmanTech\Logger;

use InvalidArgumentException;
use support\Log;
use Throwable;
use WeakMap;
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

    /**
     * 记录在用的 Logger 实例，后续用于释放资源
     * @var null|WeakMap<\Monolog\Logger, string>
     */
    private static ?WeakMap $loggerInstances = null;

    public static function __callStatic(string $name, array $arguments): void
    {
        $level = $arguments[1] ?? static::$defaultLevel;
        $context = $arguments[2] ?? [];

        if (self::$loggerInstances === null) {
            self::$loggerInstances = new WeakMap();
        }

        try {
            $channelLogger = Log::channel($name);

            if (!isset(self::$loggerInstances[$channelLogger])) {
                /** @phpstan-ignore-next-line */
                self::$loggerInstances[$channelLogger] = $name;
            }
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
        $channelLogger->log($level, static::formatMessage($arguments[0]), (array)$context);
    }

    /**
     * 重置，比如 flush message
     */
    public static function reset(?string $name = null): void
    {
        if (self::$loggerInstances === null) {
            return;
        }

        foreach (self::$loggerInstances as $logger => $channelName) {
            if ($name) {
                if ($name === $channelName) {
                    $logger->reset();
                    break;
                }
                continue;
            }
            // 不指定 name 时全部都释放一遍
            $logger->reset();
        }
    }

    /**
     * 关闭，比如 关闭文件句柄 占用
     */
    public static function close(?string $name = null): void
    {
        if (self::$loggerInstances === null) {
            return;
        }

        foreach (self::$loggerInstances as $logger => $channelName) {
            if ($name) {
                if ($name === $channelName) {
                    $logger->close();
                    break;
                }
                continue;
            }
            // 不指定 name 时全部都释放一遍
            $logger->close();
        }
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
