#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

README="$ROOT_DIR/README.md"
MAKEFILE="$ROOT_DIR/Makefile"
COMPOSER_JSON="$ROOT_DIR/source/composer.json"
HOW_TO_USE="$ROOT_DIR/docs/HOW_TO_USE_DARTHJEE-TENT.md"

if [[ $# -ge 1 ]]; then
  VERSION="$1"
  if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Error: version must be in format X.Y.Z" >&2
    exit 1
  fi
else
  CURRENT=$(grep -oE '\*\*Current Version:\*\* \[[0-9]+\.[0-9]+\.[0-9]+\]' "$README" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')
  if [[ -z "$CURRENT" ]]; then
    echo "Error: could not read current version from $README" >&2
    exit 1
  fi
  MAJOR="${CURRENT%%.*}"
  REST="${CURRENT#*.}"
  MINOR="${REST%%.*}"
  PATCH="${REST#*.}"
  VERSION="${MAJOR}.${MINOR}.$((PATCH + 1))"
fi

MAJOR="${VERSION%%.*}"
REST="${VERSION#*.}"
MINOR="${REST%%.*}"
PATCH="${REST#*.}"
NEXT_VERSION="${MAJOR}.${MINOR}.$((PATCH + 1))"

sed -i '' \
  "s|\*\*Current Version:\*\* \[.*\](https://github.com/darthjee/tent/releases/tag/.*)|**Current Version:** [$VERSION](https://github.com/darthjee/tent/releases/tag/$VERSION)|" \
  "$README"

sed -i '' \
  "s|\*\*Minimum version:\*\* \[.*\](https://github.com/darthjee/tent/releases/tag/.*)|**Minimum version:** [$VERSION](https://github.com/darthjee/tent/releases/tag/$VERSION)|" \
  "$HOW_TO_USE"

sed -i '' \
  "s|\*\*Next Release:\*\* \[.*\](https://github.com/darthjee/tent/compare/.*)|**Next Release:** [$NEXT_VERSION](https://github.com/darthjee/tent/compare/$VERSION...main)|" \
  "$README"

sed -i '' \
  "s|^VERSION?=.*|VERSION?=$VERSION|" \
  "$MAKEFILE"

sed -i '' \
  "s|\"version\": \".*\"|\"version\": \"$VERSION\"|" \
  "$COMPOSER_JSON"

echo "Bumped to $VERSION (next release: $NEXT_VERSION)"
