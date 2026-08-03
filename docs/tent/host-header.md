# Host Header and Why It Matters

When Tent forwards a request to an upstream backend, the `Host` header it sends determines how the backend identifies the virtual host being requested. Getting this wrong is a common source of routing failures with frameworks like Rails, Django, Express, and PHP servers such as Apache or nginx.

**The problem**: By default, the `Host` header in the forwarded request still contains the hostname the browser sent (e.g., `localhost` or `myapp.com`). Many backends reject or misroute requests where `Host` does not match the expected service name.

**The solution**: Override the `Host` header to match the upstream service's hostname, and preserve the original value under `X-Forwarded-Host`.

`default_proxy` handles this automatically:
- The original `Host` is renamed to `X-Forwarded-Host`.
- `Host` is set to the hostname part of the configured `host` URL.

If you use `proxy` directly, you must do this manually:

```php
'middlewares' => [
    // 1. Preserve original Host for the backend to inspect if needed
    [
        'class' => 'Tent\Middlewares\RenameHeaderMiddleware',
        'from'  => 'Host',
        'to'    => 'X-Forwarded-Host'
    ],
    // 2. Set the Host the upstream expects
    [
        'class' => 'Tent\Middlewares\SetHeadersMiddleware',
        'headers' => [
            'Host' => 'backend'
        ]
    ]
]
```

## Example: backend on a named Docker service

```yaml
# docker-compose.yml
services:
  proxy:
    image: darthjee/tent:latest
    links:
      - my_api:api
  my_api:
    image: myapp/api:latest
```

```php
// rules/backend.php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:3000'
        // Host header will be set to 'api' automatically
    ],
    'matchers' => [
        ['uri' => '/api/', 'type' => 'begins_with']
    ]
]);
```

[← Back to How to Use darthjee/tent](../HOW_TO_USE_DARTHJEE-TENT.md)
