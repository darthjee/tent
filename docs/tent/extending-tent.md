# Extending Tent

Tent supports custom PHP classes (matchers, middlewares, handlers) via a mount-based extension mechanism — no fork or image rebuild required.

## How it works

Tent automatically includes `/var/www/html/extension/loader.php` after all core classes are loaded and before `configuration/configure.php` runs. By default this file is a no-op (an empty PHP file). To add custom classes, mount a `loader.php` file at that path:

```yaml
services:
  proxy:
    image: darthjee/tent:latest
    volumes:
      - ./proxy/configuration/:/var/www/html/configuration/
      - ./proxy/extension/:/var/www/html/extension/
```

## Extension loader

Create `./proxy/extension/loader.php` with `require_once` calls for your custom classes:

```php
<?php

require_once __DIR__ . '/MyCustomMatcher.php';
require_once __DIR__ . '/MyCustomMiddleware.php';
```

Because the extension loader runs after all Tent core classes, your custom classes can extend any built-in class or implement any built-in interface.

## Using custom classes in configuration

Once loaded, your classes are available in `configure.php` by their fully-qualified name:

```php
<?php

use Tent\Configuration;

Configuration::buildRule([
    'handler' => ['type' => 'proxy', 'host' => 'http://backend:80'],
    'matchers' => [
        ['class' => 'MyCustomMatcher', 'pattern' => '/api/v2/']
    ],
    'middlewares' => [
        ['class' => 'MyCustomMiddleware']
    ]
]);
```

## Testing your extension

The production `darthjee/tent` image is deliberately lean — it ships no PHPUnit or other dev tooling, so there is no way to run automated tests against a custom matcher, middleware, or handler using it. Use the separate `darthjee/tent-test` image for that instead.

`darthjee/tent-test` bundles Tent's own source, the full dev-tooling set from its `composer.json` (`phpunit`, `pcov`, `phpcs`, `phpmd`, `phpdocumentor`), and a set of reusable test-support helper classes (`DummyRequestMiddleware`, `QuickResponseMiddleware`, `DummyResponseMiddleware`, `FileSystemUtils`, `RequestToBodyHandler`) under `tests/support/` that you can require from your own test classes. These helpers are adopted as quasi-public API for this purpose and may evolve between Tent versions.

It reuses the same `./proxy/extension/` mount from above, plus a second mount for your own PHPUnit test classes:

```yaml
# docker-compose.yml
services:
  extension_tests:
    image: darthjee/tent-test:latest
    volumes:
      - ./proxy/extension/:/var/www/html/extension/
      - ./proxy/extension_tests/:/var/www/html/tests/extension/
```

```bash
docker compose run --rm extension_tests
```

Equivalently, without Compose:

```bash
docker run --rm \
  -v ./extension:/var/www/html/extension \
  -v ./extension_tests:/var/www/html/tests/extension \
  darthjee/tent-test
```

The image bakes in a default `phpunit.xml` that requires `vendor/autoload.php` and then `/var/www/html/extension/loader.php` before running, and points its test suite at `/var/www/html/tests/extension` — so running the container with these two mounts and no extra arguments runs your extension's tests immediately. The default command (`vendor/bin/phpunit`) is overridable, e.g. `docker run --rm darthjee/tent-test /bin/bash` for a debugging shell, or `... vendor/bin/phpcs` to run one of the other bundled tools directly (Tent's own `phpcs.xml`/`phpmd.xml` configs aren't baked into the image, so `composer lint` isn't available out of the box).

[← Back to How to Use darthjee/tent](../HOW_TO_USE_DARTHJEE-TENT.md)
