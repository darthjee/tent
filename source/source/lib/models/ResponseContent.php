<?php

namespace Tent\Models;

/**
 * Interface representing a data source for generating a Response.
 *
 * Implementations of ResponseContent provide a way to read content, headers,
 * and status code from various sources (such as static files, cache, etc.)
 * to produce a Response object for Tent.
 *
 * This abstraction allows Tent to treat different sources (file, cache, etc.)
 * uniformly when building HTTP responses.
 *
 * ## Example: Reading from Redis (hypothetical)
 *
 * ```php
 * class RedisResponseContent implements ResponseContent
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
 *         // Hypothetical: fetch body from Redis
 *         return $this->redis->get($this->key . ':body');
 *     }
 *
 *     public function headers(): array
 *     {
 *         // Hypothetical: fetch headers from Redis
 *         $raw = $this->redis->get($this->key . ':headers');
 *         return $raw ? json_decode($raw, true) : [];
 *     }
 *
 *     public function httpCode(): int
 *     {
 *         // Hypothetical: fetch status code from Redis
 *         $code = $this->redis->get($this->key . ':httpCode');
 *         return $code ? (int)$code : 200;
 *     }
 *
 *     public function exists(): bool
 *     {
 *         // Hypothetical: check if body exists in Redis
 *         return $this->redis->exists($this->key . ':body');
 *     }
 * }
 * ```
 */
interface ResponseContent
{
    /**
     * Returns the content of the response.
     *
     * @return string
     */
    public function content(): string;

    /**
     * Returns the headers associated with the response content.
     *
     * @return array
     */
    public function headers(): array;

    /**
     * Returns the HTTP status code for the response content.
     *
     * @return integer
     */
    public function httpCode(): int;

    /**
     * Checks if the content exists.
     *
     * @return boolean
     */
    public function exists(): bool;
}
