<?php

namespace WebmanTech\Logger\Message;

use WebmanTech\CommonUtils\Log;

abstract class BaseMessage
{
    protected bool $enable = true;
    protected string $channel = 'default';

    final public function __construct(array $config = [])
    {
        $config = array_filter($config, fn($value) => $value !== null);
        foreach ($config as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->{$key} = $value;
        }
    }


    /**
     * 切换启用和禁用的开关，用于临时关闭日志记录
     */
    public function switchEnable(bool $enable): void
    {
        $this->enable = $enable;
    }

    /**
     * 是否启用
     */
    public function isEnabled(): bool
    {
        return $this->enable;
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        Log::channel($this->channel)->log($level, $message, array_filter($context, fn($v) => $v !== null));
    }
}
