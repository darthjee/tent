<?php

// Default bootstrap baked into the darthjee/tent-test image.
//
// Loads Composer's autoloader (Tent's core classes plus dev tooling), then
// conditionally requires the extension's own loader.php. That file only
// exists once a user mounts their extension at /var/www/html/extension, so
// the require is guarded to keep the image usable without a mount.

require_once __DIR__ . '/vendor/autoload.php';

$extensionLoader = '/var/www/html/extension/loader.php';

if (file_exists($extensionLoader)) {
    require_once $extensionLoader;
}
