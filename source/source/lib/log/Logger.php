<?php

namespace Tent\Log;

/**
 * Static facade for logging.
 *
 * All production code should call Logger::debug(), Logger::info(), etc.
 * Tests can swap the underlying instance via Logger::setInstance() or
 * temporarily silence output via Logger::disable() / Logger::enable().
 */
class Logger
{
    /**
     * @var LoggerInstance|null The active logger instance.
     */
    private static ?LoggerInstance $instance = null;

    /**
     * Returns the active instance, creating a default one on first use.
     *
     * @return LoggerInstance
     */
    private static function getInstance(): LoggerInstance
    {
        if (self::$instance === null) {
            self::$instance = new LoggerInstance();
        }

        return self::$instance;
    }

    /**
     * Replaces the active logger instance. Used in tests.
     *
     * @param LoggerInstance $instance The instance to use for subsequent calls.
     *
     * @return void
     */
    public static function setInstance(LoggerInstance $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Enables log output on the active instance.
     *
     * @return void
     */
    public static function enable(): void
    {
        self::getInstance()->enable();
    }

    /**
     * Disables all log output on the active instance.
     *
     * @return void
     */
    public static function disable(): void
    {
        self::getInstance()->disable();
    }

    /**
     * Logs a debug-level message.
     *
     * @param string $message The message to log.
     *
     * @return void
     */
    public static function debug(string $message): void
    {
        self::getInstance()->log($message, 'debug');
    }

    /**
     * Logs an info-level message.
     *
     * @param string $message The message to log.
     *
     * @return void
     */
    public static function info(string $message): void
    {
        self::getInstance()->log($message, 'info');
    }

    /**
     * Logs a warn-level message.
     *
     * @param string $message The message to log.
     *
     * @return void
     */
    public static function warn(string $message): void
    {
        self::getInstance()->log($message, 'warn');
    }

    /**
     * Logs an error-level message.
     *
     * @param string $message The message to log.
     *
     * @return void
     */
    public static function error(string $message): void
    {
        self::getInstance()->log($message, 'error');
    }
}
