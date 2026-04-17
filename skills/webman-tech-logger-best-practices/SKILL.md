---
name: webman-tech-logger-best-practices
description: webman-tech/logger 最佳实践。使用场景：用户配置多 channel 日志管理时，给出明确的推荐写法。
---

# webman-tech/logger 最佳实践

## 核心原则

1. **全项目一个 Logger 类**，所有 channel 作为常量集中管理
2. **channel 即业务模块**：每个业务模块一个 channel，日志分目录存放
3. **Processor 附加上下文**，不要在每条日志里手动传 IP/用户/TraceId

---

## 快速配置

### 1. 定义项目 Logger 类

```php
// support/facade/Logger.php

/**
 * @method static void app($msg, string $type = 'info', array $context = [])
 * @method static void order($msg, string $type = 'info', array $context = [])
 * @method static void payment($msg, string $type = 'info', array $context = [])
 * @method static void httpRequest($msg, string $type = 'info', array $context = [])
 * @method static void httpClient($msg, string $type = 'info', array $context = [])
 * @method static void sql($msg, string $type = 'info', array $context = [])
 */
class Logger extends \WebmanTech\Logger\Logger
{
    public const CHANNEL_APP          = 'app';
    public const CHANNEL_ORDER        = 'order';
    public const CHANNEL_PAYMENT      = 'payment';
    public const CHANNEL_HTTP_REQUEST = 'httpRequest';
    public const CHANNEL_HTTP_CLIENT  = 'httpClient';
    public const CHANNEL_SQL          = 'sql';

    // 获取所有 channel，可用于自动同步 log-channel.php 的 channels 配置
    public static function getAllChannels(): array
    {
        $ref = new \ReflectionClass(self::class);
        return array_unique(array_values(
            array_filter($ref->getConstants(), fn($name) => str_starts_with($name, 'CHANNEL_'), \ARRAY_FILTER_USE_KEY)
        ));
    }
}
```

### 2. 注册 channels

```php
// config/plugin/webman-tech/logger/log-channel.php
return [
    // 直接从 Logger 类读取，避免手动维护两处
    'channels' => \support\facade\Logger::getAllChannels(),
    'levels' => [
        'default' => config('app.debug') ? 'debug' : 'info',
    ],
    'processors' => function () {
        return [
            new \Monolog\Processor\PsrLogMessageProcessor('Y-m-d H:i:s', true),
            new \WebmanTech\Logger\Processors\RequestRouteProcessor(),
            new \WebmanTech\Logger\Processors\RequestIpProcessor(),
            new \WebmanTech\Logger\Processors\AuthUserIdProcessor(),
            new \WebmanTech\Logger\Processors\RequestTraceProcessor(),
        ];
    },
    'modes' => [
        'split' => [
            'class' => \WebmanTech\Logger\Mode\SplitMode::class,
            'enable' => true,
            'formatter' => ['class' => \WebmanTech\Logger\Formatter\ChannelFormatter::class],
            'max_files' => 30,
        ],
    ],
];
```

### 3. 合并到 webman 日志配置

```php
// config/log.php
return array_merge(
    [/* 原有配置 */],
    \WebmanTech\Logger\Logger::getLogChannelConfigs(),
);
```

### 4. 挂载中间件

```php
// config/middleware.php
return [
    '' => [
        \WebmanTech\Logger\Middleware\RequestTraceMiddleware::class,  // 生成 TraceId
        \WebmanTech\Logger\Middleware\ResetLog::class,                // 请求结束后重置日志
    ],
];
```

---

## 使用方式

```php
use support\facade\Logger;

// 方法名即 channel 名，IDE 有 @method 提示
Logger::order('订单创建', context: ['order_id' => $orderId]);
Logger::payment('支付完成', 'info', ['amount' => $amount]);
Logger::httpClient('请求失败', 'warning', ['url' => $url]);
```

不要用字符串方式，也不要为每个业务模块单独建 Logger 类：

```php
// ❌ 字符串 channel 名，拼错不报错
Log::channel('order')->info('订单创建');

// ❌ 每个模块单独一个 Logger 类，channel 分散难以管理
class OrderLogger extends Logger {}
class PaymentLogger extends Logger {}
```

