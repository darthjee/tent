<?php

use Tent\Configuration;
use Tent\Rule;
use Tent\ProxyRequestHandler;
use Tent\StaticFileHandler;
use Tent\Server;
use Tent\FolderLocation;
use Tent\RequestMatcher;

if (getenv('FRONTEND_DEV_MODE') === 'true') {
    Configuration::addRule(
        new Rule(
            new ProxyRequestHandler(new Server('http://frontend:8080')),
            [
                new RequestMatcher('GET', '/', 'exact'),
                new RequestMatcher('GET', '/assets/js/', 'begins_with'),
                new RequestMatcher('GET', '/assets/css/', 'begins_with'),
                new RequestMatcher('GET', '/@vite/', 'begins_with'),
                new RequestMatcher('GET', '/node_modules/', 'begins_with'),
                new RequestMatcher('GET', '/@react-refresh', 'exact')
            ]
        )
    );
} else {
    Configuration::addRule(
        new Rule(
            new StaticFileHandler(new FolderLocation('/var/www/html/static')),
            [
                new RequestMatcher('GET', '/index.html', 'exact'),
                new RequestMatcher('GET', '/assets', 'begins_with'),
            ]
        )
    );
}

Configuration::addRule(
    new Rule(
        new ProxyRequestHandler(new Server('http://api:80')),
        [
            new RequestMatcher('GET', '/persons', 'exact')
        ]
    )
);
