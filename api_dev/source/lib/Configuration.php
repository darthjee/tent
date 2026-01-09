<?php

namespace ApiDev;

class Configuration
{
    private static $instance;
    private $configurations = [];

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Configuration();
        }
        return self::$instance;
    }

    public static function add($requestMethod, $path, $endpoint)
    {
        return self::getInstance()->addRoute($requestMethod, $path, $endpoint);
    }

    public static function getConfigurations()
    {
        return self::getInstance()->fetchConfigurations();
    }

    public static function reset()
    {
        self::$instance = null;
    }

    public function addRoute($requestMethod, $path, $endpoint)
    {
        $this->configurations[] = new RouteConfiguration($requestMethod, $path, $endpoint);
    }

    public function fetchConfigurations()
    {
        return $this->configurations;
    }
}
