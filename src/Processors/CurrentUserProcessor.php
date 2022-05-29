<?php

namespace Kriss\WebmanLogger\Processors;

use Monolog\Processor\ProcessorInterface;

class CurrentUserProcessor implements ProcessorInterface
{
    protected $getUserId = null;

    public function __construct(callable $getUserId = null)
    {
        $this->getUserId = $getUserId;
    }

    /**
     * @inheritDoc
     */
    public function __invoke(array $record): array
    {
        if (!isset($record['context']['ip'])) {
            $ip = '0.0.0.0';
            if ($request = request()) {
                $ip = $request->getRealIp();
            }
            $record['context']['ip'] = $ip;
        }
        if (!isset($record['context']['userId']) && $this->getUserId) {
            $userId = call_user_func($this->getUserId);
            $record['context']['userId'] = $userId;
        }
        return $record;
    }
}
