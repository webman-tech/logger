<?php

use Monolog\Processor\PsrLogMessageProcessor;
use WebmanTech\Logger\Formatter\ChannelFormatter;
use WebmanTech\Logger\Formatter\ChannelMixedFormatter;
use WebmanTech\Logger\Mode;
use WebmanTech\Logger\Processors;

return [
    // channels
    'channels' => [
        //'channelName',
    ],
    // 记录等级，仅大于设定等级的日志才会真实写入日志文件
    'levels' => [
        // 默认等级
        'default' => config('app.debug') ? 'debug' : 'info',
        // 特殊的等级
        'special' => [
            //'channelName' => 'info',
        ],
    ],
    // processors
    'processors' => function () {
        return [
            new PsrLogMessageProcessor('Y-m-d H:i:s', true),
            new Processors\RequestRouteProcessor(),
            new Processors\RequestIpProcessor(),
            new Processors\AuthUserIdProcessor(),
            new Processors\RequestTraceProcessor(),
        ];
    },
    // 模式
    'modes' => [
        // 按照channel分目录记录
        'split' => [
            'class' => Mode\SplitMode::class,
            'enable' => true,
            'except_channels' => [],
            'only_channels' => [],
            'formatter' => [
                'class' => ChannelFormatter::class,
            ],
            'max_files' => 30, // 最大文件数
        ],
        // 将所有channel合并到一起记录
        'mix' => [
            'class' => Mode\MixMode::class,
            'enable' => false,
            'except_channels' => [],
            'only_channels' => [],
            'formatter' => [
                'class' => ChannelMixedFormatter::class,
            ],
            'max_files' => 30, // 最大文件数
            'name' => 'channelMixed', // 合并时的日志文件名
        ],
        // 控制台输出
        'stdout' => [
            'class' => Mode\StdoutMode::class,
            'enable' => false,
            'except_channels' => [],
            'only_channels' => [],
            'formatter' => [
                'class' => ChannelMixedFormatter::class,
            ],
        ],
        // 输出到 redis
        'redis' => [
            'class' => Mode\RedisMode::class,
            'enable' => false,
            'except_channels' => [],
            'only_channels' => [],
            'formatter' => [
                'class' => ChannelFormatter::class,
            ],
            'redis' => function () {
                return support\Redis::connection('default')->client();
            },
            'redis_key_prefix' => 'webmanLog:',
            'redis_size' => 0,
        ],
    ],
];
