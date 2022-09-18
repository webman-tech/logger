<?php

namespace WebmanTech\Logger;

use InvalidArgumentException;
use WebmanTech\Logger\Mode\BaseMode;
use Monolog\Processor\ProcessorInterface;

class LogChannelManager
{
    /**
     * @var array
     */
    protected $config = [
        'channels' => [],
        'modes' => [],
        'levels' => [
            'default' => 'info',
            'special' => [],
        ],
        'processors' => [],
    ];

    public function __construct(array $config)
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 对所有渠道构建适用于 config/log.php 下渠道配置的参数
     * @return array
     */
    public function buildLogChannelConfigs(): array
    {
        $channelConfigs = [];
        foreach ($this->config['channels'] as $channel) {
            $handlers = [];
            foreach ($this->config['modes'] as $modeConfig) {
                $mode = $this->buildMode($modeConfig);
                if ($handler = $mode->getHandler($channel, $this->getLevel($channel))) {
                    $handlers[] = $handler;
                }
            }
            if (count($handlers) <= 0) {
                continue;
            }
            $channelConfigs[$channel] = [
                'handlers' => $handlers,
                'processors' => $this->buildProcessors(),
            ];
        }
        return $channelConfigs;
    }

    private array $_modes = [];

    /**
     * @param array $mode
     * @return BaseMode
     */
    protected function buildMode(array $mode): BaseMode
    {
        $class = $mode['class'];
        if (!isset($this->_modes[$class])) {
            $this->_modes[$class] = new $class($mode);
            if (!$this->_modes[$class] instanceof BaseMode) {
                throw new InvalidArgumentException('mode class 必须继承 BaseMode');
            }
        }

        return $this->_modes[$class];
    }

    /**
     * @return array
     */
    protected function buildProcessors(): array
    {
        $processors = $this->config['processors'];
        if (is_callable($processors)) {
            $processors = call_user_func($processors);
        }
        if (!is_array($processors)) {
            throw new InvalidArgumentException('processors 必须是数组或者 callable 返回数组');
        }
        foreach ($processors as $processor) {
            if (!$processor instanceof ProcessorInterface) {
                throw new InvalidArgumentException('processors 必须都是 ProcessorInterface 实例');
            }
        }

        return $processors;
    }

    /**
     * @param string $channel
     * @return string
     */
    protected function getLevel(string $channel): string
    {
        return $this->config['levels']['special'][$channel] ?? $this->config['levels']['default'];
    }
}