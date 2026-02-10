<?php

use ApiDev\Request;
use ApiDev\RequestHandler;
use ApiDev\Configuration;
use ApiDev\HealthCheckEndpoint;
use ApiDev\ListPersonsEndpoint;
use ApiDev\CreatePersonEndpoint;

require_once __DIR__ . '/loader.php';

Configuration::add('GET', '/health', HealthCheckEndpoint::class);
Configuration::add('GET', '/persons', ListPersonsEndpoint::class);

\ApiDev\Mysql\Configuration::configure(
    getenv('API_DEV_MYSQL_HOST') ?: 'localhost',
    getenv('API_DEV_MYSQL_DEV_DATABASE') ?: 'api_tent_dev_db',
    getenv('API_DEV_MYSQL_USER') ?: 'root',
    getenv('API_DEV_MYSQL_PASSWORD') ?: '',
    getenv('API_DEV_MYSQL_PORT') ?: 3306
);

$request = new Request();
$handler = new RequestHandler();
$handler->handle($request);
