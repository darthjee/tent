<?php

namespace Tent\Utils;

class Logger
{
    public static function deprecate(string $message): void
    {
        trigger_error($message, E_USER_DEPRECATED);
    }
}