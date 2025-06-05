<?php

namespace WebmanTech\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class CurrentUserProcessor implements ProcessorInterface
{
    public function __construct(protected \Closure|null $getUserId = null)
    {
    }

    /**
     * @inheritDoc
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        if (!isset($record->extra['ip'])) {
            $ip = '0.0.0.0';
            if ($request = request()) {
                $ip = $request->getRealIp();
            }
            $record->extra['ip'] = $ip;
        }
        if (!isset($record->extra['userId']) && $this->getUserId) {
            $userId = call_user_func($this->getUserId);
            $record->extra['userId'] = $userId;
        }
        return $record;
    }
}
