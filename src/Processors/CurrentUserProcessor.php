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
    public function __invoke(array|LogRecord $record): array|LogRecord
    {
        $extra = $record['extra'] ?? [];

        if (!isset($extra['ip'])) {
            $ip = '0.0.0.0';
            if ($request = request()) {
                $ip = $request->getRealIp();
            }
            $extra['ip'] = $ip;
        }
        if (!isset($extra['userId']) && $this->getUserId) {
            $userId = call_user_func($this->getUserId);
            $extra['userId'] = $userId;
        }

        $record['extra'] = $extra;

        return $record;
    }
}
