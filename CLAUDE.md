# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 项目概述

webman 日志统筹化管理插件，基于 Monolog 实现，解决多 channel 管理问题。

**解决的问题**：
1. 日志量大时需要分 channel 管理
2. channel 多时配置重复，维护困难
3. `Log::channel('channelName')` 字符串易拼写错误
4. 未充分利用 Monolog 的 formatter 和 processor

**核心功能**：
- **多通道管理**：统一管理多个日志通道
- **模式化处理**：Split、Mix、Stdout、Redis 等模式
- **格式化支持**：结构化日志格式化器
- **处理器机制**：丰富日志内容
- **类型安全**：通过继承 Logger 类提供方法提示
- **灵活配置**：全局和通道级别配置
- **性能优化**：WeakMap 管理 Logger 实例

## 开发命令

测试、静态分析等通用命令与根项目一致，详见根目录 [CLAUDE.md](../../CLAUDE.md)。

## 项目架构

### 核心组件
- **LogManager**：日志管理器，管理多个 channel
- **Logger**：Logger 基类，提供类型安全的日志方法
- **Mode**：
  - `SplitMode`：分离模式，每个 channel 独立
  - `MixMode`：混合模式，多个 channel 合并
  - `StdoutMode`：标准输出模式
  - `RedisMode`：Redis 模式
- **Formatter**：
  - `ChannelFormatter`：channel 格式化器
  - `ChannelMixedFormatter`：channel 混合格式化器
- **Processor**：
  - `RequestRouteProcessor`：请求路由处理器
  - `RequestIpProcessor`：请求 IP 处理器
  - `RequestTraceProcessor`：请求追踪处理器
- **Middleware**：
  - `RequestTraceMiddleware`：请求追踪中间件
  - `HttpRequestLogMiddleware`：HTTP 请求日志中间件
- **Message**：
  - `HttpRequestMessage`：HTTP 请求消息
  - `HttpClientMessage`：HTTP 客户端消息
  - `GuzzleHttpClientMessage`：Guzzle HTTP 客户端消息
  - `SymfonyHttpClientMessage`：Symfony HTTP 客户端消息
  - `EloquentSQLMessage`：Eloquent SQL 消息

### 目录结构
- `src/`：
  - `Mode/`：日志模式
  - `Formatter/`：格式化器
  - `Processor/`：处理器
  - `Middleware/`：中间件
  - `Message/`：消息类
  - `Helper/`：助手类
- `copy/`：配置文件模板
- `src/Install.php`：Webman 安装脚本

测试文件位于项目根目录的 `tests/Unit/Logger/`。

## 代码风格

与根项目保持一致，详见根目录 [CLAUDE.md](../../CLAUDE.md)。

## 注意事项

1. **类型安全**：继承 Logger 类而不是使用 `Log::channel()`
2. **模式选择**：根据场景选择合适的日志模式
3. **Processor 顺序**：Processor 的执行顺序影响日志内容
4. **性能考虑**：大量日志时注意性能优化
5. **WeakMap**：使用 WeakMap 管理 Logger 实例，支持资源释放
6. **测试位置**：单元测试在项目根目录的 `tests/Unit/Logger/` 下，而非包内
