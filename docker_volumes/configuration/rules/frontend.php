<?php

use Tent\Configuration;
use Tent\Models\Rule;
use Tent\Handlers\FixedFileHandler;
use Tent\Handlers\ProxyRequestHandler;
use Tent\Handlers\StaticFileHandler;
use Tent\Models\Server;
use Tent\Models\FolderLocation;
use Tent\Models\RequestMatcher;

if (getenv('FRONTEND_DEV_MODE') === 'true') {
    Configuration::addRule(
        Rule::build([
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
        ])
    );
} else {
    Configuration::addRule(
        Rule::build([
            'handler' => [
                'type' => 'static',
                'location' => '/var/www/html/static'
            ],
            'matchers' => [
                ['method' => 'GET', 'uri' => '/index.html', 'type' => 'exact'],
                ['method' => 'GET', 'uri' => '/assets', 'type' => 'begins_with'],
            ]
        ])
    );
    Configuration::addRule(
        Rule::build([
            'handler' => [
                'type' => 'fixed',
                'file' => '/var/www/html/static/index.html'
            ],
            'matchers' => [
                ['method' => 'GET', 'uri' => '/', 'type' => 'exact'],
            ]
        ])
    );
}
