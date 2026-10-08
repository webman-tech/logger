<?php

namespace WebmanTech\Logger\Processors;

use WeakMap;

/**
 * Processor 结果的请求内缓存.
 *
 * 同一请求内 ip / route / traceId 等派生值恒定，逐条日志重复计算（如 getRealIp 的
 * header 解析与 filter_var 链）是纯浪费；以底层请求对象为 key 做 memoization，
 * 请求结束后对象被回收，WeakMap 自动释放，无内存泄漏，且天然适配多请求隔离场景。
 *
 * @internal
 */
final class ProcessorCache
{
    /**
     * @var WeakMap<object, array<string, mixed>>
     */
    private static ?WeakMap $cache = null;

    private function __construct()
    {
    }

    /**
     * 按请求对象缓存计算结果，同一对象同一 key 仅计算一次.
     */
    public static function remember(object $request, string $key, \Closure $fn): mixed
    {
        $cache = self::$cache ??= new WeakMap();
        $data = $cache[$request] ?? [];
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }
        $data[$key] = $fn();
        $cache[$request] = $data;
        return $data[$key];
    }
}
