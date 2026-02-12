<?php

namespace Tent\Utils;

interface LoggerInterface
{
    /**
     * Logs a deprecation message.
     *
     * @param string $message The deprecation message to log.
     * @return void
     */
    public function logDeprecation(string $message): void;
}
