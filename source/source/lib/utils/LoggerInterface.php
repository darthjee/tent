<?php

namespace Tent\Utils;

interface LoggerInterface
{
    public static function deprecate(string $message): void;
}