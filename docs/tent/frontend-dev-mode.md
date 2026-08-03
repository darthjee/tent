# Frontend Dev Mode Flip

When working with a modern JS frontend (e.g. Vite, webpack), you typically want two behaviors:

- **Development**: proxy requests live to the dev server (so hot module replacement works).
- **Production / staging**: serve the pre-built static files directly from Tent.

Tent has no built-in knowledge of this distinction, but you can implement it yourself by reading an environment variable inside the rule file.

## Environment variable

Set `FRONTEND_DEV_MODE=true` in your `.env` for development and omit it (or set it to `false`) for production.

## Rule file

```php
<?php

if (getenv('FRONTEND_DEV_MODE') === 'true') {
    // Development: proxy live requests to the Vite dev server
    Configuration::buildRule([
        'handler' => [
            'type' => 'proxy',
            'host' => 'http://frontend:8080'
        ],
        'matchers' => [
            ['method' => 'GET', 'uri' => '/',               'type' => 'exact'],
            ['method' => 'GET', 'uri' => '/assets/js/',     'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/assets/css/',    'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/@vite/',         'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/node_modules/',  'type' => 'begins_with'],
            ['method' => 'GET', 'uri' => '/@react-refresh', 'type' => 'exact']
        ]
    ]);
    // Images are still served statically even in dev mode
    Configuration::buildRule([
        'handler' => [
            'type'     => 'static',
            'location' => '/var/www/html/static'
        ],
        'matchers' => [
            ['method' => 'GET', 'uri' => '/assets/images/', 'type' => 'begins_with']
        ]
    ]);
} else {
    // Production: serve pre-built static files from /var/www/html/static
    Configuration::buildRule([
        'handler' => [
            'type'     => 'static',
            'location' => '/var/www/html/static'
        ],
        'matchers' => [
            ['method' => 'GET', 'uri' => '/index.html', 'type' => 'exact'],
            ['method' => 'GET', 'uri' => '/assets',     'type' => 'begins_with'],
        ]
    ]);
    // Map / to /index.html for SPA routing
    Configuration::buildRule([
        'handler' => [
            'type'     => 'static',
            'location' => '/var/www/html/static'
        ],
        'matchers' => [
            ['method' => 'GET', 'uri' => '/', 'type' => 'exact']
        ],
        'middlewares' => [
            [
                'class' => 'Tent\Middlewares\SetPathMiddleware',
                'path'  => '/index.html'
            ]
        ]
    ]);
}
```

## Why this works

Rules are evaluated at request time, but `getenv()` is resolved at boot — when PHP parses the configuration. As long as the `FRONTEND_DEV_MODE` environment variable is set correctly before the container starts, Tent will load the right set of rules.

[← Back to How to Use darthjee/tent](../HOW_TO_USE_DARTHJEE-TENT.md)
