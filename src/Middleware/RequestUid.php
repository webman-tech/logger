<?php

namespace WebmanTech\Logger\Middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 记录一次请求的唯一标识
 */
class RequestUid implements MiddlewareInterface
{
    public const REQUEST_UID_KEY = 'request_uid';

    public function process(Request $request, callable $handler): Response
    {
        $request->{static::REQUEST_UID_KEY} = $this->generateUid();

        return $handler($request);
    }

    /**
     * 生成唯一标识
     * @return string
     */
    public function generateUid(): string
    {
        $length = 7;
        /** @phpstan-ignore-next-line */
        return substr(bin2hex(random_bytes((int)ceil($length / 2))), 0, $length);
    }
}
