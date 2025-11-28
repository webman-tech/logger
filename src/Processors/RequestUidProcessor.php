<?php

namespace WebmanTech\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Monolog\ResettableInterface;
use WebmanTech\Logger\Middleware\RequestUid;

/**
 * @deprecated 建议使用如下 Processor 替换
 * @see RequestTraceProcessor
 *
 * 网站请求唯一标识记录，需要配合 Middleware\RequestUid
 * @see \WebmanTech\Logger\Middleware\RequestUid
 * @see \Monolog\Processor\UidProcessor
 */
class RequestUidProcessor implements ProcessorInterface, ResettableInterface
{
    private ?string $uid = null;

    public function __construct()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function __invoke(array|LogRecord $record): array|LogRecord
    {
        $extra = $record['extra'] ?? [];

        if (!isset($extra['uid'])) {
            $uid = null;
            if ($request = request()) {
                /* @phpstan-ignore-next-line */
                $uid = $request->{RequestUid::REQUEST_UID_KEY};
            }

            $extra['uid'] = $uid ?: $this->getUid();
        }
        // 保留一份 traceId，方便后续向 RequestTraceProcessor 迁移
        if (!isset($extra['traceId'])) {
            $extra['traceId'] = $extra['uid'];
        }

        $record['extra'] = $extra;

        return $record;
    }

    public function reset(): void
    {
        $this->uid = null;
    }

    private function getUid(): string
    {
        if ($this->uid === null) {
            $this->uid = $this->generateUid();
        }
        return $this->uid;
    }

    private function generateUid(): string
    {
        $length = 7;
        /** @phpstan-ignore-next-line */
        return substr(bin2hex(random_bytes((int)ceil($length / 2))), 0, $length);
    }
}
