<?php

namespace Tent\Tests\Support\Cache;

use Tent\Cache\RequestHasher;
use Tent\Models\RequestInterface;

/**
 * Test-only RequestHasher used to verify `request_hasher` config wiring.
 *
 * Produces a fixed, easily recognizable hash, optionally configurable via
 * the `hash` build parameter.
 */
class DummyRequestHasher implements RequestHasher
{
    private string $hash;

    public function __construct(string $hash = 'dummy-hash')
    {
        $this->hash = $hash;
    }

    public function hash(RequestInterface $request): string
    {
        return $this->hash;
    }

    public static function build(array $params): self
    {
        return new self($params['hash'] ?? 'dummy-hash');
    }
}
