<?php

namespace Kriss\WebmanLogger\Formatter;

class ChannelMixedFormatter extends ChannelFormatter
{
    public function __construct()
    {
        parent::__construct('[%channel%]');
    }
}
