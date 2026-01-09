<?php

namespace ApiDev\Mysql;

class Configuration
{
    private static $instance;
    private $host;
    private $database;
    private $username;
    private $password;
    private $port;

    public static function getInstance()
    {
        return self::$instance;
    }

    public static function configure($host, $database, $username, $password, $port = 3306)
    {
        self::$instance = new Configuration($host, $database, $username, $password, $port);
        return self::$instance;
    }

    public function __construct($host, $database, $username, $password, $port = 3306)
    {
        $this->host = $host;
        $this->database = $database;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getDatabase()
    {
        return $this->database;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getPort()
    {
        return $this->port;
    }
}
