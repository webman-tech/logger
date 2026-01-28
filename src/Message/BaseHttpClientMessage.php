<?php

namespace WebmanTech\Logger\Message;

use Throwable;
use WebmanTech\CommonUtils\Json;
use WebmanTech\Logger\Helper\StringHelper;

/**
 * 基础 HttpClient 的请求日志
 * @phpstan-type TypeRequest = array{method: string, url: string, options: array}
 */
abstract class BaseHttpClientMessage extends BaseMessage
{
    use TimeBasedMessageTrait;
    use CostCalculateTrait;

    protected string $channel = 'httpClient';

    protected array $skipUrls = []; // 忽略的请求路径，使用正则
    /** @phpstan-ignore-next-line */
    protected ?\Closure $skipRequest = null; // 忽略的请求，返回 true 表示忽略
    /** @phpstan-ignore-next-line */
    protected ?\Closure $extraInfo = null; // 其他信息
    /** @phpstan-ignore-next-line */
    protected ?\Closure $logRequestOptionsFn = null; // 通过 callback 处理记录的 options
    protected bool $logRequestBody = true; // 是否记录请求 body
    protected int $logRequestBodyLimitSize = 1000; // 记录请求 body 的最大长度
    protected bool $logResponseBody = true; // 是否记录响应 body
    protected int $logResponseBodyLimitSize = 1000; // 记录响应 body 的最大长度

    final public function appendSkipUrls(string|array $paths): static
    {
        $this->skipUrls = array_unique(array_merge($this->skipUrls, (array)$paths));

        return $this;
    }

    /**
     * @var TypeRequest|null
     */
    protected ?array $request = null;

    /**
     * 标记一个请求开始
     */
    public function markRequestStart(string $method, string $url, array $options = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->request = [
            'method' => $method,
            'url' => $url,
            'options' => $options,
        ];
        $this->markStartTime();
    }

    /**
     * 标记一个请求结束
     */
    public function markResponseEnd(mixed $response, ?Throwable $exception = null): void
    {
        if (!$this->isEnabled() || !$this->request || !$this->start) {
            return;
        }

        $this->handle($this->request, $response, $exception);
        $this->request = null;
        $this->markStartTime(clear: true);
    }

