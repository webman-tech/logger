<?php

namespace WebmanTech\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\UidProcessor;
use WebmanTech\Logger\Middleware\RequestUid;

/**
 * 网站请求唯一标识记录，需要配合 Middleware\RequestUid
 * @see \WebmanTech\Logger\Middleware\RequestUid
 */
class RequestUidProcessor extends UidProcessor
{
    public function __construct()
    {
        parent::__construct(7);
    }

    /**
     * {@inheritDoc}
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        if (!isset($record->extra['uid'])) {
            $uid = null;
            if ($request = request()) {
                $uid = $request->{RequestUid::REQUEST_UID_KEY};
            }

            $record->extra['uid'] = $uid ?: $this->getUid();
        }

        return $record;
    }
}
