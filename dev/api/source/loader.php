<?php

require_once __DIR__ . '/lib/api_dev/models/Response.php';

require_once __DIR__ . '/lib/api_dev/Configuration.php';
require_once __DIR__ . '/lib/api_dev/Endpoint.php';
require_once __DIR__ . '/lib/api_dev/endpoints/HealthCheckEndpoint.php';
require_once __DIR__ . '/lib/api_dev/endpoints/ListPersonsEndpoint.php';
require_once __DIR__ . '/lib/api_dev/endpoints/CreatePersonEndpoint.php';
require_once __DIR__ . '/lib/api_dev/models/MissingResponse.php';
require_once __DIR__ . '/lib/api_dev/models/Person.php';
require_once __DIR__ . '/lib/api_dev/models/Request.php';
require_once __DIR__ . '/lib/api_dev/RequestHandler.php';
require_once __DIR__ . '/lib/api_dev/Route.php';
require_once __DIR__ . '/lib/api_dev/RouteConfiguration.php';
require_once __DIR__ . '/lib/mysql/Configuration.php';
require_once __DIR__ . '/lib/mysql/Connection.php';
require_once __DIR__ . '/lib/mysql/ModelConnection.php';