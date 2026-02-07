<?php

use Tent\Configuration;
use Tent\Models\Rule;
use Tent\RequestHandlers\ProxyRequestHandler;
use Tent\RequestHandlers\StaticFileHandler;
use Tent\Models\Server;
use Tent\Models\FolderLocation;
use Tent\Models\RequestMatcher;

if (getenv('FRONTEND_DEV_MODE') === 'true') {
    Configuration::buildRule([
        'handler' => [
            'type' => 'proxy',
            'host' => 'http://frontend:8080'
        ],
        'matchers' => [
            ['method' => 'GET', 'uri' => '/', 'type' => 'exact'],
            ['method' => 'GET', 'uri' => '/assets/js/', 'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/assets/css/', 'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/@vite/', 'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/node_modules/', 'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/@react-refresh', 'type' => 'exact']
        ]
    ]);
} else {
    Configuration::buildRule([
        'handler' => [
            'type' => 'static',
            'location' => '/var/www/html/static'
        ],
        'matchers' => [
            ['method' => 'GET', 'uri' => '/index.html', 'type' => 'exact'],
            ['method' => 'GET', 'uri' => '/assets', 'type' => 'begins_with'],
        ]
    ]);
    Configuration::buildRule([
        'handler' => [
            'type' => 'static',
            'location' => '/var/www/html/static/'
        ],
        'matchers' => [
            ['method' => 'GET', 'uri' => '/', 'type' => 'exact'],
        ],
        "middlewares" => [
            [
                'class' => 'Tent\Middlewares\SetPathMiddleware',
                'path' => '/index.html'
            ]
        ]
    ]);
}
