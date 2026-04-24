<?php

namespace Tent\Log;

/**
 * Default logger implementation that reads LOG_LEVEL from the environment
 * and writes messages to stdout when their level meets or exceeds the threshold.
 *
 * Level precedence (ascending): debug < info < warn < error.
 */
class LoggerInstance
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warn' => 2, 'error' => 3];

    /**
     * @var int Minimum level index required to write a message.
     */
    private int $threshold;

    /**
     * @var bool When false, all output is suppressed regardless of level.
     */
    private bool $enabled = true;

    /**
     * Reads LOG_LEVEL from the environment and sets the threshold.
     */
    public function __construct()
    {
        $env = getenv('LOG_LEVEL') ?: 'debug';
        $this->threshold = self::LEVELS[$env] ?? 0;
    }

    /**
     * Enables log output (restores level-based filtering).
     *
     * @return void
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disables all log output regardless of level.
     *
     * @return void
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Writes $message to stdout if enabled and $level meets the threshold.
     *
     * @param string $message The message to log.
     * @param string $level   One of 'debug', 'info', 'warn', 'error'.
     *
     * @return void
     */
    public function log(string $message, string $level): void
    {
        if (!$this->enabled) {
            return;
        }

        $levelIndex = self::LEVELS[$level] ?? 0;

        if ($levelIndex < $this->threshold) {
            return;
        }

        echo '[' . strtoupper($level) . '] ' . $message . PHP_EOL;
    }
}
