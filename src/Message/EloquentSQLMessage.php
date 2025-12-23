<?php

namespace WebmanTech\Logger\Message;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use WebmanTech\Logger\Helper\StringHelper;

/**
 * Eloquent SQL 日志
 */
class EloquentSQLMessage extends BaseMessage
{
    use TimeBasedMessageTrait;

    protected string $channel = 'sql';

    protected array $ignoreSql = [
        'select 1', // 心跳 SQL
    ]; // 忽略的 sql
    protected array $ignoreSqlPattern = []; // 正则忽略的 sql

    protected bool $logNotSelect = true; // 记录所有非 select 语句
    protected ?Closure $checkIsSqlNotSelect = null; // 判断 SQL 是否不是 select
    protected bool $bindSQLBindings = true; // 是否绑定 SQL 参数
    protected bool $showConnectionName = false; // 是否显示连接名称
    protected int $logMaxLength = 1000; // 日志 SQL 长度限制
    /** @phpstan-ignore-next-line */
    protected ?Closure $extraInfo = null; // 其他信息

    final public function appendIgnoreSql(string|array $sql): static
    {
        $this->ignoreSql = array_unique(array_merge($this->ignoreSql, (array)$sql));

        return $this;
    }

    final public function appendIgnoreSqlPattern(string|array $pattern): static
    {
        $this->ignoreSqlPattern = array_unique(array_merge($this->ignoreSqlPattern, (array)$pattern));

        return $this;
    }

    /**
     * 绑定一个连接
     */
    public function bindConnection(Connection $connection): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $connection->listen($this->handle(...));
    }

    /**
     * 处理一个 SQL 事件
     */
    public function handle(QueryExecuted $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $sql = $event->sql;
        $cost = intval(round($event->time));

        // 检查是否需要记录
        if (in_array($sql, $this->ignoreSql, true)) {
            return;
        }
        foreach ($this->ignoreSqlPattern as $pattern) {
            if (preg_match($pattern, $sql)) {
                return;
            }
        }

        // 检查 logLevel 是否需要记录
        $logLevel = $this->getLogLevelByTime($cost);
        if ($logLevel === null) {
            if (!($this->logNotSelect && $this->isSqlNotSelect($sql, $event))) {
                return;
            }
            $logLevel = 'info';
        }

        // sql 绑定参数
        if ($this->bindSQLBindings && $event->bindings) {
            $sql = $event->toRawSql();
        }

        $context = [
            'cost' => $cost,
        ];
        if ($this->showConnectionName) {
            $context['connectionName'] = $event->connectionName;
        }
        // 添加其他信息
        if ($value = $this->callClosure($this->extraInfo, $event)) {
            $context = array_merge($context, (array)$value);
        }

        $this->log($logLevel, StringHelper::limit($sql, $this->logMaxLength), $context);
    }

    protected function isSqlNotSelect(string $sql, QueryExecuted $event): bool
    {
        $value = $this->callClosure($this->checkIsSqlNotSelect, $sql, $event);
        if ($value !== null) {
            return $value;
        }

        return !!preg_match("/^\s*(update|delete|insert|replace|create|alter|drop|truncate)\s*/i", $sql);
    }

    private function callClosure(mixed $fn, mixed ...$args): mixed
    {
        if ($fn instanceof \Closure) {
            return ($fn)(...$args);
        }
        return null;
    }
}
