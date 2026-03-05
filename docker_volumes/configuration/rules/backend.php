<?php

use Tent\Configuration;

Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'api:80'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
    ]
]);

Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'api:80'
    ],
    'matchers' => [
        ['method' => 'POST', 'uri' => '/persons', 'type' => 'exact']
    ]
]);
