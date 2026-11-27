# CHECK_ACCESS_LOG image check
Add an access-log behaviour check to `check_image`, mirroring `CHECK_DEPLOY` / `CHECK_DEPLOY_SH`, so it runs from `verify` (every push, amd64 + arm64) and from `check-digest` (tags).

- `image_config()` `tent` case: `CHECK_ACCESS_LOG=true`.
- Define `CHECK_ACCESS_LOG_SH` (a `read -r -d '' ... <<'SH'` block like `CHECK_DEPLOY_SH`). It runs inside the container as the default user (`app`), with no `--user`:
  1. start `apache2-foreground > /tmp/out 2>&1 &`;
  2. wait for readiness by polling `curl -s -o /dev/null http://localhost/` for a few seconds, not a fixed sleep;
  3. `curl -s -o /dev/null -H 'Referer: http://x/?ref=LEAK' 'http://localhost/probe?token=SMOKE'`;
  4. pick the access line (the one containing `/probe`), then assert according to `$EXPECT`:
     - `safe`: contains `"GET /probe HTTP/1.1"` and a numeric `%D` field before the quoted user agent; contains neither `SMOKE` nor `LEAK`;
     - `combined`: contains `?token=SMOKE` and `http://x/?ref=LEAK`;
     - `dropin`: contains the drop-in's marker (see below) and not `SMOKE`;
  5. collect failures into an array and exit 1 with an `Access-log check FAILED:` list, in the same style as the deploy check. Always stop Apache before exiting.
- In `check_image`, when `CHECK_ACCESS_LOG` is set, run the script four times with `docker run --rm --platform "$PLATFORM" --entrypoint bash "$ref" -c "$CHECK_ACCESS_LOG_SH"`:
  - `-e EXPECT=safe`, `TENT_ACCESS_LOG_FORMAT` not passed;
  - `-e EXPECT=safe -e TENT_ACCESS_LOG_FORMAT=`, set to an empty string;
  - `-e EXPECT=combined -e TENT_ACCESS_LOG_FORMAT=combined`;
  - `-e EXPECT=dropin -e TENT_ACCESS_LOG_FORMAT=combined` with a host temp dir (`mktemp -d`, chmod so uid 1000 can read it) holding `tent.d/zz-test.conf`, which contains `LogFormat "DROPIN %m %U %>s" combined`, mounted as `-v "$dir:/etc/apache2/tent.d:ro"`. Clean up the temp dir with a `trap`.
- No static grep of the effective Apache configuration (dropped by the issue).
- The invalid-preset case is deliberately not part of this matrix.

## Files to Change
- `scripts/build_docker_image.sh` — `CHECK_ACCESS_LOG` flag, `CHECK_ACCESS_LOG_SH` script, the four `docker run` invocations in `check_image`.
