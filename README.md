# webman-tech/logger

本项目是从 [webman-tech/components-monorepo](https://github.com/orgs/webman-tech/components-monorepo) 自动 split
出来的，请勿直接修改

## 简介

webman 日志统筹化管理插件，基于 Monolog 实现，旨在解决 webman 原生日志配置的一些不便之处：

1. 当日志量较大时，不可能所有日志都通过 `Log::info` 的形式记录，需要分 channel 管理
2. 当 channel 数量较多时，每个都需要单独定义，基本上是复制粘贴操作，后期如果需要切换所有通道的写入方式，需要逐一修改，维护困难
3. 每次通过 `Log::channel('channelName')` 的形式调用时，由于 `channelName` 是字符串，容易拼写错误导致日志记录失败
4. 没有充分利用 Monolog 的 formatter 和 processor 功能

本插件正是为了解决以上问题，针对多 `channel` 模式进行统筹优化管理。

## 功能特性

- **多通道管理**：统一管理多个日志通道，避免重复配置
- **模式化处理**：支持多种日志处理模式（Split、Mix、Stdout、Redis等）
- **格式化支持**：提供结构化的日志格式化器
- **处理器机制**：支持多种日志处理器，丰富日志内容
- **类型安全**：通过继承 Logger 类提供方法提示，避免拼写错误
- **灵活配置**：支持全局和通道级别的灵活配置
- **性能优化**：使用 WeakMap 管理 Logger 实例，支持资源释放

## 安装

```bash
composer require webman-tech/logger
```

## 快速开始

### 基本配置

1. 在 `config/plugin/webman-tech/logger/log-channel.php` 中配置日志通道：

```php
return [
    'channels' => [
        'app',
        'sql',
        'business',
    ],
];
```

2. 在 `config/log.php` 中合并配置：

```php
use support\facade\Logger;

return array_merge(
    [
        // 原有配置
    ],
    Logger::getLogChannelConfigs()
);
```

3. 创建自定义 Logger 类（可选但推荐）：

```php
<?php

namespace support\facade;

/**
 * @method static void app($msg, string $type = 'info', array $context = [])
 * @method static void sql($msg, string $type = 'info', array $context = [])
 * @method static void business($msg, string $type = 'info', array $context = [])
 */
class Logger extends \WebmanTech\Logger\Logger
{
}
```

### 基本使用

```php
use support\facade\Logger;

// 记录日志到 app 通道
Logger::app('这是一条应用日志');

// 记录不同级别的日志
Logger::app('这是一条错误日志', 'error');

// 记录数组数据
Logger::app(['user_id' => 123, 'action' => 'login']);

// 记录异常
Logger::app($exception);

// 使用上下文
Logger::app('用户 {name} 执行了 {action}', 'info', ['name' => '张三', 'action' => '登录']);
```

## 核心组件

### Logger 主类

[Logger](src/Logger.php) 通过静态方法连接多个日志通道，负责：

- `__callStatic()`：转发到指定通道并自动登记被使用的 Logger；
- `getLogChannelConfigs()`：交给 LogChannelManager 生成 `config/log.php` 的最终 handler;
- `reset()/close()`：释放 handler、文件句柄等资源。

### LogChannelManager

[LogChannelManager](src/LogChannelManager.php) 把 channel/mode/processor/level 组合起来：

- 遍历 channel，为每个 mode 生成 handler；
- 根据 `levels.default/special` 决定级别；
- `processors` 支持数组或回调，并强制校验必须实现 `ProcessorInterface`；
- mode 会被缓存，避免重复实例化。

### 模式 (Mode)

模式本质是 Monolog Handler 的包装，带有统一的 `enable/only_channels/except_channels/formatter` 配置。

- [BaseMode](src/Mode/BaseMode.php)：实现通用开关与 formatter 管理；
- [SplitMode](src/Mode/SplitMode.php)：每个 channel 拥有独立目录，按日期轮转；
- [MixMode](src/Mode/MixMode.php)：所有 channel 写入 `channelMixed`，方便集中采集；
- [StdoutMode](src/Mode/StdoutMode.php)：输出到 `php://stdout`，容器友好；
- [RedisMode](src/Mode/RedisMode.php)：写入 Redis，方便异步处理。

### 格式化器 (Formatter)

日志行格式统一由 Formatter 控制：

- [ChannelFormatter](src/Formatter/ChannelFormatter.php)：`[时间][traceId][前缀][等级][IP][UserId][Route]: 消息`；
- [ChannelMixedFormatter](src/Formatter/ChannelMixedFormatter.php)：在 ChannelFormatter 基础上包含 `%channel%` 占位符，适合 MixMode。

### 处理器 (Processor)

内置多个 Processor，均可自由组合：

- [RequestIpProcessor](src/Processors/RequestIpProcessor.php)：注入当前请求 IP；
- [RequestRouteProcessor](src/Processors/RequestRouteProcessor.php)：注入 `METHOD:/path`；
- [RequestTraceProcessor](src/Processors/RequestTraceProcessor.php)：从 `RequestTraceMiddleware` 或 `X-Trace-Id` 读取 trace id；
- [AuthUserIdProcessor](src/Processors/AuthUserIdProcessor.php)：从 Auth guard 获取用户 ID；
- [CurrentUserProcessor](src/Processors/CurrentUserProcessor.php)：兼容旧版的 IP / userId 逻辑（已不推荐）；
- [RequestUidProcessor](src/Processors/RequestUidProcessor.php)：配合旧版 `RequestUid` 中间件，为日志增加 `uid/traceId`（已废弃，建议迁移到 RequestTrace）。

## HTTP 日志工具

### HttpRequestMessage 与 HttpRequestLogMiddleware

- `HttpRequestMessage` 记录 Web 请求生命周期（耗时、方法、路径、Query、Body、响应/异常），并根据 `logMinTimeMS / warningTimeMS / errorTimeMS` 自动调整等级；
- 支持 `skipPaths/skipRequest`、`logRequestQueryFn/logRequestBodyFn`、`appendLogRequestBodySensitive()`、`extraInfo`、`logRequestBodyLimitSize` 等钩子；
- [HttpRequestLogMiddleware](src/Middleware/HttpRequestLogMiddleware.php) 即插即用，可通过 `HTTP_REQUEST_LOG_CONFIG` 环境变量覆盖配置：

```php
put_env('HTTP_REQUEST_LOG_CONFIG', [
    'logMinTimeMS' => 100,
    'skipPaths' => ['/\\/health\\//'],
    'logRequestBodySensitive' => ['password', 'token'],
    'extraInfo' => fn($request) => ['trace_id' => $request->getCustomData('trace_id')],
]);
```

### HttpClient 请求日志

- [BaseHttpClientMessage](src/Message/BaseHttpClientMessage.php) 内置时间分级、请求/响应体截断、`_logger` 单次请求覆盖、`extraInfo` 等能力；
- [GuzzleHttpClientMessage](src/Message/GuzzleHttpClientMessage.php) 提供 middleware，可直接 push 到 `HandlerStack`；
- [SymfonyHttpClientMessage](src/Message/SymfonyHttpClientMessage.php) 与 `MockHttpClient/MockResponse` 完全兼容，易于写测试。

### SQL 与其他消息

- [EloquentSQLMessage](src/Message/EloquentSQLMessage.php) 可根据 SQL / 正则忽略语句，并按耗时输出 INFO/WARNING/ERROR；
- 结合 HttpRequest/HttpClient 消息，可以在入口、下游以及数据库层面保持统一的日志格式。

### 敏感数据 & 工具

- [StringHelper](src/Helper/StringHelper.php) 提供 `limit()` 截断字符串、`maskSensitiveFields()` 批量遮蔽 JSON/Form/纯文本敏感字段；
- `RequestTraceMiddleware` 注入 trace id，配合 RequestTraceProcessor；
- `ResetLog` 中间件在响应后执行 `Logger::reset()/close()`，防止 handler 被复用过久；
- `RequestUid` 中间件仅用于旧版兼容，建议全面使用 RequestTrace 方案。

## 配置说明

### 主要配置项

在 `config/plugin/webman-tech/logger/log-channel.php` 中配置：

```php
return [
    'channels' => [
        // 日志通道列表
        'app',
        'sql',
        'business',
    ],
    'modes' => [
        // 日志模式配置
        [
            'class' => \WebmanTech\Logger\Mode\SplitMode::class,
            'enable' => true,
            'max_files' => 30,
        ],
    ],
    'levels' => [
        // 日志级别配置
        'default' => 'info',
        'special' => [
            'sql' => 'debug',
        ],
    ],
    'processors' => [
        // 处理器配置
        new \WebmanTech\Logger\Processors\RequestUidProcessor(),
        new \WebmanTech\Logger\Processors\CurrentUserProcessor(),
        new \WebmanTech\Logger\Processors\RequestRouteProcessor(),
    ],
];
```

### 配置层级

- **全局配置**：在 `config/plugin/webman-tech/logger/log-channel.php` 中定义
- **通道级别配置**：通过 `levels.special` 为特定通道设置不同日志级别

## 高级用法

### 自定义模式

通过继承 [BaseMode](src/Mode/BaseMode.php) 创建自定义日志模式：

```php
use WebmanTech\Logger\Mode\BaseMode;

class CustomMode extends BaseMode
{
    public function getHandler(string $channelName, string $level): array
    {
        if (!$this->checkHandlerUsefulForChannel($channelName)) {
            return [];
        }

        return [
            'class' => YourHandler::class,
            'constructor' => [
                // 配置参数
            ],
            'formatter' => $this->getFormatter(),
        ];
    }
}
```

### 自定义格式化器

创建自定义格式化器满足特定的日志格式需求：

```php
use Monolog\Formatter\LineFormatter;

class CustomFormatter extends LineFormatter
{
    public function __construct()
    {
        $format = "[%datetime%][%channel%][%level_name%]: %message%\n";
        parent::__construct($format);
    }
}
```

### 动态调整日志级别

通过配置 `levels` 选项为不同通道设置不同的日志级别：

```php
'levels' => [
    'default' => 'info',           // 默认日志级别
    'special' => [
        'sql' => 'debug',          // SQL 通道使用 debug 级别
        'business' => 'warning',   // 业务通道使用 warning 级别
    ],
],
```

### 处理器配置

通过 `processors` 配置添加自定义处理器：

```php
'processors' => function() {
    return [
        new \WebmanTech\Logger\Processors\RequestUidProcessor(),
        new \WebmanTech\Logger\Processors\CurrentUserProcessor(function() {
            // 获取当前用户ID的逻辑
            return Auth::id();
        }),
    ];
},
```

## 最佳实践

1. **合理规划日志通道**：根据业务模块或功能划分日志通道
2. **选择合适的模式**：开发环境使用 SplitMode，生产环境根据需要选择 MixMode 或其他模式
3. **配置适当的日志级别**：避免记录过多无用的调试信息
4. **使用结构化日志**：通过格式化器和处理器丰富日志信息
5. **定期清理日志**：设置合理的日志保留天数
6. **监控重要日志**：对错误和警告级别的日志进行监控和告警