---

## 日志模式选择

| 模式 | 适用场景 |
|------|---------|
| `SplitMode`（默认） | 每个 channel 独立目录，便于按模块查看 |
| `MixMode` | 所有 channel 合并到一个文件，便于全局搜索 |
| `StdoutMode` | 容器/开发环境，输出到标准输出 |
| `RedisMode` | 需要实时消费日志的场景 |

可以同时开启多个模式（如 SplitMode + StdoutMode）。

---

## Processor 说明

Processor 自动为每条日志附加上下文信息，不需要在业务代码里手动传：

| Processor | 附加信息 |
|-----------|---------|
| `RequestRouteProcessor` | 当前路由路径 |
| `RequestIpProcessor` | 客户端 IP |
| `RequestTraceProcessor` | TraceId（需配合 `RequestTraceMiddleware`） |
| `AuthUserIdProcessor` | 当前登录用户 ID |

---

## 结构化消息（Message 类）

Message 类是封装好的常用结构化日志，自动记录耗时、请求/响应内容等重要信息，开箱即用。

### Web HTTP 请求日志

`HttpRequestMessage` + `HttpRequestLogMiddleware` 配套，自动记录所有入站请求（含耗时、路径、请求体、响应状态）：

```php
// config/middleware.php 中添加
new \WebmanTech\Logger\Middleware\HttpRequestLogMiddleware([
    'channel' => 'httpRequest',
])
```

需要自定义时继承 `HttpRequestMessage`：

```php
class AppHttpRequestMessage extends HttpRequestMessage
{
    protected string $channel = 'httpRequest';
    protected array $logRequestBodySensitive = ['password', 'token'];  // 敏感字段脱敏
    protected array $skipPaths = ["/^\/health$/i"];                    // 跳过特定路径
}

new \WebmanTech\Logger\Middleware\HttpRequestLogMiddleware(new AppHttpRequestMessage())
```

### 外部 HTTP 请求日志（HttpClient）

自动记录外部 HTTP 请求的耗时、URL、响应状态和响应体。

**Guzzle：**

```php
use WebmanTech\Logger\Message\GuzzleHttpClientMessage;
use GuzzleHttp\HandlerStack;

$message = new GuzzleHttpClientMessage(['channel' => 'httpClient']);
$stack = HandlerStack::create();
$stack->push($message->middleware());

$client = new \GuzzleHttp\Client(['handler' => $stack]);
```

**Symfony HttpClient：**

```php
use WebmanTech\Logger\Message\SymfonyHttpClientMessage;

$message = new SymfonyHttpClientMessage(['channel' => 'httpClient']);

$message->markRequestStart('GET', $url);
try {
    $response = $client->request('GET', $url);
    $message->markResponseEnd($response);
} catch (\Throwable $e) {
    $message->markResponseEnd(null, $e);
    throw $e;
}
```

### SQL 日志（Eloquent）

自动记录 SQL 语句和执行耗时，绑定到 Eloquent 连接：

```php
use WebmanTech\Logger\Message\EloquentSQLMessage;

$message = new EloquentSQLMessage(['channel' => 'sql']);
$message->bindConnection(\support\Db::connection());
```

需要自定义时继承：

```php
class AppSQLMessage extends EloquentSQLMessage
{
    protected bool $logNotSelect = true;    // 非 SELECT 必记
    protected bool $bindSQLBindings = true; // 绑定参数到 SQL
    protected array $ignoreSql = ['select 1']; // 忽略心跳 SQL
}
```

---

## 常见错误

| 错误 | 原因 | 解决 |
|------|------|------|
| `请先在 log-channel.php 配置中配置 channels` | channel 名未在配置中注册 | 在 `channels` 数组中添加该 channel 名 |
| 日志文件不生成 | 所有 mode 都 `enable: false` | 至少开启一个 mode |
| TraceId 为空 | 未挂载 `RequestTraceMiddleware` | 在全局中间件中添加 |
| 请求结束后日志未刷新 | 未挂载 `ResetLog` | 在全局中间件中添加 |
