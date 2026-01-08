<?php

namespace Tent;

class Server
{
    private $targetHost;

    public function __construct($targetHost)
    {
        $this->targetHost = $targetHost;
    }

    public function targetHost()
    {
        return $this->targetHost;
    }
}
