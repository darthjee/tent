<?php

header('Content-Type: application/json');

$response = [
    'status' => 'ok',
    'message' => 'API Dev Server',
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'timestamp' => date('c')
];

echo json_encode($response, JSON_PRETTY_PRINT);
