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

[Logger](src/Logger.php) 类是主要入口，提供静态方法调用各日志通道：

- `__callStatic()`: 魔术方法，用于调用各通道日志方法
- `getLogChannelConfigs()`: 获取日志通道配置
- `reset()`: 重置 Logger 实例
- `close()`: 关闭 Logger 实例，释放资源

### LogChannelManager

[LogChannelManager](src/LogChannelManager.php) 负责管理日志通道配置：

- 构建适用于 config/log.php 的通道配置
- 管理日志模式和处理器
- 处理不同通道的日志级别

### 模式(Mode)

模式基本等同于 Monolog 的 Handler，是对 Handler 的二次封装。

#### BaseMode 基础模式

[BaseMode](src/Mode/BaseMode.php) 是所有模式的基类，提供通用配置和方法：

- 通用配置：enable、except_channels、only_channels、formatter
- 检查通道是否启用
- 获取格式化器

#### SplitMode 分离模式

[SplitMode](src/Mode/SplitMode.php) 将不同的日志通道记录到不同的目录下：

```
runtime/
└── logs/
    ├── app/
    │   ├── app-2023-01-01.log
    │   └── app-2023-01-02.log
    └── sql/
        ├── sql-2023-01-01.log
        └── sql-2023-01-02.log
```

适用于开发和测试环境，便于按通道和日期排查错误。

#### MixMode 混合模式

[MixMode](src/Mode/MixMode.php) 将所有日志通道记录到同一个目录下：

```
runtime/
└── logs/
    └── channelMixed/
        ├── channelMixed-2023-01-01.log
        └── channelMixed-2023-01-02.log
```

适用于将日志写入 ELK 等集中日志管理系统。

#### StdoutMode 标准输出模式

[StdoutMode](src/Mode/StdoutMode.php) 将日志输出到控制台，适用于 Docker 环境。

#### RedisMode Redis 模式

[RedisMode](src/Mode/RedisMode.php) 将日志写入 Redis，适用于需要进一步处理日志的场景。

### 格式化器(Formatter)

格式化器用于结构化日志格式，方便日志的筛查和查看。

#### ChannelFormatter 通道格式化器

[ChannelFormatter](src/Formatter/ChannelFormatter.php) 提供基本的通道格式：

```
[时间][请求唯一标识][日志级别][客户端IP][用户ID][路由]: 日志内容
```

#### ChannelMixedFormatter 混合格式化器

[ChannelMixedFormatter](src/Formatter/ChannelMixedFormatter.php) 在 ChannelFormatter 基础上增加通道名：

```
[时间][请求唯一标识][通道名][日志级别][客户端IP][用户ID][路由]: 日志内容
```

### 处理器(Processors)

处理器用于在日志记录时向日志中添加额外信息。

#### CurrentUserProcessor 当前用户处理器

[CurrentUserProcessor](src/Processors/CurrentUserProcessor.php) 添加当前用户信息（IP地址和用户ID）。

#### RequestRouteProcessor 请求路由处理器

[RequestRouteProcessor](src/Processors/RequestRouteProcessor.php) 添加当前请求路由信息。

#### RequestUidProcessor 请求UID处理器

[RequestUidProcessor](src/Processors/RequestUidProcessor.php) 添加请求唯一标识，需要配合 RequestUid 中间件使用。

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