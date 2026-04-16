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

测试、静态分析等通用命令与根项目一致，详见根目录 [AGENTS.md](../../AGENTS.md)。

## 目录结构
- `src/`：
  - `LogChannelManager.php`：channel 管理器，统一管理多个日志通道
  - `Logger.php`：Logger 基类，业务 Logger 继承此类以获得类型安全的方法提示
  - `Mode/`：日志模式（Split/Mix/Stdout/Redis），决定日志如何路由和存储
  - `Processors/`：日志处理器，为日志记录附加上下文信息（IP/Route/TraceId/UserId 等）
  - `Formatter/`：格式化器（ChannelFormatter/ChannelMixedFormatter）
  - `Middleware/`：RequestTraceMiddleware/HttpRequestLogMiddleware 等
  - `Message/`：结构化消息类（HttpRequest/GuzzleHttpClient/SymfonyHttpClient/EloquentSQL 等）
  - `Helper/`：ConfigHelper/StringHelper
- `copy/`：配置文件模板
- `src/Install.php`：Webman 安装脚本

测试文件位于项目根目录的 `tests/Unit/Logger/`。测试环境配置和 Helper 函数详见根目录 [AGENTS.md](../../AGENTS.md) 的测试相关章节。

## 工作流程

```
业务代码
    │ MyLogger extends Logger (类型安全，避免字符串 channel 名)
    ▼
LogChannelManager
    │
    ▼
Mode (决定日志路由方式)
    ├── SplitMode  ──→ 各 channel 独立文件
    ├── MixMode    ──→ 多 channel 合并到一个文件
    ├── StdoutMode ──→ 标准输出
    └── RedisMode  ──→ Redis 存储
    │
    ▼
Processors (附加上下文信息)
    └── IP / Route / TraceId / UserId...
    │
    ▼
Formatter ──→ Monolog Handler ──→ 输出
```

## 代码风格

与根项目保持一致，详见根目录 [AGENTS.md](../../AGENTS.md)。

## 注意事项

1. **类型安全**：继承 Logger 类而不是使用 `Log::channel()`
2. **模式选择**：根据场景选择合适的日志模式
3. **Processor 顺序**：Processor 的执行顺序影响日志内容
4. **性能考虑**：大量日志时注意性能优化
5. **WeakMap**：使用 WeakMap 管理 Logger 实例，支持资源释放
