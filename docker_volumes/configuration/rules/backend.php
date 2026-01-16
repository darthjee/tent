<?php

use Tent\Configuration;
use Tent\Models\Rule;
use Tent\Handlers\ProxyRequestHandler;
use Tent\Models\Server;
use Tent\Models\RequestMatcher;

Configuration::addRule(
    Rule::build([
        'host' => 'http://api:80',
        'rules' => [
            ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
        ]
    ])
);
