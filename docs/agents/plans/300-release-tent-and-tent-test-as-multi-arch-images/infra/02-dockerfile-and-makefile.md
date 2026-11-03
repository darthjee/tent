# Dockerfile pin and Makefile targets

## Dockerfile

`dockerfiles/tent/Dockerfile`, line 1:

```dockerfile
FROM darthjee/scripts:0.9.0 as scripts
```

becomes:

```dockerfile
FROM --platform=linux/amd64 darthjee/scripts:0.9.0 AS scripts
```

Add a comment above it explaining why:
- `darthjee/scripts:0.9.0` is amd64-only, and only the plain shell script `deploy_frontend.sh` is copied out of it, so the amd64 stage is safe for both arch variants;
- #301 replaces the line with the multi-arch `0.12.0` and drops the pin.

Leave the rest of the Dockerfile unchanged (`deploy_frontend.sh` must stay byte-identical).

Then delete `scripts/prepare_scripts_image.sh`. Its only caller is the `build-and-release-image` job, which step 03 removes.

## Makefile

- **Remove** `ci-build-tent`, `ci-check-tent`, `ci-release-tent`, `ci-build-tent-test`, `ci-check-tent-test` and `ci-release-tent-test`.
- **Add**, mirroring the existing `*-base` targets but using `$(VERSION)`:

  ```make
  ci-push-digest-tent:
  	./scripts/build_docker_image.sh push-digest tent $(ARCH) $(VERSION)

  ci-check-digest-tent:
  	./scripts/build_docker_image.sh check-digest tent $(ARCH) $(VERSION)

  ci-merge-tent:
  	./scripts/build_docker_image.sh merge tent $(VERSION)

  ci-verify-tent:
  	./scripts/build_docker_image.sh verify tent $(ARCH) $(VERSION)
  ```

  Add the same four targets for `tent-test`: `ci-push-digest-tent-test`, `ci-check-digest-tent-test`, `ci-merge-tent-test` and `ci-verify-tent-test`.
- Update `.PHONY` to match: remove the old names and add the eight new ones.
- `*-base` targets keep using `$(BASE_VERSION)`, so a `VERSION=...` passed by CI is ignored for them.
- Local `build`, `build-base` and every other target are unchanged.

## Files to Change

- `dockerfiles/tent/Dockerfile`: pin the scripts stage to `linux/amd64` and use uppercase `AS`.
- `scripts/prepare_scripts_image.sh`: delete.
- `Makefile`: remove the per-arch `ci-*-tent(-test)` targets, add the push-digest / check-digest / merge / verify targets for tent and tent-test, and update `.PHONY`.
