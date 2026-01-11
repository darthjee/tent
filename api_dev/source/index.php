<?php

use ApiDev\Request;
use ApiDev\RequestHandler;
use ApiDev\Configuration;
use ApiDev\HealthCheckEndpoint;

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

Configuration::add('GET', '/health', HealthCheckEndpoint::class);
Configuration::add('GET', '/persons', ListPersonsEndpoint::class);

$request = new Request();
$handler = new RequestHandler();
$handler->handle($request);
