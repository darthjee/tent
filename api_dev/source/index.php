<?php

use ApiDev\Request;
use ApiDev\RequestHandler;
use ApiDev\Configuration;
use ApiDev\HealthCheckEndpoint;

require_once __DIR__ . '/lib/models/Request.php';
require_once __DIR__ . '/lib/models/Response.php';
require_once __DIR__ . '/lib/models/MissingResponse.php';
require_once __DIR__ . '/lib/Route.php';
require_once __DIR__ . '/lib/Endpoint.php';
require_once __DIR__ . '/lib/endpoints/HealthCheckEndpoint.php';
require_once __DIR__ . '/lib/RouteConfiguration.php';
require_once __DIR__ . '/lib/Configuration.php';
require_once __DIR__ . '/lib/RequestHandler.php';

Configuration::add('GET', '/health', HealthCheckEndpoint::class);

$request = new Request();
$handler = new RequestHandler();
$handler->handle($request);
