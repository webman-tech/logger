<?php

namespace WebmanTech\Logger\Middleware;

use Closure;
use WebmanTech\CommonUtils\Middleware\BaseMiddleware;
use WebmanTech\CommonUtils\Request;
use WebmanTech\CommonUtils\Response;

final class RequestTraceMiddleware extends BaseMiddleware
{
    public const KEY_TRACE_ID = 'trace_id';

    /**
     * @inheritDoc
     */
    protected function processRequest(Request $request, Closure $handler): Response
    {
        $request->withCustomData([
            self::KEY_TRACE_ID => uniqid('trace'),
        ]);

        return $handler($request);
    }
}
