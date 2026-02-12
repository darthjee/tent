<?php

namespace Tent\Utils;

class Logger implements LoggerInterface
{
    static private $instance;

    /**
     * Logs a deprecation message.
     *
     * @param string $message The deprecation message to log.
     */
    public static function deprecate(string $message): void
    {
        self::getInstance()->logDeprecation($message);
    }

    /**
     * Gets the singleton instance of the Logger.
     *
     * @return LoggerInterface The singleton Logger instance.
     */
    public static function getInstance(): LoggerInterface
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Sets the Logger instance to use for logging.
     *
     * @param LoggerInterface $logger The Logger instance to use.
     */
    public static function setInstance(LoggerInterface $logger): void
    {
        self::$instance = $logger;
    }

    /**
     * Logs a deprecation message using trigger_error.
     *
     * @param string $message The deprecation message to log.
     */
    public function logDeprecation(string $message): void
    {
        trigger_error($message, E_USER_DEPRECATED);
    }
}