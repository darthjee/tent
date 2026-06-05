#!/usr/bin/env bash
set -euo pipefail

show_help() {
  cat <<'EOF'
Usage: scripts/upload_release_package.sh <build|release|help>
EOF
}

build_release_package() {
  if [ -z "${CIRCLE_TAG:-}" ]; then
    echo "Skipping package creation for non-tag build"
    return 0
  fi

  RELEASE_PACKAGE="tent-${CIRCLE_TAG}-source.tar.gz"
  tar -czf "$RELEASE_PACKAGE" -C source/source .

  if [ -n "${BASH_ENV:-}" ]; then
    echo "RELEASE_PACKAGE=$RELEASE_PACKAGE" >> "$BASH_ENV"
  fi
}

upload_release_package() {
  if [ -z "${CIRCLE_TAG:-}" ]; then
    echo "Skipping release package upload for non-tag build"
    return 0
  fi

  if [ -z "${GITHUB_TOKEN:-}" ]; then
    echo "GITHUB_TOKEN is required to upload release assets"
    exit 1
  fi

  if [ -z "${RELEASE_PACKAGE:-}" ]; then
    RELEASE_PACKAGE="tent-${CIRCLE_TAG}-source.tar.gz"
  fi

  RELEASE_RESPONSE=$(curl --silent --show-error \
    -u "x-access-token:${GITHUB_TOKEN}" \
    -H "Accept: application/vnd.github+json" \
    -w '\n%{http_code}' \
    "https://api.github.com/repos/darthjee/tent/releases/tags/${CIRCLE_TAG}")
  RELEASE_STATUS=$(echo "$RELEASE_RESPONSE" | tail -n 1)
  RELEASE_DATA=$(echo "$RELEASE_RESPONSE" | sed '$d')

  if [ "$RELEASE_STATUS" = "404" ]; then
    RELEASE_PAYLOAD=$(python3 - <<'PY'
import json
import os

tag = os.environ["CIRCLE_TAG"]
print(json.dumps({
    "tag_name": tag,
    "name": tag,
    "generate_release_notes": True
}))
PY
)
    RELEASE_DATA=$(curl --silent --show-error --fail \
      -X POST \
      -u "x-access-token:${GITHUB_TOKEN}" \
      -H "Accept: application/vnd.github+json" \
      -d "$RELEASE_PAYLOAD" \
      "https://api.github.com/repos/darthjee/tent/releases")
  elif [ "$RELEASE_STATUS" != "200" ]; then
    echo "Failed to fetch release for tag ${CIRCLE_TAG} (HTTP ${RELEASE_STATUS})"
    echo "$RELEASE_DATA"
    exit 1
  fi

  if [ -z "$RELEASE_DATA" ]; then
    echo "Release data is empty for tag ${CIRCLE_TAG}"
    exit 1
  fi

  RELEASE_ID=$(RELEASE_DATA="$RELEASE_DATA" python3 - <<'PY'
import json
import os

data = json.loads(os.environ["RELEASE_DATA"])
print(data["id"])
PY
)
  UPLOAD_URL=$(RELEASE_DATA="$RELEASE_DATA" python3 - <<'PY'
import json
import os

data = json.loads(os.environ["RELEASE_DATA"])
print(data["upload_url"].split("{")[0])
PY
)

  ASSETS_DATA=$(curl --silent --show-error --fail \
    -u "x-access-token:${GITHUB_TOKEN}" \
    -H "Accept: application/vnd.github+json" \
    "https://api.github.com/repos/darthjee/tent/releases/${RELEASE_ID}/assets")

  EXISTING_ASSET_ID=$(RELEASE_PACKAGE="$RELEASE_PACKAGE" ASSETS_DATA="$ASSETS_DATA" python3 - <<'PY'
import json
import os

name = os.environ["RELEASE_PACKAGE"]
for asset in json.loads(os.environ["ASSETS_DATA"]):
    if asset.get("name") == name:
        print(asset["id"])
        break
PY
)

  if [ -n "$EXISTING_ASSET_ID" ]; then
    curl --silent --show-error --fail \
      -X DELETE \
      -u "x-access-token:${GITHUB_TOKEN}" \
      -H "Accept: application/vnd.github+json" \
      "https://api.github.com/repos/darthjee/tent/releases/assets/${EXISTING_ASSET_ID}"
  fi

  curl --silent --show-error --fail \
    -X POST \
    -u "x-access-token:${GITHUB_TOKEN}" \
    -H "Content-Type: application/gzip" \
    --data-binary @"${RELEASE_PACKAGE}" \
    "${UPLOAD_URL}?name=${RELEASE_PACKAGE}"
}

COMMAND="${1:-help}"
case "$COMMAND" in
  build)
    build_release_package
    ;;
  release)
    upload_release_package
    ;;
  help)
    show_help
    ;;
  *)
    echo "Invalid command: $COMMAND" >&2
    show_help >&2
    exit 1
    ;;
esac
