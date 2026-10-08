<?php

use Tent\Models\Request;
use Tent\Models\Response;
use Tent\Service\RequestProcessor;
use Tent\Service\ResponseSender;

require_once __DIR__ . '/loader.php';

$configFile = __DIR__ . '/configuration/configure.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

/**
 * Sends the HTTP response to the client.
 *
 * @param Tent\Models\Response $response The response to send.
 * @return void
 */
function send_response(Response $response)
{
    (new ResponseSender())->send($response);
}

$request = new Request();
$response = RequestProcessor::handleRequest($request);

send_response($response);
