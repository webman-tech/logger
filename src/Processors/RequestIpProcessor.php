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
            $request = Request::getCurrent();
            if ($request === null) {
                return '0.0.0.0';
            }
            // 同一请求内 ip 恒定，按请求对象缓存避免逐条日志重复解析
            return ProcessorCache::remember($request->getRaw(), 'ip', fn(): string => $request->getUserIp() ?? '0.0.0.0');
        });
    }
}
