<?php

namespace Tent\Tests\Support\Log;

use Tent\Log\LoggerInstance;

class CapturingLoggerInstance extends LoggerInstance
{
    public array $lines = [];

    public function log(string $message, string $level): void
    {
        $this->lines[] = [$level, $message];
    }
}
