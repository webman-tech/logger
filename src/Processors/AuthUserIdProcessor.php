<?php

namespace WebmanTech\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use WebmanTech\Auth\Auth;

final class AuthUserIdProcessor implements ProcessorInterface
{
    use ExtraGetSetTrait;

    public function __construct(private readonly ?string $guardName = null)
    {
    }

    public function __invoke(array|LogRecord $record): array|LogRecord
    {
        return $this->withRecordExtra($record, 'userId', function () {
            return Auth::guard($this->guardName)->getId() ?? '0';
        });
    }
}
