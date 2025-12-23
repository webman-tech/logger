<?php

namespace WebmanTech\Logger\Message;

use Closure;
use Throwable;
use WebmanTech\CommonUtils\Request;
use WebmanTech\CommonUtils\Response;
use WebmanTech\Logger\Helper\StringHelper;

/**
 * Http 请求日志
 */
class HttpRequestMessage extends BaseMessage
{
    use TimeBasedMessageTrait;
    use CostCalculateTrait;

    protected string $channel = 'httpRequest';

    protected array $skipPaths = [
        "/^\/\.well-known\/.*/i",
        "/^\/favicon.ico$/i",
        '/^\/_debugbar\/.*/i',
    ]; // 忽略的请求路径，使用正则
    /** @phpstan-ignore-next-line */
    protected ?Closure $skipRequest = null; // 忽略的请求，返回 true 表示忽略
    /** @phpstan-ignore-next-line */
    protected ?Closure $extraInfo = null; // 其他信息
    protected bool $logRequestQuery = true; // 是否记录请求参数
    /** @phpstan-ignore-next-line */
    protected ?Closure $logRequestQueryFn = null; // 通过 callback 处理记录的 query
    protected bool $logRequestBody = true; // 是否记录请求 body
    /** @phpstan-ignore-next-line */
    protected ?Closure $logRequestBodyFn = null; // 通过 callback 处理记录请求 body
    protected array $logRequestBodySensitive = [
        'password',
        'password_confirmation',
        'password_confirm',
        'old_password',
        'new_password',
        'new_password_confirmation',
    ]; // requestBody 中的敏感字段
    protected int $logRequestBodyLimitSize = 1000; // 记录请求 body 的最大长度

    final public function appendSkipPaths(string|array $paths): static
    {
        $this->skipPaths = array_unique(array_merge($this->skipPaths, (array)$paths));

        return $this;
    }

    final public function appendLogRequestBodySensitive(string|array $keys): static
    {
        $this->logRequestBodySensitive = array_unique(array_merge($this->logRequestBodySensitive, (array)$keys));

        return $this;
    }

    private mixed $request = null;

    public function markRequestStart(mixed $request): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->request = $request;
        $this->markStartTime();
    }

    public function markResponseEnd(mixed $response, ?Throwable $exception = null): void
    {
        if (!$this->isEnabled() || !$this->request || !$this->start) {
            return;
        }

        $this->handle(Request::from($this->request), $response ? Response::from($response) : null, $exception);
        $this->request = null;
        $this->markStartTime(clear: true);
    }

    public function handle(Request $request, ?Response $response, ?Throwable $exception): void
    {
        if (!$this->isEnabled() || !$this->start) {
            return;
        }

        // 计算 cost
        $cost = $this->getCostTimeMs();

        // 根据耗时获取 level
        $logLevel = $this->getLogLevelByTime($cost);
        if ($logLevel === null) {
            return;
        }

        $requestMethod = $request->getMethod();
        $requestPath = $request->getPath();
        if ($this->shouldSkipRequestPath($requestPath)) {
            return;
        }
        if ($this->shouldSkipRequest($request, $response, $exception)) {
            return;
        }

        // 构建 context 结构
        $message = $requestMethod . ':' . $requestPath;
        $context = [
            'cost' => $cost,
            'method' => $requestMethod,
            'path' => $requestPath,
            'query' => $this->getRequestQuery($request),
            'body' => $this->getRequestBody($request),
        ];

        // response
        if ($exception) {
            $context['response_exception'] = $exception->getMessage();
            if ($logLevel === 'info') {
                $logLevel = 'warning';
            }
        }
        if ($response) {
            $responseStatus = $response->getStatusCode();
            $context['response_status'] = $responseStatus;

            // 根据 response 状态码控制 level
            if ($responseStatus && $logLevel === 'info') {
                if ($responseStatus >= 500) {
                    $logLevel = 'error';
                } elseif ($responseStatus >= 400) {
                    $logLevel = 'warning';
                }
            }
        }
        // 添加其他信息
        if ($value = $this->callClosure($this->extraInfo, $request, $response)) {
            $context = array_merge($context, (array)$value);
        }

        $this->log($logLevel, $message, $context);
    }

    protected function shouldSkipRequestPath(string $path): bool
    {
        foreach ($this->skipPaths as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    protected function shouldSkipRequest(Request $request, ?Response $response, ?Throwable $exception): bool
    {
        return $this->callClosure($this->skipRequest, $request, $response, $exception) ?? false;
    }

    protected function getRequestQuery(Request $request): ?string
    {
        if (!$this->logRequestQuery) {
            return null;
        }
        if (($value = $this->callClosure($this->logRequestQueryFn, $request)) !== null) {
            if ($value === false) {
                return null;
            }
            return $value;
        }
        return http_build_query($request->allGet());
    }

    protected function getRequestBody(Request $request): ?string
    {
        if (!$this->logRequestBody) {
            return null;
        }
        if (($value = $this->callClosure($this->logRequestBodyFn, $request)) !== null) {
            if ($value === false) {
                return null;
            }
            return $value;
        }
        $contentType = $request->getContentType();
        $contentLength = $request->header('content-length') ?? 0;
        if (
            !is_numeric($contentLength)
            || $contentLength <= 0
            || !(
                str_contains($contentType, 'application/json')
                || str_contains($contentType, 'application/x-www-form-urlencoded')
            )
        ) {
            return null;
        }

        $content = $request->rawBody();
        if (!$content) {
            return null;
        }
        if ($contentLength > $this->logRequestBodyLimitSize) {
            $content = StringHelper::limit($content, $this->logRequestBodyLimitSize);
        }
        return StringHelper::maskSensitiveFields($content, $this->logRequestBodySensitive);
    }
}
