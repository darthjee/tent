<?php

use Tent\Configuration;
use Tent\Models\Rule;
use Tent\Handlers\ProxyRequestHandler;
use Tent\Models\Server;
use Tent\Models\RequestMatcher;

Configuration::addRule(
    Rule::build([
        'handler' => [
            'type' => 'proxy',
            'host' => 'http://api:80'
        ],
        'matchers' => [
            ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
        ]
    ])
);
