<?php

namespace WebmanTech\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use WebmanTech\CommonUtils\Request;
use WebmanTech\Logger\Middleware\RequestTraceMiddleware;

/**
 * 请求 trace 处理器
 */
final class RequestTraceProcessor implements ProcessorInterface
{
    use ExtraGetSetTrait;

    public function __invoke(array|LogRecord $record): array|LogRecord
    {
        return $this->withRecordExtra($record, 'traceId', function () {
            $value = '';
            if ($request = Request::getCurrent()) {
                // 同一请求内 customData/header 恒定，按请求对象缓存避免逐条日志重复读取
                $value = ProcessorCache::remember($request->getRaw(), 'traceId', function () use ($request) {
                    $traceId = $request->getCustomData(RequestTraceMiddleware::KEY_TRACE_ID);
                    if (!$traceId) {
                        $traceId = $request->header('X-Trace-Id');
                    }
                    return $traceId ?? '';
                });
            }
            if (!$value) {
                $value = $this->getUid();
            }
            return (string)$value;
        });
    }

    private ?string $uid = null;

    public function reset(): void
    {
        $this->uid = null;
    }

    private function getUid(): string
    {
        if ($this->uid === null) {
            $this->uid = uniqid('uid');
        }
        return $this->uid;
    }
}
