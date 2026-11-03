# Issue: Ship darthjee/scripts 0.12.0 deploy_frontend.sh in the tent image

## Description

Part of Epic #298. Builds on #300 (multi-arch `tent` / `tent-test` release, merged in #303).

The `darthjee/tent` image ships `deploy_frontend.sh` from `darthjee/scripts:0.9.0`, copied into `/usr/sbin/` (`dockerfiles/tent/Dockerfile`). Ward (`darthjee/ward`) will deploy its proxy from CircleCI jobs running in `darthjee/tent:<new version>` and call `deploy_frontend.sh` from `PATH`. Ward needs these 0.12.0 features:

- `SSH_KNOWN_HOSTS` support (host-key pinning with `StrictHostKeyChecking=yes`);
- `set -euo pipefail` and required-variable checks;
- a safe `release` that checks staging exists, tolerates a missing live dir and supports `KEEP_OLD`;
- `upload` that creates the remote folder itself, plus `EXCLUDE_FROM`;
- the `cleanup` action.

This is a prerequisite for the Ward deployment issue (darthjee/ward).

A guide for `deploy_frontend.sh`, including the 0.9.0 → 0.12.0 findings above, is tracked in darthjee/docker#248.

## Problem

`darthjee/scripts:0.9.0` is old: its `deploy_frontend.sh` has no host-key pinning, no fail-fast on missing variables, an unsafe `release`, no `cleanup`, and unquoted remote paths. 0.9.0 is also amd64-only, which forced #300 to pin the `scripts` stage to `linux/amd64`. Projects deploying from the tent image (Ward first) cannot use the 0.12.0 features until tent ships them.

## Expected Behavior

- The image built from the branch (CI `verify-tent` on both arches), and later `darthjee/tent:1.1.0` once tagged: `deploy_frontend.sh` prints the 0.12.0 usage, listing `generate_key_file build generate_folder copy_files upload link run release cleanup`, and exits 1, on both amd64 and arm64.
- `ssh`, `rsync` and `bash` are available in the image, with no packages added to the Dockerfile.
- `generate_key_file`, run as the default user with a dummy `SSH_PRIVATE_KEY`, creates `$HOME/ssh_key` with mode 600.
- The `/var/www/html/` contents are unchanged.

## Solution

- `dockerfiles/tent/Dockerfile`: `FROM --platform=linux/amd64 darthjee/scripts:0.9.0 AS scripts` → `FROM darthjee/scripts:0.12.0 AS scripts`. 0.12.0 is published for both `linux/amd64` and `linux/arm64`, so the amd64 pin added in #300 and its 4-line comment are removed. The script still lives at `/home/scripts/sbin/deploy_frontend.sh`, so the `COPY` line is unchanged. Also normalise `FROM php:8.4-apache as base` → `AS base`.
- No new packages: the tent image already ships everything the script needs. `ssh` (openssh-client, a recommended package of `git`), `rsync` (installed explicitly), `bash` and `find` (Debian base) are all present in `darthjee/tent:1.0.3`. The `PATH` check below is the safety net.
- Extend the image check in `scripts/build_docker_image.sh` with a tent-only check, wired through `image_config`, so `check-digest` and `verify` run it on both arches:
  - `deploy_frontend.sh` with no action prints the 0.12.0 usage, including the full actions list, and exits 1. Exit 1 is the expected result, not a failure.
  - `ssh`, `rsync` and `bash` are on `PATH`.
  - Smoke test: running as the image's default user (`app`), `SSH_PRIVATE_KEY=dummy deploy_frontend.sh generate_key_file` creates `$HOME/ssh_key` with mode 600.

### Out of scope

- The CircleCI `scripts` executor (`darthjee/scripts:0.6.0`, used to push the Docker Hub description).
- `tent-test` and `dev_tent` content: neither ships `deploy_frontend.sh`.
- Installing node/npm for the script's `build` action.
- Copying any other script from `darthjee/scripts`: only `deploy_frontend.sh` is copied.
- The release: the maintainer bumps `VERSION` in the `Makefile` to `1.1.0` (a minor bump, because `deploy_frontend.sh` behaviour changes for existing callers; see Backward compatibility) and pushes the tag separately, after merge. `tent-test` is published from the same tag automatically, with no content change.

