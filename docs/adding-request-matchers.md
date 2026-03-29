# Adding New Request Matchers

## Where to place the class

New `RequestMatcher` classes must be placed in `source/source/lib/matchers/`.

## How `RequestMatcher::build` maps the type

`RequestMatcher::build` uses `StringUtils::toStudlyCase` to convert the `type` string from configuration into a concrete `RequestMatcher` class name. This means the type string in configuration maps directly to the class name (e.g., `'begins_with'` → `BeginsWithRequestMatcher`).

## How to register it

Add a `require_once` line for the new class in `source/source/loader.php`, following the same pattern as the existing matchers. Dependency-first ordering applies: interfaces and base classes must be loaded before concrete implementations.

## How to write unit tests

Unit tests for each `RequestMatcher` belong in `source/tests/unit/lib/matchers/`. Cover all matcher conditions, including edge cases and the behaviour of any nested/negative matchers.

## Configuration example

Matchers are used in rule definitions in `docker_volumes/configuration/configure.php`:

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'default_proxy',
        'host' => 'http://api:80'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/persons', 'type' => 'begins_with'],
    ]
]);
```

For matchers used inside `FileCacheMiddleware`, use the full class name:

```php
'matchers' => [
    ['class' => 'Tent\\Matchers\\StatusCodeMatcher', 'httpCodes' => [200]]
]
```