    /**
     * @param TypeRequest $request
     */
    public function handle(array $request, mixed $response, ?Throwable $exception = null): void
    {
        if (!$this->isEnabled() || !$this->start) {
            return;
        }

        $requestMethod = $request['method'];
        $requestUrl = $request['url'];
        $requestOptions = $request['options'];

        // 请求级别的行为配置
        $requestBasedConfig = array_merge([
            'skip' => false,
            'logMinTimeMS' => $this->logMinTimeMS,
            'warningTimeMS' => $this->warningTimeMS,
            'errorTimeMS' => $this->errorTimeMS,
            'extraInfo' => [],
            'logRequestOptionsFn' => null,
            'logRequestBody' => $this->logRequestBody,
            'logRequestBodyLimitSize' => $this->logRequestBodyLimitSize,
            'logResponseBody' => $this->logResponseBody,
            'logResponseBodyLimitSize' => $this->logResponseBodyLimitSize,
        ], $requestOptions['_logger'] ?? $requestOptions['extra']['_logger'] ?? []);
        unset($requestOptions['_logger'], $requestOptions['extra']['_logger']); // symfony 下不能在 options 中添加自定义参数，因此需要放到 extra 下

        // 单请求定义的跳过
        if ($requestBasedConfig['skip']) {
            return;
        }

        // 全局控制的是否需要跳过
        if ($this->shouldSkipRequest($request, $exception)) {
            return;
        }

        // 计算 cost
        $cost = $this->getCostTimeMs();

        // 根据 cost 控制 level
        $logLevel = match (true) {
            $cost < $requestBasedConfig['logMinTimeMS'] => null,
            $cost < $requestBasedConfig['warningTimeMS'] => 'info',
            $cost < $requestBasedConfig['errorTimeMS'] => 'warning',
            default => 'error',
        };
        if ($logLevel === null) {
            return;
        }

        // 解析 URL 信息
        $urlInfo = parse_url($requestUrl);
        if (!$urlInfo) {
            return;
        }
        $requestHost = $urlInfo['host'] ?? '';
        $requestPath = $urlInfo['path'] ?? '/';
        $message = $requestMethod . ':' . $requestPath;

        // 处理全局 requestOptions
        if ($value = $this->callClosure($this->logRequestOptionsFn, $requestOptions)) {
            $requestOptions = $value;
        }
        // 处理请求级别的 requestOptions
        if ($value = $this->callClosure($requestBasedConfig['logRequestOptionsFn'], $requestOptions)) {
            $requestOptions = $value;
        }
        // 格式化 requestOptions 为可以 json 化的
        $requestOptions = $this->normalizeRequestOptions(
            $requestOptions,
            logRequestBody: $requestBasedConfig['logRequestBody'],
            logRequestBodyLimitSize: $requestBasedConfig['logRequestBodyLimitSize']
        );

        // 构建 context 结构
        $context = [
            'cost' => $cost,
            'method' => $requestMethod,
            'url' => $requestUrl,
            'host' => $requestHost,
            'path' => $requestPath,
            'request_options' => $requestOptions,
        ];

        // response
        if ($exception) {
            $context['response_exception'] = $exception->getMessage();
            if ($logLevel === 'info') {
                $logLevel = 'warning';
            }
        }
        if ($response !== null) {
            $responseStatus = $this->getResponseStatus($response);
            $context['response_status'] = $responseStatus;
            if ($requestBasedConfig['logResponseBody']) {
                $context['response_content'] = $this->getResponseContent(
                    $response,
                    limitLength: $requestBasedConfig['logResponseBodyLimitSize'],
                );
            }

            // 根据 response 状态码控制 level
            if ($responseStatus && $logLevel === 'info') {
                if ($responseStatus >= 500) {
                    $logLevel = 'error';
                } elseif ($responseStatus >= 400) {
                    $logLevel = 'warning';
                }
            }
        }

        // 添加全局的其他信息
        if ($value = $this->callClosure($this->extraInfo, $request, $response)) {
            $context = array_merge($context, (array)$value);
        }
        // 添加请求级别的其他信息
        if ($value = $this->callClosure($requestBasedConfig['extraInfo'], $request, $response)) {
            $context = array_merge($context, (array)$value);
        }

        $this->log($logLevel, $message, $context);
    }

    /**
     * @param TypeRequest $request
     */
    protected function shouldSkipRequest(array $request, ?Throwable $exception = null): bool
    {
        foreach ($this->skipUrls as $pattern) {
            if (preg_match($pattern, $request['url'])) {
                return true;
            }
        }

        if ($value = $this->callClosure($this->skipRequest, $request, $exception)) {
            return $value;
        }

        return false;
    }

    /**
     * 格式化请求的 options
     */
    protected function normalizeRequestOptions(array $options, bool $logRequestBody, int $logRequestBodyLimitSize): array
    {
        foreach ($options as $key => &$value) {
            if ($value === null) {
                unset($options[$key]);
                continue;
            }
            if (in_array($key, ['json', 'body'], true)) {
                if (!$logRequestBody) {
                    $value = '[skip]';
                    continue;
                }
                if (is_array($value)) {
                    // 校验 array 类型的，json 之后是否超长，超长的话截断一下
                    try {
                        $jsonValue = Json::encode($value);
                        if (strlen($jsonValue) > $logRequestBodyLimitSize) {
                            $value = StringHelper::limit($jsonValue, $logRequestBodyLimitSize);
                        }
                    } catch (\Throwable $e) {
                        $value = '[json_encode_error: ' . $e->getMessage() . ']';
                    }
                }
                if (is_string($value)) {
                    $value = StringHelper::limit($value, $logRequestBodyLimitSize);
                }
            }
            if (is_array($value)) {
                $value = array_map(function ($item) {
                    return (!is_null($item) && !is_scalar($item) && !is_array($item))
                        ? ('[' . gettype($item) . ']')
                        : $item;
                }, $value);
            }
            if (!is_scalar($value) && !is_array($value)) {
                $value = '[' . gettype($value) . ']';
            }
        }
        unset($value);

        return $options;
    }

    abstract protected function getResponseStatus(mixed $response): int;

    abstract protected function getResponseContent(mixed $response, int $limitLength): string;
}
