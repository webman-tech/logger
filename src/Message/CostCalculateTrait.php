<?php

namespace WebmanTech\Logger\Message;

use DateTimeImmutable;
use Symfony\Component\Clock\ClockAwareTrait;

trait CostCalculateTrait
{
    use ClockAwareTrait;

    private ?DateTimeImmutable $start = null;

    /**
     * 标记开始时间
     */
    private function markStartTime(bool $clear = false): void
    {
        if ($clear) {
            $this->start = null;
            return;
        }
        $this->start = $this->now();
    }

    /**
     * 获取耗时，单位毫秒
     */
    private function getCostTimeMs(): int
    {
        if ($this->start === null) {
            return 0;
        }

        $costDiff = $this->now()->diff($this->start);
        return intval($costDiff->s * 1000 + $costDiff->f * 1000);
    }
}
