<?php

use Tent\Request;
use Tent\RequestProcessor;

require_once __DIR__ . '/lib/handlers/RequestHandler.php';
require_once __DIR__ . '/lib/http/HttpClientInterface.php';
require_once __DIR__ . '/lib/models/Response.php';

require_once __DIR__ . '/lib/Configuration.php';
require_once __DIR__ . '/lib/handlers/FileHandler.php';
require_once __DIR__ . '/lib/handlers/FixedFileHandler.php';
require_once __DIR__ . '/lib/handlers/MissingRequestHandler.php';
require_once __DIR__ . '/lib/handlers/ProxyRequestHandler.php';
require_once __DIR__ . '/lib/handlers/StaticFileHandler.php';
require_once __DIR__ . '/lib/http/CurlHttpClient.php';
require_once __DIR__ . '/lib/models/FolderLocation.php';
require_once __DIR__ . '/lib/models/MissingResponse.php';
require_once __DIR__ . '/lib/models/Request.php';
require_once __DIR__ . '/lib/models/RequestMatcher.php';
require_once __DIR__ . '/lib/models/Rule.php';
require_once __DIR__ . '/lib/models/Server.php';
require_once __DIR__ . '/lib/service/RequestProcessor.php';
require_once __DIR__ . '/lib/utils/CurlUtils.php';

$configFile = __DIR__ . '/configuration/configure.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

function send_response($response)
{
    http_response_code($response->httpCode);
    foreach ($response->headerLines as $header) {
        header($header);
    }
    echo $response->body;
}

$request = new Request();
$response = RequestProcessor::handleRequest($request);

send_response($response);
