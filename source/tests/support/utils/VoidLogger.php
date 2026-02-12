<?php

namespace Tent\Tests\Support\Utils;

use Tent\Utils\LoggerInterface;

class VoidLogger implements LoggerInterface
{
    public function logDeprecation(string $message): void
    {
        // Do nothing
    }
}