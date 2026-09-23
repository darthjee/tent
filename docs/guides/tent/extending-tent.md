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

## PHP runtime available to extensions

Since `1.0.0`, both `darthjee/tent` and `darthjee/tent-test` ship the `gd` extension (with JPEG and PNG support) and `exif`. Extensions can use them to decode, resize and re-encode uploaded images: `imagecreatefromjpeg`/`imagecreatefrompng`, `imagescale`, `imagejpeg`/`imagepng`.

Phone JPEGs often carry an EXIF `Orientation` flag, and GD ignores it. Read the flag with `exif_read_data()` and apply `imagerotate()` before you resize:

```php
function loadOrientedJpeg(string $path): \GdImage
{
    $image = imagecreatefromjpeg($path);
    $exif = @exif_read_data($path);
    $orientation = $exif['Orientation'] ?? 1;

    $angle = match ($orientation) {
        3 => 180,
        6 => -90,
        8 => 90,
        default => 0,
    };

    return $angle === 0 ? $image : imagerotate($image, $angle, 0);
}

$image = loadOrientedJpeg($uploadedPath);
$thumbnail = imagescale($image, 320);
imagejpeg($thumbnail, $targetPath, 85);
```

Upload and memory limits:

- `upload_max_filesize=20M` and `post_max_size=25M` are set in `/usr/local/etc/php/conf.d/tent.ini`. You can't change them with `ini_set()`. To override them, mount or add another `.ini` file in `/usr/local/etc/php/conf.d/`.
- `memory_limit` stays at PHP's default of `128M`. GD needs about 4–5 bytes per pixel while it decodes an image, so a 12 MP photo takes about 60 MB. Extensions that handle large images should raise the limit with `ini_set('memory_limit', '256M')` or a similar value.

Only JPEG and PNG are guaranteed. Imagick (and the ImageMagick CLI) isn't included, and GD has no WebP, AVIF or FreeType support. The bundled GD can also handle GIF and BMP, but those formats aren't guaranteed.

## Testing your extension

The production `darthjee/tent` image is deliberately lean in dev tooling. It ships no PHPUnit or other test tooling, so there is no way to run automated tests against a custom matcher, middleware, or handler using it. Use the separate `darthjee/tent-test` image for that instead.

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

[← Back to How to Use darthjee/tent](../how-to-use-tent.md)
