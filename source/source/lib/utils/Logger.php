<?php

namespace Tent\Utils;

class Logger implements LoggerInterface
{
    public static function deprecate(string $message): void
    {
        trigger_error($message, E_USER_DEPRECATED);
    }
}