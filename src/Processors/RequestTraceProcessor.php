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
                $value = $request->getCustomData(RequestTraceMiddleware::KEY_TRACE_ID);
                if (!$value) {
                    $value = $request->header('X-Trace-Id');
                }
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
