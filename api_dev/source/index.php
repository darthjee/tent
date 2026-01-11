<?php

use ApiDev\Request;
use ApiDev\RequestHandler;
use ApiDev\Configuration;
use ApiDev\HealthCheckEndpoint;
use ApiDev\ListPersonsEndpoint;

require_once __DIR__ . '/lib/api_dev/models/Request.php';
require_once __DIR__ . '/lib/api_dev/models/Response.php';
require_once __DIR__ . '/lib/api_dev/models/MissingResponse.php';
require_once __DIR__ . '/lib/api_dev/Route.php';
require_once __DIR__ . '/lib/api_dev/Endpoint.php';
require_once __DIR__ . '/lib/api_dev/RouteConfiguration.php';
require_once __DIR__ . '/lib/api_dev/Configuration.php';
require_once __DIR__ . '/lib/api_dev/RequestHandler.php';

require_once __DIR__ . '/lib/api_dev/endpoints/HealthCheckEndpoint.php';
require_once __DIR__ . '/lib/api_dev/endpoints/ListPersonsEndpoint.php';

require_once __DIR__ . '/lib/mysql/Configuration.php';

Configuration::add('GET', '/health', HealthCheckEndpoint::class);
Configuration::add('GET', '/persons', ListPersonsEndpoint::class);

\ApiDev\Mysql\Configuration::configure(
    getenv('API_DEV_MYSQL_HOST') ?: 'localhost',
    getenv('API_DEV_MYSQL_TEST_DATABASE') ?: 'api_tent_test_db',
    getenv('API_DEV_MYSQL_USER') ?: 'root',
    getenv('API_DEV_MYSQL_PASSWORD') ?: '',
    getenv('API_DEV_MYSQL_PORT') ?: 3306
);

$request = new Request();
$handler = new RequestHandler();
$handler->handle($request);
