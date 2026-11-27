# Dockerfile step and build-context wiring
The `tent` build context is `source/`, so bring the new files in through a named build context, following the `tent-test` precedent (`tent-test-assets=dockerfiles/tent-test`).

- `scripts/build_docker_image.sh`, `image_config()` `tent` case: add `EXTRA_BUILD_CONTEXT="tent-assets=dockerfiles/tent"`. `buildx_base_cmd()` already appends `--build-context` when this variable is set.
- `Makefile` `build` target: it runs a plain `docker build -f $(DOCKER_FILE) source ...`. Add `--build-context tent-assets=dockerfiles/tent`, either unconditionally (unused named contexts are harmless for the dev images; verify) or through a variable that defaults to it. `make build` must keep working for `dev_tent` and must work for `MOD= make build` (the `tent` image).
- `dockerfiles/tent/Dockerfile`: add a **new, dedicated** step **before `USER app`**, leaving the existing `;`-chained dependency `RUN` untouched:

  ```dockerfile
  COPY --from=tent-assets apache/00-tent-access-log.conf /etc/apache2/conf-enabled/00-tent-access-log.conf
  COPY --from=tent-assets apache/envvars-tent.sh /tmp/envvars-tent.sh
  RUN set -eu; \
      cat /tmp/envvars-tent.sh >> /etc/apache2/envvars; \
      rm /tmp/envvars-tent.sh; \
      mkdir -p /etc/apache2/tent.d; \
      apache2ctl -t
  ```

  `apache2ctl -t` sources `envvars` (so the variable resolves to `safe`) and fails the build on a broken config. Keep it inside the same `set -eu` step.

## Files to Change
- `dockerfiles/tent/Dockerfile` — new COPY + RUN before `USER app`.
- `scripts/build_docker_image.sh` — `EXTRA_BUILD_CONTEXT` for `tent`.
- `Makefile` — `build` target passes the `tent-assets` build context.
