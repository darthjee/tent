<?php

namespace Tent\Utils;

class StringUtils
{
    public static function toStudlyCase(string $value): string
    {
        $parts = preg_split('/[^a-z0-9]+/i', $value, -1, PREG_SPLIT_NO_EMPTY);
        return implode('', array_map('ucfirst', array_map('strtolower', $parts)));
    }
}
