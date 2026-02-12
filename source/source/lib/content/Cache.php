<?php

namespace Tent\Content;

use Tent\Models\Response;

/**
 * Interface for cache sources that can both read and store response data.
 *
 * Cache implementations serve a dual purpose:
 * - As a ResponseContent, they allow reading cached content, headers, and status code to generate a Response.
 * - As a cache, they provide a store() method to save a Response into the cache.
 *
 * This abstraction allows Tent to treat cache sources uniformly for reading and writing.
 *
 * ## Example: RedisCache (hypothetical)
 *
 * ```php
 * class RedisCache implements Cache
 * {
 *     private $redis;
 *     private $key;
 *
 *     public function __construct($redis, $key)
 *     {
 *         $this->redis = $redis;
 *         $this->key = $key;
 *     }
 *
 *     public function content(): string
 *     {
 *         return $this->redis->get($this->key . ':body');
 *     }
 *
 *     public function headers(): array
 *     {
 *         $raw = $this->redis->get($this->key . ':headers');
 *         return $raw ? json_decode($raw, true) : [];
 *     }
 *
 *     public function httpCode(): int
 *     {
 *         $code = $this->redis->get($this->key . ':httpCode');
 *         return $code ? (int)$code : 200;
 *     }
 *
 *     public function exists(): bool
 *     {
 *         return $this->redis->exists($this->key . ':body');
 *     }
 *
 *     public function store(Response $response): void
 *     {
 *         $this->redis->set($this->key . ':body', $response->body());
 *         $this->redis->set($this->key . ':headers', json_encode($response->headers()));
 *         $this->redis->set($this->key . ':httpCode', $response->httpCode());
 *     }
 * }
 * ```
 */
interface Cache extends ResponseContent
{
    /**
     * Stores the response in the cache.
     *
     * @param Response $response The response to store.
     * @return void
     */
    public function store(Response $response): void;
}
