<?php

namespace Tent\Middlewares;

use Tent\Models\ProcessingRequest;

/**
 * Middleware to set or override headers in a ProcessingRequest.
 */
class SetHeadersMiddleware implements RequestMiddleware
{
    /**
     * @var array<string, string> Headers to set
     */
    private $headers;

    /**
     * @param array<string, string> $headers Associative array of headers to set (e.g., ['Host' => 'some_host'])
     */
    public function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * Sets or overrides headers in the ProcessingRequest.
     *
     * @param ProcessingRequest $request
     * @return ProcessingRequest The modified request
     */
    public function process(ProcessingRequest $request): ProcessingRequest
    {
        foreach ($this->headers as $name => $value) {
            $request->setHeader($name, $value);
        }
        return $request;
    }
}
