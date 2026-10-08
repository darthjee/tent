<?php

namespace Tent\Service;

use Tent\Models\Response;

/**
 * Service for sending a Response to the client.
 *
 * Emits the HTTP status code, every header line and the body. Header lines are
 * emitted with `replace = false` so that repeated headers sharing the same name
 * (e.g. several `Set-Cookie` lines) all reach the client instead of the last one
 * overwriting the others.
 *
 * The header and status code emitters are injectable so what gets emitted can be
 * observed in tests, where PHP's `header()` has no visible effect and
 * `http_response_code()` fails once output has started.
 */
class ResponseSender
{
    /**
     * @var callable Callable receiving `(string $line, bool $replace)` that emits a header.
     */
    private $headerEmitter;

    /**
     * @var callable Callable receiving `(int $code)` that emits the HTTP status code.
     */
    private $statusEmitter;

    /**
     * Constructs a ResponseSender instance.
     *
     * @param callable|null $headerEmitter Callable receiving `(string $line, bool $replace)`.
     *                                     Defaults to PHP's `header()`.
     * @param callable|null $statusEmitter Callable receiving `(int $code)`.
     *                                     Defaults to PHP's `http_response_code()`.
     */
    public function __construct(?callable $headerEmitter = null, ?callable $statusEmitter = null)
    {
        $this->headerEmitter = $headerEmitter ?? function (string $line, bool $replace): void {
            header($line, $replace);
        };
        $this->statusEmitter = $statusEmitter ?? function (int $code): void {
            http_response_code($code);
        };
    }

    /**
     * Sends the response status code, headers and body to the client.
     *
     * @param Response $response The response to send.
     * @return void
     */
    public function send(Response $response): void
    {
        ($this->statusEmitter)($response->httpCode());
        foreach ($response->headers() as $header) {
            ($this->headerEmitter)($header, false);
        }
        echo $response->body();
    }
}
