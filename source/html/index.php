<?php

namespace Tent;

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Simple response
$response = [
    'status' => 'ok',
    'message' => 'Request received',
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'timestamp' => date('c')
];

echo json_encode($response, JSON_PRETTY_PRINT);