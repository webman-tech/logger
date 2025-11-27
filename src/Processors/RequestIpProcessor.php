<?php

namespace WebmanTech\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use WebmanTech\CommonUtils\Request;

/**
 * 请求 IP 处理器
 */
final class RequestIpProcessor implements ProcessorInterface
{
    use ExtraGetSetTrait;

    public function __invoke(array|LogRecord $record): array|LogRecord
    {
        return $this->withRecordExtra($record, 'ip', function () {
            return Request::getCurrent()?->getUserIp() ?? '0.0.0.0';
        });
    }
}
