<?php

namespace WebmanTech\Logger\Middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use WebmanTech\Logger\Logger;

/**
 * 重置日志组件的状态
 */
final class ResetLog implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        try {
            return $handler($request);
        } finally {
            Logger::reset();
            Logger::close();
        }
    }
}
