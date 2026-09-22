# Product-dev-extending Plan: Add image processing extension (GD) to Tent images

Main plan: [plan.md](plan.md)

## Shared contracts

Document exactly the runtime from the main plan's table, as guaranteed by both `darthjee/tent` and `darthjee/tent-test` from `1.0.0`: `gd` (JPEG + PNG) and `exif`; `upload_max_filesize=20M` and `post_max_size=25M` in `/usr/local/etc/php/conf.d/tent.ini`; `memory_limit` stays at the 128M default. Imagick and GD WebP/GIF/AVIF/FreeType are not included.

## Implementation Steps

### Step 1 — Document the PHP runtime available to extensions

In `docs/guides/tent/extending-tent.md`, add a short section (e.g. `## PHP runtime available to extensions`) before `## Testing your extension`. It should state:

- Since `1.0.0`, both `darthjee/tent` and `darthjee/tent-test` ship the `gd` extension (JPEG and PNG) and `exif`, so extensions can decode, resize and re-encode uploaded images (`imagecreatefromjpeg`/`imagecreatefrompng`, `imagescale`, `imagejpeg`/`imagepng`).
- JPEGs from phones often carry an EXIF `Orientation` flag that GD ignores. Read it with `exif_read_data()` and apply `imagerotate()` before resizing. Include a short example (orientations 3 → 180°, 6 → -90°, 8 → 90°).
- Upload limits: `upload_max_filesize=20M` and `post_max_size=25M`, set in `/usr/local/etc/php/conf.d/tent.ini`. These can't be changed with `ini_set()`; override them by mounting or adding another `conf.d` ini.
- `memory_limit` stays at PHP's 128M default. GD needs roughly 4–5 bytes per pixel while decoding (a 12 MP photo is about 60 MB), so extensions that handle large images should raise it with `ini_set('memory_limit', …)`.
- Imagick and GD WebP/GIF/AVIF support are not included.

Also adjust the "deliberately lean" sentence under `## Testing your extension` if needed, so it doesn't contradict the new section. It refers to dev tooling (PHPUnit), not PHP extensions.

## Files to Change
- `docs/guides/tent/extending-tent.md` — new PHP runtime section, and a wording check on the "deliberately lean" sentence.

## Notes
- Check the documented values against the actual Dockerfiles once infra's steps land (`docker run --rm --entrypoint php darthjee/tent-test:<tag> -m` / `-i`), following this agent's "prove it, not just assert it" rule.
- `docs/guides/how-to-use-tent.md`'s Minimum version line is updated by infra's `scripts/bump_version.sh 1.0.0` step. Don't edit it by hand.
