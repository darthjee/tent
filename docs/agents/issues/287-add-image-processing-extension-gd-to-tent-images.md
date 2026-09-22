# Issue: Add image processing extension (GD) to Tent images

## Description

Add the PHP **GD** extension (JPEG and PNG support) and the **exif** extension to the `darthjee/tent` and `darthjee/tent-test` images, raise PHP's upload limits, and release everything as **1.0.0**.

Oak's proxy extension (`Oak\Proxy\PhotoSubmitRequestHandler`, see darthjee/oak#328) accepts photo uploads and has to write resized copies of each one: an 800x1064 max "photo" and a 215x215 max "snap". The backend runs somewhere else and can't see the proxy's disk, so the resizing has to happen inside the Tent process. The production host that runs Oak's proxy has `gd`, `exif` and `imagick`. Tent's images have none of them today.

## Problem

- Neither `darthjee/tent` nor `darthjee/tent-test` ships an image library (`php -m` lists no `gd`/`imagick`), so extensions can't resize images.
- Phone JPEGs store rotation in an EXIF `Orientation` flag. GD ignores it, and without `exif` extensions can't read the flag, so resized photos come out sideways.
- The stock `php:8.4-apache` limits (`upload_max_filesize=2M`, `post_max_size=8M`) reject typical phone photos (3–12 MB) before Tent runs: `UPLOAD_ERR_INI_SIZE`, or an empty body when `post_max_size` is exceeded. These can't be changed with `ini_set()`.
- `tent-test` builds `FROM darthjee/dev_tent-base`, so GD has to go into the base image, which needs a base version bump. In CI, all `build-and-release-*` jobs run in parallel, so on the first release `tent-test` would try to build `FROM dev_tent-base:0.0.3` before that image is published.
- **Existing bug:** `tent-test`'s arm64 build uses the amd64 base. arm64 tags carry a `-arm64` suffix, but `FROM darthjee/dev_tent-base:<v>` always resolves to the amd64 image. Confirmed on Docker Hub: `tent-test:0.10.4-arm64` has the same layers as `dev_tent-base:0.0.2` (amd64), not `dev_tent-base:0.0.2-arm64`.

## Expected Behavior

- [ ] `php -m` lists `gd` and `exif` in `darthjee/tent` and `darthjee/tent-test`.
- [ ] `gd_info()` reports JPEG and PNG support.
- [ ] `imagecreatefromjpeg`, `imagecreatefrompng`, `imagescale`, `imagerotate`, `imagejpeg`, `imagepng` and `exif_read_data` are available.
- [ ] `php -r 'echo ini_get("upload_max_filesize"), " ", ini_get("post_max_size");'` prints `20M 25M` in both images.
- [ ] CI runs the image check after each `tent`/`tent-test` build (amd64 + arm64) and doesn't release an image that fails it.
- [ ] `docs/guides/tent/extending-tent.md` documents the available extensions and PHP limits.
- [ ] Images published as `1.0.0` (amd64 + arm64).
- [ ] `dev_tent-base:0.0.3` is published before `tent-test` builds (CI `requires`).
- [ ] `tent-test:1.0.0-arm64` is built on `dev_tent-base:0.0.3-arm64` (its base layers match the arm64 base, not the amd64 one).

## Solution

### Image changes

- `dockerfiles/tent/Dockerfile` and `dockerfiles/dev_tent-base/Dockerfile`:
  - install GD with JPEG/PNG: `libjpeg62-turbo-dev libpng-dev`, `docker-php-ext-configure gd --with-jpeg`, `docker-php-ext-install gd`;
  - install `exif`: `docker-php-ext-install exif`;
  - ship a `php.ini` drop-in at `/usr/local/etc/php/conf.d/tent.ini`:
    ```ini
    upload_max_filesize=20M
    post_max_size=25M
    ```
