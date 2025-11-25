<?php

namespace WebmanTech\Logger\Message;

use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;
use WebmanTech\Logger\Helper\StringHelper;

/**
 * Symfony HttpClient 请求日志
 */
class SymfonyHttpClientMessage extends BaseHttpClientMessage
{
    protected function getResponseStatus(mixed $response): int
    {
        if (!$response instanceof ResponseInterface) {
            return 0;
        }
        try {
            return $response->getStatusCode();
        } catch (Throwable) {
            return 1;
        }
    }

    protected function getResponseContent(mixed $response, int $limitLength): string
    {
        if (!$response instanceof ResponseInterface) {
            return '[Response Type error]';
        }
        try {
            $content = $response->getContent(false);
        } catch (Throwable $e) {
            return '[Response Content error: ' . $e->getMessage() . ']';
        }
        return StringHelper::limit($content, $limitLength);
    }
}