### Edge cases

- **`build` needs npm**: the action runs `${BUILD_COMMAND:-npm run build}`, and the tent image has no node or npm. Tent itself doesn't use `deploy_frontend.sh`; it's shipped for other projects that run in the tent image, which must set `BUILD_COMMAND` or skip `build`. Documenting this for image users is left for later.
- **Files under `$HOME`**: `generate_key_file` writes `$HOME/ssh_key` and `$HOME/ssh_known_hosts` (mode 600). The image runs as `app` with `HOME=/home/app` owned by `app`. The smoke test above guards this.
- **CircleCI `checkout`**: jobs using the tent image as their docker executor need `git` and `ssh` for the `checkout` step. Both are present today, and the `PATH` check keeps `ssh` guarded.
- **Recommends drift**: `ssh` only comes in as a recommended package of `git`. If `--no-install-recommends` is ever added, the `PATH` check fails CI instead of shipping an image without `ssh`.

### Backward compatibility

`deploy_frontend.sh` 0.12.0 changes behaviour compared with 0.9.0:

| # | 0.9.0 | 0.12.0 | Impact |
|---|---|---|---|
| 1 | No or unknown action: silent, exit 0 | Usage on **stderr**, exit 1 | Breaking for callers with a typo or no action |
| 2 | Missing variables run anyway | `set -euo pipefail` and `${VAR:?}` fail fast | Breaking for callers relying on empty variables |
| 3 | `SSH_PORT` must be set | Defaults to 22 | Fix |
| 4 | Remote paths unquoted | Remote paths single-quoted: `~` and globs are not expanded remotely | Breaking for callers using e.g. `SSH_REMOTE_DIR=~/www` |
| 5 | Staging dir is `SSH_REMOTE_TEMP_DIR` | On CircleCI, staging is `SSH_REMOTE_TEMP_DIR-$CIRCLE_WORKFLOW_WORKSPACE_ID` | Breaking for callers doing their own ssh against the temp dir |
| 6 | `release` fails if the live dir is missing | Tolerates it; `KEEP_OLD` supported | Fix |
| 7 | `link` is `ln -s SOURCE TARGET` | `ln -sfn`, or `DEPLOY_PATH`-based inside staging | Mostly compatible |

- Consumers pinned to `darthjee/tent:1.0.3` or earlier are unaffected. Consumers on `latest`, or bumping to `1.1.0`, get the new behaviour. Hence the minor version bump.
- Existing projects that call `deploy_frontend.sh` from the tent image will be updated by the maintainer separately; that's out of scope here.
- The image check must capture stderr (`2>&1`) when asserting the usage output.

### Security considerations

- **Deploy tooling in a runtime image**: `darthjee/tent` is also the proxy's runtime image. It already ships `git`, `curl`, `rsync`, `telnet` and `ssh`, so the new script adds negligible attack surface, and no packages are added. Splitting out a dedicated deploy image is out of scope.
- **Host-key checking**: without `SSH_KNOWN_HOSTS` the script uses `StrictHostKeyChecking=no`. Consumers (e.g. Ward) should set `SSH_KNOWN_HOSTS`; tent doesn't enforce it. User-facing docs are tracked in darthjee/docker#248.
- **Private key at rest**: `generate_key_file` writes `$HOME/ssh_key` (mode 600), only in ephemeral CI containers. The image contains no keys. The smoke test uses a dummy key in a throwaway `docker run --rm`, so nothing lands in a layer.
- **Command injection**: 0.12.0 single-quotes remote paths, a hardening over 0.9.0. `COMMAND` for `run` is passed as is by design; the caller owns its quoting.
- **Supply chain**: the stage references the `darthjee/scripts:0.12.0` tag, not a digest. The maintainer owns that repo, so the tag is trusted, and digest bumps would add friction.

## Benefits

- Ward and other projects can deploy from the tent image with host-key pinning, fail-fast checks, safe releases and staging cleanup.
- The `scripts` stage becomes truly multi-arch, removing the amd64 pin workaround from #300.
- CI guards the deploy tooling (`deploy_frontend.sh`, `ssh`, `rsync`, `bash`) on both arches, so a regression can't ship silently.
