<?php

namespace WebmanTech\Logger\Formatter;

use Monolog\Formatter\LineFormatter;

class ChannelFormatter extends LineFormatter
{
    public function __construct(string $channel = '')
    {
        $format = "[%datetime%][%extra.traceId%]{$channel}[%level_name%][%extra.ip%][%extra.userId%][%extra.route%]: %message% %context% %extra%\n";

        parent::__construct($format, 'Y-m-d H:i:s.u', true, true, true);
    }
}
