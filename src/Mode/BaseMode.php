<?php

namespace WebmanTech\Logger\Mode;

use InvalidArgumentException;
use Monolog\Formatter\LineFormatter;

abstract class BaseMode
{
    /**
     * 通用配置
     * @var array
     */
    protected array $commonConfig = [
        'enable' => false,
        'except_channels' => [], // 排除部分 channel
        'only_channels' => [], // 仅包含部分 channel，为空时无效
        'formatter' => [
            'class' => LineFormatter::class,
            'constructor' => [],
        ],
    ];
    /**
     * 模式配置
     * @var array
     */
    protected array $config = [];

    final public function __construct(array $config = [])
    {
        $this->commonConfig = array_replace($this->commonConfig, $config);
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取 Handler
     * @param string $channelName
     * @param string $level
     * @return array
     */
    abstract public function getHandler(string $channelName, string $level): array;

    /**
     * 检查该模式对该 channel 是否启用
     * @param string $channelName
     * @return bool
     */
    protected function checkHandlerUsefulForChannel(string $channelName): bool
    {
        if (!$this->commonConfig['enable']) {
            return false;
        }
        if (in_array($channelName, $this->commonConfig['except_channels'])) {
            return false;
        }
        if (count($this->commonConfig['only_channels']) > 0 && !in_array($channelName, $this->commonConfig['only_channels'])) {
            return false;
        }

        return true;
    }

    /**
     * handler 的 formatter
     * @return array
     */
    protected function getFormatter(): array
    {
        // support/Log::handler() $formatterConfig 必须 class 和 constructor
        $formatter = $this->commonConfig['formatter'];
        if (!isset($formatter['class'])) {
            throw new InvalidArgumentException('formatter 必须 class 参数');
        }
        $formatter['constructor'] = $formatter['constructor'] ?? [];
        return $formatter;
    }
}