<?php

namespace Tent\Utils;

interface LoggerInterface
{
    public function logDeprecation(string $message): void;
}