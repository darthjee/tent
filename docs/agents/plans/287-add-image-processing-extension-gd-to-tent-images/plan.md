# Plan: Add image processing extension (GD) to Tent images

Issue: [287-add-image-processing-extension-gd-to-tent-images.md](../../issues/287-add-image-processing-extension-gd-to-tent-images.md)

## Overview

Install GD (JPEG/PNG) and `exif`, plus a `conf.d/tent.ini` that raises the upload limits, in `darthjee/tent` and `dev_tent-base`. Bump the base to `0.0.3` so `tent-test`/`dev_tent` pick it up. Fix the arm64 `tent-test` build so it uses the arm64 base (`BASE_SUFFIX` build arg). CI releases the base before `tent-test` and checks each image before pushing it. Document the runtime for extension authors, then bump Tent to `1.0.0`.

## Agents involved

- [infra](infra.md)
- [product-dev-extending](product-dev-extending.md)

## Shared contracts

The PHP runtime guaranteed by both `darthjee/tent` and `darthjee/tent-test` (and `dev_tent-base`). infra must produce exactly this, the CI check asserts it, and the docs describe it:

| Item | Value |
|---|---|
| Extensions | `gd` (with JPEG and PNG support) and `exif` loaded |
| Functions available | `imagecreatefromjpeg`, `imagecreatefrompng`, `imagescale`, `imagerotate`, `imagejpeg`, `imagepng`, `exif_read_data` |
| `upload_max_filesize` | `20M` |
| `post_max_size` | `25M` |
| `memory_limit` | unchanged, `128M` (php:8.4-apache default); extensions may raise it with `ini_set('memory_limit', …)` |
| ini file | `/usr/local/etc/php/conf.d/tent.ini` |
| Not included | Imagick / ImageMagick CLI; GD WebP/GIF/AVIF/FreeType |
| Versions | `dev_tent-base:0.0.3`; `tent`/`tent-test`: `1.0.0` |
