# webman-tech/logger

本项目是从 [webman-tech/components-monorepo](https://github.com/orgs/webman-tech/components-monorepo) 自动 split 出来的，请勿直接修改

## 简介

webman 日志统筹化管理插件，基于 Monolog 实现，旨在解决 webman 原生日志配置的一些不便之处：

1. 日志量较大时需要分 channel 管理，但每个 channel 都需要单独定义，维护困难
2. 通过字符串 channel 名调用时容易拼写错误导致日志记录失败
3. 没有充分利用 Monolog 的 formatter 和 processor 功能

本插件针对多 channel 模式进行统筹优化管理。

## 功能特性

- **多通道管理**：统一管理多个日志通道，避免重复配置
- **模式化处理**：支持多种日志处理模式（Split、Mix、Stdout、Redis 等）
- **格式化支持**：提供结构化的日志格式化器
- **处理器机制**：支持多种日志处理器，丰富日志内容
- **类型安全**：通过继承 Logger 类提供方法提示，避免拼写错误
- **灵活配置**：支持全局和通道级别的灵活配置
- **性能优化**：使用 WeakMap 管理 Logger 实例，支持资源释放

## 安装

```bash
composer require webman-tech/logger
```

## 核心组件

### Logger 主类

[Logger](src/Logger.php) 通过静态方法连接多个日志通道，负责将调用转发到指定通道（`__callStatic()`）、生成 `config/log.php` 所需的 handler 配置（`getLogChannelConfigs()`），以及释放 handler 和文件句柄等资源（`reset()/close()`）。

### LogChannelManager

[LogChannelManager](src/LogChannelManager.php) 将 channel、mode、processor、level 组合起来，为每个 channel 的每个 mode 生成对应 handler，根据 `levels.default/special` 决定日志级别，并对 mode 实例进行缓存避免重复实例化。

### 模式（Mode）

模式本质是 Monolog Handler 的包装，带有统一的 `enable/only_channels/except_channels/formatter` 配置：

- [SplitMode](src/Mode/SplitMode.php)：每个 channel 拥有独立目录，按日期轮转
- [MixMode](src/Mode/MixMode.php)：所有 channel 写入 `channelMixed`，方便集中采集
- [StdoutMode](src/Mode/StdoutMode.php)：输出到 `php://stdout`，容器友好
- [RedisMode](src/Mode/RedisMode.php)：写入 Redis，方便异步处理

### 格式化器（Formatter）

- [ChannelFormatter](src/Formatter/ChannelFormatter.php)：格式为 `[时间][traceId][前缀][等级][IP][UserId][Route]: 消息`
- [ChannelMixedFormatter](src/Formatter/ChannelMixedFormatter.php)：在 ChannelFormatter 基础上包含 `%channel%` 占位符，适合 MixMode

### 处理器（Processor）

内置多个 Processor，均可自由组合：

- [RequestIpProcessor](src/Processors/RequestIpProcessor.php)：注入当前请求 IP
- [RequestRouteProcessor](src/Processors/RequestRouteProcessor.php)：注入 `METHOD:/path`
- [RequestTraceProcessor](src/Processors/RequestTraceProcessor.php)：从 `RequestTraceMiddleware` 或 `X-Trace-Id` 读取 trace id
- [AuthUserIdProcessor](src/Processors/AuthUserIdProcessor.php)：从 Auth guard 获取用户 ID

## HTTP 日志工具

### HttpRequestMessage 与 HttpRequestLogMiddleware

`HttpRequestMessage` 记录 Web 请求生命周期（耗时、方法、路径、Query、Body、响应/异常），并根据 `logMinTimeMS/warningTimeMS/errorTimeMS` 自动调整日志等级，支持跳过路径、敏感字段遮蔽、请求体大小限制、附加信息等钩子。[HttpRequestLogMiddleware](src/Middleware/HttpRequestLogMiddleware.php) 即插即用，支持通过环境变量覆盖配置。

### HttpClient 请求日志

- [GuzzleHttpClientMessage](src/Message/GuzzleHttpClientMessage.php)：提供 middleware，可直接 push 到 `HandlerStack`
- [SymfonyHttpClientMessage](src/Message/SymfonyHttpClientMessage.php)：与 `MockHttpClient/MockResponse` 完全兼容，易于写测试

两者均内置时间分级、请求/响应体截断、单次请求覆盖 logger、附加信息等能力。

### EloquentSQLMessage

[EloquentSQLMessage](src/Message/EloquentSQLMessage.php) 记录 SQL 日志，支持按 SQL 或正则忽略语句，并按耗时自动输出 INFO/WARNING/ERROR 等级。

## AI 辅助

- **开发维护**：[AGENTS.md](AGENTS.md) — 面向 AI 的代码结构和开发规范说明
- **使用指南**：[skills/webman-tech-logger-best-practices/SKILL.md](skills/webman-tech-logger-best-practices/SKILL.md) — 面向 AI 的最佳实践，可安装到 Claude Code 的 skills 目录使用
