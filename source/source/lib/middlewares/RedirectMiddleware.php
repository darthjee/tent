<?php

namespace Tent\Middlewares;

use InvalidArgumentException;
use Tent\Models\ProcessingRequest;
use Tent\Models\Response;

class RedirectMiddleware extends Middleware
{
    private string $pattern;
    private string $replacement;

    /**
     * @param string $pattern     Regex pattern used to match request path.
     * @param string $replacement Replacement for matched request path.
     */
    public function __construct(string $pattern, string $replacement)
    {
        $this->pattern = $pattern;
        $this->replacement = $replacement;
    }

    /**
     * Builds a RedirectMiddleware from attributes.
     *
     * @param array $attributes Middleware attributes.
     * @return RedirectMiddleware
     */
    public static function build(array $attributes): self
    {
        if (!array_key_exists('pattern', $attributes)) {
            throw new InvalidArgumentException('Missing required redirect pattern.');
        }

        if (!array_key_exists('replacement', $attributes)) {
            throw new InvalidArgumentException('Missing required redirect replacement.');
        }

        $pattern = $attributes['pattern'];

        if (!is_string($pattern) || $pattern === '') {
            throw new InvalidArgumentException('Redirect pattern must be a non-empty string.');
        }

        if (@preg_match($pattern, '') === false) {
            throw new InvalidArgumentException(sprintf("Invalid redirect pattern '%s'.", $pattern));
        }

        return new self($pattern, strval($attributes['replacement']));
    }

    /**
     * Rewrites path and sets a 302 response when pattern matches.
     *
     * @param ProcessingRequest $request Incoming request.
     * @return ProcessingRequest
     */
    public function processRequest(ProcessingRequest $request): ProcessingRequest
    {
        $redirectPath = preg_replace($this->pattern, $this->replacement, $request->requestPath());

        if ($redirectPath === null || $redirectPath === $request->requestPath()) {
            return $request;
        }

        $location = $redirectPath;
        if ($request->query() !== '') {
            $location .= '?' . $request->query();
        }

        $request->setResponse(new Response([
            'body' => '',
            'httpCode' => 302,
            'headers' => ['Location: ' . str_replace(["\r", "\n"], '', $location)],
            'request' => $request,
        ]));

        return $request;
    }
}
