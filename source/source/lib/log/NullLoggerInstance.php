<?php

namespace Tent\Log;

/**
 * Silent logger implementation used as a test double.
 * All log calls are discarded; enable() and disable() are no-ops.
 */
class NullLoggerInstance extends LoggerInstance
{
    /**
     * No-op: NullLoggerInstance is always silent.
     *
     * @return void
     */
    public function enable(): void
    {
    }

    /**
     * No-op: NullLoggerInstance is always silent.
     *
     * @return void
     */
    public function disable(): void
    {
    }

    /**
     * No-op: discards all messages.
     *
     * @param string $message Ignored.
     * @param string $level   Ignored.
     *
     * @return void
     */
    public function log(string $message, string $level): void
    {
    }
}
