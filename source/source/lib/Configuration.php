<?php

namespace Tent;

/**
 * Configuration class for setting up server routing rules.
 *
 * This class is used to configure the server by adding Rule objects that define how requests are handled.
 * Rules are stored statically and can be retrieved or reset as needed.
 */
class Configuration
{
    /**
     * @var Rule[] List of rules added to the configuration.
     */
    private static $rules = [];

    /**
     * Adds a Rule to the configuration.
     *
     * @param Rule $rule The rule to add.
     * @return void
     */
    public static function addRule(Rule $rule)
    {
        self::$rules[] = $rule;
    }

    /**
     * Returns all configured rules, always including a fallback rule with MissingRequestHandler.
     *
     * @return Rule[] Array of rules for request processing.
     */
    public static function getRules()
    {
        return array_merge(
            self::$rules,
            [new Rule(new MissingRequestHandler())]
        );
    }

    /**
     * Resets the configuration, removing all rules.
     *
     * @return void
     */
    public static function reset()
    {
        self::$rules = [];
    }
}
