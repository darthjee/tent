# Complete Example Layout

```
my-project/
├── docker-compose.yml
├── .env                          # FRONTEND_DEV_MODE=true
├── proxy/
│   └── static/
│       └── assets/
│           └── images/           # committed static images
├── proxy_configuration/          # mounted into Tent at /var/www/html/configuration/
│   ├── configure.php
│   └── rules/
│       ├── backend.php
│       └── frontend.php
└── docker_volumes/
    ├── cache/                    # FileCacheMiddleware writes here
    └── static/                   # Vite build output, shared with Tent
        ├── index.html
        └── assets/
            ├── js/
            └── css/
```

## `docker-compose.yml`

```yaml
services:
  proxy:
    image: darthjee/tent:latest
    ports:
      - "0.0.0.0:80:80"
    volumes:
      - ./proxy/static/:/var/www/html/static/
      - ./proxy_configuration/:/var/www/html/configuration/
      - ./docker_volumes/static/:/var/www/html/static/built/
      - ./docker_volumes/cache/:/var/www/html/cache/
    links:
      - api:api
      - frontend:frontend
    env_file: .env

  api:
    image: myapp/api:latest

  frontend:
    image: myapp/frontend:latest
```

## `proxy_configuration/configure.php`

```php
<?php

require_once __DIR__ . '/rules/frontend.php';
require_once __DIR__ . '/rules/backend.php';
```

## `proxy_configuration/rules/backend.php`

```php
<?php

// Read endpoints — cached
Configuration::buildRule([
    'handler' => [
        'type'  => 'default_proxy',
        'host'  => 'http://api:3000',
        'cache' => './cache'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/', 'type' => 'begins_with']
    ]
]);

// Write endpoints — no cache
Configuration::buildRule([
    'handler' => [
        'type'  => 'default_proxy',
        'host'  => 'http://api:3000',
        'cache' => false
    ],
    'matchers' => [
        ['method' => 'POST',   'uri' => '/api/', 'type' => 'begins_with'],
        ['method' => 'PUT',    'uri' => '/api/', 'type' => 'begins_with'],
        ['method' => 'DELETE', 'uri' => '/api/', 'type' => 'begins_with'],
        ['method' => 'PATCH',  'uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```

[← Back to How to Use darthjee/tent](../HOW_TO_USE_DARTHJEE-TENT.md)
