<?php

namespace Tent\Utils;

class Logger implements LoggerInterface
{
    static private $instance;

    public static function deprecate(string $message): void
    {
        self::getInstance()->logDeprecation($message);
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

    public function logDeprecation(string $message): void
    {
        trigger_error($message, E_USER_DEPRECATED);
    }
}