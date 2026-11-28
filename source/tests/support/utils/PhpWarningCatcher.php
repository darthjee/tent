<?php

namespace Tent\Tests\Support\Utils;

/**
 * Records the PHP warnings and notices raised while it is active, so that specs can
 * assert that none was raised without relying on how PHPUnit treats them.
 *
 * Errors silenced with the "@" operator are not recorded. They are left to PHP, so
 * that error_get_last() is still populated.
 */
class PhpWarningCatcher
{
    private array $warnings = [];

    private bool $active = false;

    /**
     * Installs the capturing error handler.
     */
    public function start(): void
    {
        set_error_handler([$this, 'handle'], E_ALL);
        $this->active = true;
    }

    /**
     * Restores the previous error handler. Does nothing when not started.
     */
    public function stop(): void
    {
        if (!$this->active) {
            return;
        }

        restore_error_handler();
        $this->active = false;
    }

    /**
     * @return array<int, array{0: int, 1: string}> The captured [errno, message] pairs.
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Error handler callback.
     *
     * @return boolean False for silenced errors, so that PHP handles them; true otherwise.
     */
    public function handle(int $errno, string $message): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $this->warnings[] = [$errno, $message];

        return true;
    }
}
