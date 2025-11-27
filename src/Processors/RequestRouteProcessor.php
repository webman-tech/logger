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
            return sprintf('%s:%s', $request->getMethod(), $request->getPath());
        });
    }
}
