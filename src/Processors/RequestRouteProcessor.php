<?php

namespace WebmanTech\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use WebmanTech\CommonUtils\Request;

/**
 * 请求 路由 处理器
 */
final class RequestRouteProcessor implements ProcessorInterface
{
    use ExtraGetSetTrait;

    public function __invoke(array|LogRecord $record): array|LogRecord
    {
        return $this->withRecordExtra($record, 'route', function () {
            $request = Request::getCurrent();
            if ($request === null) {
                return '';
            }
            // 同一请求内 method/path 恒定，按请求对象缓存避免逐条日志重复计算
            return ProcessorCache::remember($request->getRaw(), 'route', fn(): string => sprintf('%s:%s', $request->getMethod(), $request->getPath()));
        });
    }
}
