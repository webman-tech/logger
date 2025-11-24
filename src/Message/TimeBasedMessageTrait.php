<?php

namespace WebmanTech\Logger\Message;

/**
 * @internal
 * 需要时间控制的 Message Trait
 */
trait TimeBasedMessageTrait
{
    protected int $logMinTimeMS = 1000; // 小于该时间的不记录，单位毫秒
    protected int $warningTimeMS = 2000; // 超过该时间，记为 warning
    protected int $errorTimeMS = 10000; // 超过该时间，记为 error

    /**
     * 根据时间获取日志级别
     */
    protected function getLogLevelByTime(int $ts): ?string
    {
        return match (true) {
            $ts < $this->logMinTimeMS => null,
            $ts < $this->warningTimeMS => 'info',
            $ts < $this->errorTimeMS => 'warning',
            default => 'error',
        };
    }
}
