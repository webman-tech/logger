<?php

namespace Kriss\WebmanLogger\Formatter;

use Monolog\Formatter\LineFormatter;

class ChannelFormatter extends LineFormatter
{
    public function __construct(string $channel = '')
    {
        $format = $format ?? "[%datetime%][%extra.uid%]{$channel}[%level_name%][%context.ip%][%context.userId%][%context.route%]: %message%\n";

        parent::__construct($format, 'Y-m-d H:i:s.u', true, true, true);
    }
}
