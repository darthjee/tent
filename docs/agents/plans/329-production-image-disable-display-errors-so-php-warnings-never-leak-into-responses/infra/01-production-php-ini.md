# Production PHP ini

Create `dockerfiles/tent/php/tent.ini` with the content from the issue's "Changes" section: the explanatory header comment, `display_errors = Off`, `log_errors = On`, the comment explaining why `error_log` is unset (under mod_php PHP then writes to the Apache error log, linked to stderr, shared with Tent's `error_log()` output), `upload_max_filesize = 20M`, `post_max_size = 25M`.

In `dockerfiles/tent/Dockerfile`, replace the block

```dockerfile
# PHP runtime limits for Tent (uploads)
RUN printf 'upload_max_filesize=20M\npost_max_size=25M\n' > /usr/local/etc/php/conf.d/tent.ini
```

with

```dockerfile
# PHP runtime settings: production ini (display_errors off, log_errors on,
# upload limits), shipped from the tent-assets context.
COPY --from=tent-assets php/tent.ini /usr/local/etc/php/conf.d/tent.ini
```

Keep it before `USER app`, so the file is written as root and stays world-readable (0644; set `--chmod=0644` on the `COPY` if the build context's mode could differ). The `tent-assets` context (`dockerfiles/tent`) is already wired up in the `Makefile` (`BUILD_CONTEXTS`) and in `scripts/build_docker_image.sh` (`EXTRA_BUILD_CONTEXT`), so no pipeline change is needed.

## Files to Change
- `dockerfiles/tent/php/tent.ini` — new: production PHP settings.
- `dockerfiles/tent/Dockerfile` — replace the `printf` with a `COPY --from=tent-assets`.
