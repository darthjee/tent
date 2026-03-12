<?php

namespace Tent;

use Tent\RequestHandlers\MissingRequestHandler;
use Tent\Models\Rule;

/**
 * Configuration class for setting up server routing rules.
 *
 * This class is used to configure the server by adding Rule objects that define how requests are handled.
 * Rules are stored statically and can be retrieved or reset as needed.
 *
 * @example Basic proxy configuration:
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'proxy',
 *         'host' => 'http://api:80'
 *     ],
 *     'matchers' => [
 *         ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
 *     ]
 * ]);
 * ```
 *
 * @example Proxy with caching and custom headers sent to backend:
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'proxy',
 *         'host' => 'http://api:80'
 *     ],
 *     'matchers' => [
 *         ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
 *     ],
 *     'middlewares' => [
 *         [
 *             'class' => 'Tent\Middlewares\FileCacheMiddleware',
 *             'location' => './cache',
 *             'httpCodes' => [200]
 *         ],
 *         [
 *             'class' => 'Tent\Middlewares\SetHeadersMiddleware',
 *             'headers' => ['Host' => 'backend.local']
 *         ]
 *     ]
 * ]);
 * ```
 *
 * @example Static file serving:
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'static',
 *         'location' => '/var/www/html/static'
 *     ],
 *     'matchers' => [
 *         ['method' => 'GET', 'uri' => '/assets', 'type' => 'begins_with']
 *     ]
 * ]);
 * ```
 *
 * @example Static file with static path rewriting:
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'static',
 *         'location' => '/var/www/html/static/'
 *     ],
 *     'matchers' => [
 *         ['method' => 'GET', 'uri' => '/', 'type' => 'exact']
 *     ],
 *     'middlewares' => [
 *         [
 *             'class' => 'Tent\Middlewares\SetPathMiddleware',
 *             'path' => '/index.html'
 *         ]
 *     ]
 * ]);
 * ```
 *
 * @example Multiple matchers for one handler:
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'proxy',
 *         'host' => 'http://frontend:8080'
 *     ],
 *     'matchers' => [
 *         ['method' => 'GET', 'uri' => '/', 'type' => 'exact'],
 *         ['method' => 'GET', 'uri' => '/assets/js/', 'type' => 'begins_with'],
 *         ['method' => 'GET', 'uri' => '/assets/css/', 'type' => 'begins_with']
 *     ]
 * ]);
 * ```
 *
 * @note
 *   When defining middlewares or other components in the params array for buildRule
 *   you can specify the class to use via the 'class' key:
 *   Example:
 *   ```php
 *   'middlewares' => [
 *     [
 *       'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
 *       'location' => './cache',
 *       'httpCodes' => [200]
 *     ]
 *   ]
 *   ```
 *   The 'class' key is required to indicate which middleware or handler implementation should be used.
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
     * @return Rule
     */
    public static function addRule(Rule $rule): Rule
    {
        self::$rules[] = $rule;
        return $rule;
    }

    /**
     * Builds a Rule from an array of parameters (proxy for Rule::build).
     *
     * @param array $params Array of parameters for Rule::build.
     * @return Rule
     */
    public static function buildRule(array $params): Rule
    {
        return self::addRule(Rule::build($params));
    }

    /**
     * Adds a matcher to an existing rule identified by name.
     *
     * Locates the rule with the given name and adds the specified matcher to it.
     *
     * @example
     * ```php
     * Configuration::addMatcher([
     *     'rule'    => 'api-persons',
     *     'matcher' => ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact'],
     * ]);
     * ```
     *
     * @param array $params Associative array with keys:
     *   - 'rule'   (string): Name of the existing rule to add the matcher to.
     *   - 'matcher' (array):  Matcher parameters accepted by RequestMatcher::build
     *                         (keys: 'method', 'uri', 'type').
     * @return void
     * @throws \InvalidArgumentException If 'rule' or 'matcher' keys are missing,
     *                                   'matcher' is not an array, or the named rule
     *                                   does not exist.
     */
    public static function addMatcher(array $params): void
    {
        if (!isset($params['rule']) || !is_string($params['rule'])) {
            throw new \InvalidArgumentException(
                "Configuration::addMatcher requires a 'rule' key with a string value."
            );
        }

        if (!isset($params['matcher']) || !is_array($params['matcher'])) {
            throw new \InvalidArgumentException(
                "Configuration::addMatcher requires a 'matcher' key with an array value."
            );
        }

        $rule = self::getRule($params['rule']);

        if ($rule === null) {
            throw new \InvalidArgumentException(
                sprintf("Rule '%s' not found.", $params['rule'])
            );
        }

        $rule->addMatcher($params['matcher']);
    }

    /**
     * Returns the first Rule with the given name, or null if not found.
     *
     * @param string $name The name of the rule to find.
     * @return Rule|null
     */
    public static function getRule(string $name): ?Rule
    {
        foreach (self::getRules() as $rule) {
            if (method_exists($rule, 'name') && $rule->name() === $name) {
                return $rule;
            }
        }
        return null;
    }

    /**
     * Returns all configured rules, always including a fallback rule with MissingRequestHandler.
     *
     * @return Rule[] Array of rules for request processing.
     */
    public static function getRules(): array
    {
        return array_merge(
            self::$rules,
            [new Rule(['handler' => new MissingRequestHandler()])]
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
