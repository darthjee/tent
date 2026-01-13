<?php

namespace Tent;

class Configuration
{
    private static $rules = [];

    public static function addRule($rule)
    {
        self::$rules[] = $rule;
    }

    public static function getRules()
    {
        return array_merge(
            self::$rules,
            [new Rule(new MissingRequestHandler())]
        );
    }

    public static function reset()
    {
        self::$rules = [];
    }
}
