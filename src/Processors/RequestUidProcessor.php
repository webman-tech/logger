<?php

namespace WebmanTech\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Monolog\ResettableInterface;
use WebmanTech\Logger\Middleware\RequestUid;

/**
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
