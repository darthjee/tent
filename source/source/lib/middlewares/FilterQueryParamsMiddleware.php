<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;

/**
 * Middleware to filter the query string of a request against a configured list of
 * parameter names.
 *
 * Depending on the configured `mode`, the middleware either keeps only the listed
 * top-level query parameters (`allow`, the default) or removes them (`deny`). Any
 * other top-level parameters are left untouched (in `allow` mode they are dropped;
 * in `deny` mode they are kept). Parameters not present in the query string have no
 * effect. If the request has no query string, the request is left unchanged.
 *
 * ## Usage Example (in configuration)
 *
 * ```php
 * use Tent\Configuration;
 *
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
 *             'class' => 'Tent\\Middlewares\\FilterQueryParamsMiddleware',
 *             'params' => ['id', 'page'],
 *             'mode'   => 'allow'
 *         ]
 *     ]
 * ]);
 * ```
 *
 * The middleware can also be instantiated directly:
 *
 * ```php
 * $middleware = FilterQueryParamsMiddleware::build([
 *     'params' => ['id', 'page'],
 *     'mode'   => 'allow'
 * ]);
 * ```
 */
class FilterQueryParamsMiddleware extends Middleware
{
    /**
     * @var array List of top-level query parameter names to allow/deny.
     */
    private $params;

    /**
     * @var string Filtering mode: 'allow' or 'deny'.
     */
    private $mode;

    /**
     * @param array  $params The parameter names to allow/deny. Defaults to [].
     * @param string $mode   Either 'allow' or 'deny'. Defaults to 'allow'.
     */
    public function __construct(array $params = [], string $mode = 'allow')
    {
        $this->params = $params;
        $this->mode   = $mode;
    }

    /**
     * Builds a FilterQueryParamsMiddleware using named parameters.
     *
     * Example:
     *   FilterQueryParamsMiddleware::build(['params' => ['id', 'page'], 'mode' => 'allow'])
     *
     * @param array $attributes Associative array with optional keys 'params' (array of strings)
     *   and 'mode' ('allow' or 'deny').
     * @return self
     * @throws \InvalidArgumentException If 'mode' is set to a value other than 'allow' or 'deny'.
     */
    public static function build(array $attributes): self
    {
        $params = $attributes['params'] ?? [];
        $mode = $attributes['mode'] ?? 'allow';

        if (!in_array($mode, ['allow', 'deny'], true)) {
            throw new \InvalidArgumentException("Invalid mode '{$mode}', expected 'allow' or 'deny'");
        }

        return new self($params, $mode);
    }

    /**
     * Filters the query string of the ProcessingRequest against the configured params list.
     *
     * @param ProcessingRequest $request The request to process.
     * @return ProcessingRequest The modified request.
     */
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        $query = $request->query();

        if ($query === '') {
            return $request;
        }

        parse_str($query, $parsed);

        $keys = array_flip($this->params);

        $filtered = $this->mode === 'allow'
            ? array_intersect_key($parsed, $keys)
            : array_diff_key($parsed, $keys);

        $request->setQuery(http_build_query($filtered));

        return $request;
    }
}
