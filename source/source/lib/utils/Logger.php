<?php

namespace Tent\Utils;

class Logger
{
    static private $instance;

    public static function deprecate(string $message): void
    {
        trigger_error($message, E_USER_DEPRECATED);
    }

    public static function getInstance(): LoggerInterface
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(LoggerInterface $logger): void
    {
        self::$instance = $logger;
    }
}