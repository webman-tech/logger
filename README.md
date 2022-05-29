# kriss/webman-logger

webman log 统筹化管理插件

## 简介

webman 支持原始的 monolog 配置形式，配置灵活，但是从以下情况来看不是特别便利：

1. 当日志特别多，不可能所有日志都通过 `Log::info` 的形式去记录，肯定是需要分 channel 的
2. 当 channel 特别多的时候：每个都要去单独定义，但基本都是复制粘贴的，当后期切换所有通道写入渠道时，需要逐一修改，不太好维护
3. 每次通过 `Log::channel('channelName')` 的形式去调用，因为 `channelName` 是字符串，万一单词拼错，会导致日志记录不到
4. 没有很好的利用好 monolog 的 formatter 和 processor

此插件即为了解决以上问题，针对多 `channel` 模式进行统筹优化管理

## 安装

composer require kriss/webman-logger

## 配置

1. 主要的配置文件位于：`config/plugin/kriss/webman-logger/log-channel.php`，按需调整，后文详细对其中部分配置做说明

2. （建议）自定义一个 Logger 类继承 `Kriss\WebmanLogger\Logger`，比如 `support\facade\Logger`，便于后期扩展和使用

3. （必须）在 `config/log.php` 中合并原来的配置和 `Logger::getLogChannelConfigs()`，例如：

```php
use support\facade\Logger;

return array_merge(
    [
        'default' => [
            'handlers' => [
                [
                    'class' => Monolog\Handler\RotatingFileHandler::class,
                    'constructor' => [
                        runtime_path() . '/logs/webman.log',
                        7, //$maxFiles
                        Monolog\Logger::DEBUG,
                    ],
                    'formatter' => [
                        'class' => Monolog\Formatter\LineFormatter::class,
                        'constructor' => [null, 'Y-m-d H:i:s', true],
                    ],
                ]
            ],
        ],
    ],
    Logger::getLogChannelConfigs(), // merge 这个
);
```

4. 新增一个日志 channel，执行以下两步操作：

   1. （必须）在 `config/plugin/kriss/webman-logger/log-channel.php` 的 `channels` 中添加日志 channel 的名字，建议小驼峰命名，例如 `purchaseOrder`
   2. （建议）在 `support\facade\Logger` 的类上方添加注释：`@method static void purchaseOrder($msg, string $type = 'info', array $context = [])`

步骤2是为了代码提示，后期记录日志可以直接使用 `support\facade\Logger::purchaseOrder('xxx')` 的形式，
如果未定义该类，一样可以使用 `support\Log::channel('purchaseOrder')->info('xxx')` 的方式去记录日志

后期新增其他 channel，只需要重复4即可

## 使用

假设已经在 `config/plugin/kriss/webman-logger/log-channel.php` 的 `channels` 中配置了两个 channels: app 和 sql，建议有如下 Logger 类：

```php
<?php

namespace support\facade;

/**
 * @method static void app($msg, string $type = 'info', array $context = [])
 * @method static void sql($msg, string $type = 'info', array $context = [])
 */
class Logger extends \Kriss\WebmanLogger\Logger
{
}
```

### 记录日志

```php
use support\facade\Logger;

Logger::app('xxx'); // 记录 xxx 到 app channel
Logger::app(['x' => 'y']); // 支持数组写入，写入日志时会转成 json
Logger::app($exception); // 支持 Exception 写入，写入时会记录详细的 trace
Logger::app('xxx', 'error'); // 切换 level
Logger::app('hello: {name}', 'info', ['name' => 'Kriss']); // 使用 PsrLogMessageProcessor 处理，会记录成 "hello Kriss"
```

### 模式(Mode)介绍

模式基本等同于 monolog 的 Handler，基本来说就是 Handler 的二次封装，多个模式可以同时启用，以下时已有的模式介绍

#### SplitMode

不同的channel分别会被记录到不同的目录下，目录名和文件名均为 channel 同名，如：

- runtime
  - logs
    - app
      - app-2022-05-28.log
      - app-2022-05-29.log
    - sql
      - sql-2022-05-28.log
      - sql-2022-05-29.log

此模式比较适合开发和测试，按channel和日期分开日志，方便排查错误

#### MixMode

不同的channel会被记录到同一个目录下，目录名和文件名均为 channelMixed(可修改)，如：

- runtime
    - logs
        - channelMixed
            - channelMixed-2022-05-28.log
            - channelMixed-2022-05-29.log

此模式比较适合将日志文件写入到 elk 等其他集中日志管理的服务中，因为一般此种通过 agent 来收集日志的服务不太会兼容动态扩展的日志目录

在 log 文件中如何区分 channel？ 在记录的日志中有一列是 [channelName]

### formatter 介绍

formatter 结构化可以有效的方便日志的筛查和查看

#### ChannelFormatter

基本的通道格式

单行日志格式如下：

`[时间][请求的唯一标识][日志级别][客户端ip][当前登录的用户ID][路由path]: 日志内容`

#### ChannelMixedFormatter

mix mode 使用的格式，比 ChannelFormatter 多了一列 channelName

单行日志格式如下：

`[时间][请求的唯一标识][channelName][日志级别][客户端ip][当前登录的用户ID][路由path]: 日志内容`
