<?php

use Tent\Request;
use Tent\RequestProcessor;

require_once __DIR__ . '/lib/models/Response.php';
require_once __DIR__ . '/lib/models/MissingResponse.php';
require_once __DIR__ . '/lib/models/Request.php';
require_once __DIR__ . '/lib/utils/CurlUtils.php';
require_once __DIR__ . '/lib/http/HttpClientInterface.php';
require_once __DIR__ . '/lib/http/CurlHttpClient.php';
require_once __DIR__ . '/lib/handlers/ProxyRequestHandler.php';
require_once __DIR__ . '/lib/handlers/MissingRequestHandler.php';
require_once __DIR__ . '/lib/models/RequestMatcher.php';
require_once __DIR__ . '/lib/service/RequestProcessor.php';

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
