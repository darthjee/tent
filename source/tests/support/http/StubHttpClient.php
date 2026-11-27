<?php

namespace Tent\Tests\Support\Http;

use Tent\Http\HttpClientInterface;

class StubHttpClient implements HttpClientInterface
{
    private array $result;

    public function __construct(array $result)
    {
        $this->result = $result;
    }

    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body = null,
        array $uploadedFiles = [],
        array $postFields = []
    ): array {
        return $this->result;
    }
}
