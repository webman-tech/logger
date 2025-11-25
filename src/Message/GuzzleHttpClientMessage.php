<?php

namespace WebmanTech\Logger\Message;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use WebmanTech\Logger\Helper\StringHelper;

/**
 * Guzzle HttpClient 请求日志
 */
class GuzzleHttpClientMessage extends BaseHttpClientMessage
{
    protected function getResponseStatus(mixed $response): int
    {
        if (!$response instanceof ResponseInterface) {
            return 0;
        }
        return $response->getStatusCode();
    }

    protected function getResponseContent(mixed $response, int $limitLength): string
    {
        if (!$response instanceof ResponseInterface) {
            return '[Response Type error]';
        }
        try {
            $content = $response->getBody()->getContents();
        } catch (Throwable $e) {
            return '[Response Content error: ' . $e->getMessage() . ']';
        }
        return StringHelper::limit($content, $limitLength);
    }

    /**
     * 作为 middleware 时的入口
     */
    public function middleware(): \Closure
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $this->markRequestStart($request->getMethod(), (string)$request->getUri(), $options);

                try {
                    /** @var PromiseInterface $promise */
                    $promise = $handler($request, $options);
                } catch (Throwable $reason) {
                    [$response, $exception] = $this->resolveRejectedReason($reason);
                    $this->markResponseEnd($response, $exception);
                    throw $reason;
                }

                return $promise->then(
                    function (ResponseInterface $response) {
                        $this->markResponseEnd($response);
                        return $response;
                    },
                    function (mixed $reason) {
                        [$response, $exception] = $this->resolveRejectedReason($reason);
                        $this->markResponseEnd($response, $exception);
                        return Create::rejectionFor($reason);
                    }
                );
            };
        };
    }

    /**
     * @return array{0: ResponseInterface|null, 1: Throwable|null}
     */
    protected function resolveRejectedReason(mixed $reason): array
    {
        $response = null;
        $exception = $reason instanceof Throwable ? $reason : null;

        if ($reason instanceof RequestException) {
            $response = $reason->getResponse();
        } elseif ($reason instanceof ResponseInterface) {
            $response = $reason;
        }

        return [$response, $exception];
    }

}
