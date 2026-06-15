#!/usr/bin/env bash
set -euo pipefail

ARCH="${1:-arm64}"
DOCKERFILE="dockerfiles/tent/Dockerfile"

VERSION=$(sed -n 's/FROM darthjee\/scripts:\([^ ]*\) as.*/\1/p' "$DOCKERFILE")
docker pull "darthjee/scripts:${VERSION}-${ARCH}"
docker tag "darthjee/scripts:${VERSION}-${ARCH}" "darthjee/scripts:${VERSION}"
