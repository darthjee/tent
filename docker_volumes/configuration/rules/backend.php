<?php

use Tent\Configuration;

Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://api:80'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
    ],
    "middlewares" => [
        [
            'class' => 'Tent\Middlewares\FileCacheMiddleware',
            'location' => "./cache",
            'httpCodes' => [200]
        ],
        [
            'class' => 'Tent\Middlewares\SetHeadersMiddleware',
            'headers' => [
                'Host' => 'backend.local'
            ]
        ]
    ]
]);

Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://api:80'
    ],
    'matchers' => [
        ['method' => 'POST', 'uri' => '/persons', 'type' => 'exact']
    ],
    "middlewares" => [
        [
            'class' => 'Tent\Middlewares\SetHeadersMiddleware',
            'headers' => [
                'Host' => 'backend.local'
            ]
        ]
    ]
]);
