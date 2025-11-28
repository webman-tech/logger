<?php

namespace WebmanTech\Logger\Middleware;

use Closure;
use Webman\Http\Response as WebmanResponse;
use WebmanTech\CommonUtils\Middleware\BaseMiddleware;
use WebmanTech\CommonUtils\Request;
use WebmanTech\CommonUtils\Response;
use WebmanTech\Logger\Message\HttpRequestMessage;
use function WebmanTech\CommonUtils\get_env;

final class HttpRequestLogMiddleware extends BaseMiddleware
{
    private static ?HttpRequestMessage $message = null;

    /**
     * @inheritDoc
     */
    protected function processRequest(Request $request, Closure $handler): Response
    {
        if (self::$message === null) {
            self::$message = new HttpRequestMessage(get_env('HTTP_REQUEST_LOG_CONFIG', []));
        }
        $message = self::$message;
        $response = Response::make();
        try {
            $message->markRequestStart($request);
            $response = $handler($request);

            $rawResponse = $response->getRaw();
            if ($rawResponse instanceof WebmanResponse && $rawResponse->exception()) {
                throw $rawResponse->exception();
            }
            $message->markResponseEnd($response);
            return $response;
        } catch (\Throwable $e) {
            $message->markResponseEnd($response, $e);
            return $response;
        }
    }
}
