<?php

namespace ApiDev;

class Configuration
{
    private $configurations = [];

    public function add($requestMethod, $path, $endpoint)
    {
        $this->configurations[] = new RouteConfiguration($requestMethod, $path, $endpoint);
    }

    public function getConfigurations()
    {
        return $this->configurations;
    }
}