- `Makefile`: `BASE_VERSION` from `0.0.2` to `0.0.3`.
- `dockerfiles/tent-test/Dockerfile` and `dockerfiles/dev_tent/Dockerfile`: `ARG BASE_SUFFIX=""` before `FROM`, then `FROM darthjee/dev_tent-base:0.0.3${BASE_SUFFIX}`.
- `scripts/build_docker_image.sh`: for `tent-test` and `dev_tent`, pass `--build-arg BASE_SUFFIX=$ARCH_SUFFIX` (empty for amd64, `-arm64` for arm64).

### CI verification

- Add a `check` command to `scripts/build_docker_image.sh` (e.g. `scripts/build_docker_image.sh check <image> <arch> <version>`), exposed as `make ci-check-tent` / `make ci-check-tent-test`. It runs the built image with `docker run --rm --platform <platform> <image>:<version><suffix> php -r '…'` and exits non-zero unless:
  - `extension_loaded('gd')` and `extension_loaded('exif')`;
  - `gd_info()` reports JPEG and PNG support;
  - `imagecreatefromjpeg`, `imagecreatefrompng`, `imagescale`, `imagerotate`, `imagejpeg`, `imagepng` and `exif_read_data` exist;
  - `ini_get('upload_max_filesize') === '20M'` and `ini_get('post_max_size') === '25M'`;
  - `php_uname('m')` matches the target arch (`x86_64` for amd64, `aarch64` for arm64). This catches the arm64-on-amd64-base regression.
- `.circleci/config.yml`: in `build-and-release-linux`, `build-and-release-macos`, `build-and-release-tent-test-linux` and `build-and-release-tent-test-macos`, add a check step between "Docker build" and "Docker login"/"Docker release", so an image that fails the check is never pushed. The macos jobs already set up QEMU, so the arm64 check runs emulated.

### Documentation

- `docs/guides/tent/extending-tent.md`: add a short section on the PHP runtime that extensions can rely on in both `darthjee/tent` and `darthjee/tent-test`:
  - `gd` (JPEG/PNG) and `exif` are available, for resizing uploads and fixing EXIF orientation;
  - `upload_max_filesize=20M`, `post_max_size=25M` (set in `/usr/local/etc/php/conf.d/tent.ini`);
  - `memory_limit` stays at the 128M default; extensions that decode large images should raise it with `ini_set('memory_limit', …)`.

### Release

- `.circleci/config.yml`: `build-and-release-tent-test-linux` requires `build-and-release-base-linux`; `build-and-release-tent-test-macos` requires `build-and-release-base-macos`.
- Run `scripts/bump_version.sh 1.0.0` (updates README, `docs/guides/how-to-use-tent.md`, `Makefile` `VERSION`, `source/composer.json`). After merging, tag `1.0.0`. CI releases `dev_tent-base:0.0.3`, then `tent`/`tent-test:1.0.0` (amd64 + arm64). The next release becomes `1.0.1`.

### Why GD (and not Imagick)

GD ships with PHP, adds little to the image, and covers jpg/png resizing, which is all Oak accepts. The production host also has GD, so code written against it runs unchanged there. Imagick would pull in all of ImageMagick and need `policy.xml` hardening.

### Out of scope

- `dockerfiles/circleci_tent-base`: Tent's own code and specs don't use GD.
- Imagick / the ImageMagick CLI.
- WebP, GIF, AVIF and FreeType support in GD.
- Raising `memory_limit` (stays at 128M). GD needs about 4–5 bytes per pixel while decoding (a 12 MP photo is about 60 MB); extensions can call `ini_set('memory_limit', …)` at runtime.
- Image-handling logic, which belongs to the extension (Oak): preserving PNG transparency, not upscaling, validating the real file type (`getimagesize()`), HEIC.

## Benefits

- Tent extensions can accept phone photos and resize them in-process, which unblocks darthjee/oak#328.
- Extension specs in `tent-test` can exercise the same image code that runs in production.
- The arm64 `tent-test` image becomes a real arm64 build, and future base bumps release in the right order.
