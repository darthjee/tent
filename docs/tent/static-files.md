# Static Files

Place any assets you want Tent to serve directly (images, committed CSS, etc.) into the folder you mount at `/var/www/html/static/`.

If your frontend build tool (e.g. Vite) writes its output to a different path, share a volume between the build container and the Tent container so built files land directly in the static root without a copy step:

```yaml
volumes:
  - ./docker_volumes/static/index.html:/var/www/html/static/index.html
  - ./docker_volumes/static/assets/js/:/var/www/html/static/assets/js/
  - ./docker_volumes/static/assets/css/:/var/www/html/static/assets/css/
```

The Vite container writes to `./docker_volumes/static/` as its `outDir`, and Tent picks it up immediately.

[← Back to How to Use darthjee/tent](../HOW_TO_USE_DARTHJEE-TENT.md)
