# Infra Plan: Ship darthjee/scripts 0.12.0 deploy_frontend.sh in the tent image

Main plan: [plan.md](plan.md)

## Overview
Two changes, both in infra's scope:

1. The `dockerfiles/tent/Dockerfile` `scripts` stage moves from the amd64-pinned 0.9.0 to the multi-arch 0.12.0.
2. `scripts/build_docker_image.sh` gains a tent-only deploy-tooling check. It runs wherever `check_image` runs: `check`, `check-digest` and `verify`, so in CI it covers `release-digest` and `verify-tent-{linux,macos}` on both arches.

No packages are added. The version bump (1.1.0) and the tag are out of scope; the maintainer does them.

## Context
- `darthjee/scripts:0.12.0` is published for `linux/amd64` and `linux/arm64`. The script is still at `/home/scripts/sbin/deploy_frontend.sh`.
- `darthjee/tent:1.0.3` already has `ssh` (openssh-client, recommended by `git`), `rsync`, `bash` and `find`.
- 0.12.0 `usage()` writes to **stderr** and the script exits 1 when called with no action. The actions line is exactly:
  `Actions: generate_key_file build generate_folder copy_files upload link run release cleanup`
- `generate_key_file` writes `$HOME/ssh_key` (mode 600). The image runs as `app` with `HOME=/home/app`, owned by `app`.
- `check_image()` today runs only the PHP contract (`--entrypoint php`) and, for `dev_tent-base`, `composer --version`. Per-image extras are toggled by variables set in `image_config()` (`CHECK_EXTENSIONS`, `CHECK_COMPOSER`).

## Implementation Steps

### Step 1 — Dockerfile: multi-arch scripts stage
In `dockerfiles/tent/Dockerfile`:

- Delete the 4-line comment above the `scripts` stage, which explains the 0.9.0 amd64 pin and mentions #301.
- Replace `FROM --platform=linux/amd64 darthjee/scripts:0.9.0 AS scripts` with `FROM darthjee/scripts:0.12.0 AS scripts`.
- Replace `FROM php:8.4-apache as base` with `FROM php:8.4-apache AS base`.
- Leave the `COPY --from=scripts /home/scripts/sbin/deploy_frontend.sh /usr/sbin/` line and the `apt-get install` list unchanged: no new packages.

### Step 2 — Tent-only deploy-tooling check
In `scripts/build_docker_image.sh`:

1. In `image_config()`, initialise `CHECK_DEPLOY=""` next to `CHECK_EXTENSIONS`/`CHECK_COMPOSER`, and set `CHECK_DEPLOY=true` in the `tent)` branch only. Update the comment ("Per-image check extras").
2. Add a bash heredoc `CHECK_DEPLOY_SH`, next to `CHECK_PHP` and using the same `read -r -d '' ... || true` pattern, with a short comment explaining the contract. It must run without `set -e`, so failures are collected and reported together:

   ```bash
   failures=()

   for cmd in ssh rsync bash; do
     command -v "$cmd" >/dev/null 2>&1 || failures+=("command not on PATH: $cmd")
   done

   # No action: 0.12.0 prints usage on stderr and exits 1 (expected, not a failure).
   output="$(deploy_frontend.sh 2>&1)"
   status=$?
   [ "$status" -eq 1 ] || failures+=("deploy_frontend.sh without action: expected exit 1, got $status")
   expected="Actions: generate_key_file build generate_folder copy_files upload link run release cleanup"
   grep -qxF "$expected" <<<"$output" || failures+=("deploy_frontend.sh usage does not list: $expected")

   # Smoke test as the image's default user: the key lands in a writable $HOME with mode 600.
   if SSH_PRIVATE_KEY=dummy deploy_frontend.sh generate_key_file; then
     mode="$(stat -c %a "$HOME/ssh_key" 2>/dev/null)"
     [ "$mode" = "600" ] || failures+=("\$HOME/ssh_key: expected mode 600, got ${mode:-missing}")
   else
     failures+=("deploy_frontend.sh generate_key_file failed (HOME=$HOME, user=$(id -un))")
   fi

   if [ "${#failures[@]}" -gt 0 ]; then
     printf 'Deploy check FAILED:\n' >&2
     printf '  - %s\n' "${failures[@]}" >&2
     exit 1
   fi

   echo "Deploy check OK ($(id -un))"
   ```

3. In `check_image()`, after the composer block, add:

   ```bash
   if [[ -n "$CHECK_DEPLOY" ]]; then
     docker run --rm --platform "$PLATFORM" \
       --entrypoint bash \
       "$ref" \
       -c "$CHECK_DEPLOY_SH"
   fi
   ```

   No `--user` flag: the check deliberately runs as the image's default user (`app`). `--rm` makes sure the dummy key never persists.

The `help` text needs no change; `check`'s behaviour is described per image elsewhere. Don't touch `tent-test`/`dev_tent`/`dev_tent-base`: they leave `CHECK_DEPLOY` empty.

## Files to Change
- `dockerfiles/tent/Dockerfile`: multi-arch `scripts:0.12.0` stage, remove the pin comment, `as` → `AS`.
- `scripts/build_docker_image.sh`: `CHECK_DEPLOY` toggle in `image_config()`, `CHECK_DEPLOY_SH` heredoc, extra `docker run` in `check_image()`.

## CI Checks
- `scripts/`: `bash -n scripts/build_docker_image.sh` and `shellcheck scripts/build_docker_image.sh`. The check heredoc can be linted by extracting it to a temp file and running `shellcheck -s bash`.
- Local verify, from the repo root, on both arches (QEMU/buildx needed for the non-native one):
  - `./scripts/build_docker_image.sh verify tent amd64 local`
  - `./scripts/build_docker_image.sh verify tent arm64 local`

  Both must print `Image check OK` and `Deploy check OK (app)`.
- Negative sanity check (not committed): temporarily point the stage back at `darthjee/scripts:0.9.0` and confirm the deploy check fails, because 0.9.0 exits 0 with no usage. Then revert.
- CI jobs: `verify-tent-linux` / `verify-tent-macos` (branch, after `all_checks`). On tags, `release-digest` (tent, amd64/arm64) runs `ci-check-digest-tent`.

## Notes
- The plain-version `check tent <arch> <version>` against the published `darthjee/tent:1.0.3` will now **fail** the deploy check, because it ships 0.9.0. That's expected; only images built from this change onwards satisfy it.
- `tent-test` isn't built from the tent image, so it's unaffected; `verify-tent-test-*` should stay green.
- The `VERSION` bump to 1.1.0 (a minor bump for the `deploy_frontend.sh` behaviour changes) and the release tag are done by the maintainer after merge. Do **not** touch `Makefile` `VERSION`.
- The user-facing `deploy_frontend.sh` guide is tracked in darthjee/docker#248; no tent docs change here.
