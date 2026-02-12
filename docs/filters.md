# Filter System

The Filter system in Tent allows you to define conditions that must be met for middleware operations to be applied. Filters can check both incoming requests and outgoing responses, providing fine-grained control over when middleware actions should occur.

## Overview

A `Filter` is an abstract class that provides two methods:
- `matchRequest(ProcessingRequest $request): bool` - Checks if a request matches certain criteria
- `matchResponse(Response $response): bool` - Checks if a response matches certain criteria

Both methods have default implementations that return `true`, so you only need to override the methods you need.

## Built-in Filters

### StatusCodeMatcher

Matches responses based on HTTP status codes.

**Example:**
```php
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://api:80'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/data', 'type' => 'exact']
    ],
    'middlewares' => [
        [
            'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
            'location' => './cache',
            'matchers' => [
                [
                    'class' => 'Tent\\Matchers\\StatusCodeMatcher',
                    'httpCodes' => [200, 201] // Cache responses with 200 or 201 status
                ]
            ]
        ]
    ]
]);
```

You can also use patterns like `"2xx"` to match all 2xx status codes:

```php
'matchers' => [
    [
        'class' => 'Tent\\Matchers\\StatusCodeMatcher',
        'httpCodes' => ["2xx"] // Cache all successful responses
    ]
]
```

### GetRequestSuccessFilter

An example filter that checks both request and response. It only matches when:
- The request is a GET request
- The response has HTTP status code 200

**Example:**
```php
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://api:80'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/api/data', 'type' => 'exact']
    ],
    'middlewares' => [
        [
            'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
            'location' => './cache',
            'matchers' => [
                [
                    'class' => 'Tent\\Matchers\\GetRequestSuccessFilter'
                ]
            ]
        ]
    ]
]);
```

## Creating Custom Filters

To create a custom filter, extend the `Filter` class and override `matchRequest()` and/or `matchResponse()`:

```php
<?php

namespace Tent\Matchers;

use Tent\Models\Response;
use Tent\Models\ProcessingRequest;

class CustomFilter extends Filter
{
    /**
     * Only match POST requests
     */
    public function matchRequest(ProcessingRequest $request): bool
    {
        return $request->requestMethod() === 'POST';
    }

    /**
     * Only match successful responses (2xx status codes)
     */
    public function matchResponse(Response $response): bool
    {
        $code = $response->httpCode();
        return $code >= 200 && $code < 300;
    }

    /**
     * Build method for configuration
     */
    public static function build(array $attributes): self
    {
        return new self();
    }
}
```

## Using Multiple Filters

You can combine multiple filters in the `matchers` array. ALL filters must match for the middleware operation to proceed:

```php
'matchers' => [
    [
        'class' => 'Tent\\Matchers\\StatusCodeMatcher',
        'httpCodes' => [200]
    ],
    [
        'class' => 'YourNamespace\\CustomFilter'
    ]
]
```

## Migration from ResponseMatcher

The `ResponseMatcher` class is now deprecated in favor of `Filter`. For backward compatibility, `ResponseMatcher` extends `Filter` and its `match()` method is automatically mapped to `matchResponse()`.

**Old way (deprecated):**
```php
class MyMatcher extends ResponseMatcher
{
    public function match(Response $response): bool
    {
        return $response->httpCode() === 200;
    }
}
```

**New way (recommended):**
```php
class MyFilter extends Filter
{
    public function matchResponse(Response $response): bool
    {
        return $response->httpCode() === 200;
    }
    
    // Optionally also check requests
    public function matchRequest(ProcessingRequest $request): bool
    {
        return $request->requestMethod() === 'GET';
    }
}
```

## Configuration Attribute Migration

The old `httpCodes` attribute in middleware configuration is also deprecated:

**Old way (deprecated):**
```php
[
    'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
    'location' => './cache',
    'httpCodes' => [200]
]
```

**New way (recommended):**
```php
[
    'class' => 'Tent\\Middlewares\\FileCacheMiddleware',
    'location' => './cache',
    'matchers' => [
        [
            'class' => 'Tent\\Matchers\\StatusCodeMatcher',
            'httpCodes' => [200]
        ]
    ]
]
```

The new approach provides more flexibility and allows you to:
- Use multiple filters
- Create custom filters that check both requests and responses
- Combine different types of filters
