<?php

use Tent\Configuration;
use Tent\Models\Rule;
use Tent\Handlers\FixedFileHandler;
use Tent\Handlers\ProxyRequestHandler;
use Tent\Handlers\StaticFileHandler;
use Tent\Models\Server;
use Tent\Models\FolderLocation;
use Tent\Models\RequestMatcher;

require_once __DIR__ . '/rules/frontend.php';

Configuration::addRule(
    new Rule(
        new ProxyRequestHandler(new Server('http://api:80')),
        [
            new RequestMatcher('GET', '/persons', 'exact')
        ]
    )
);
