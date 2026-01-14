<?php

namespace Tent;

/**
 * Model representing the base address for proxy requests.
 *
 * Used by ProxyHandler to define the target host for forwarding requests.
 * Similar to FolderLocation, this is a simple value object.
 */
class Server
{
    /**
     * @var string The base address (host) for proxy requests.
     */
    private $targetHost;

    /**
     * Constructs a Server model.
     *
     * @param string $targetHost The base address for proxy requests.
     */
    public function __construct($targetHost)
    {
        $this->targetHost = $targetHost;
    }

    /**
     * Returns the base address (host) for proxy requests.
     *
     * @return string
     */
    public function targetHost()
    {
        return $this->targetHost;
    }
}
