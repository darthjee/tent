#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 <version>" >&2
  exit 1
fi

VERSION="$1"

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Error: version must be in format X.Y.Z" >&2
  exit 1
fi

MAJOR="${VERSION%%.*}"
REST="${VERSION#*.}"
MINOR="${REST%%.*}"
PATCH="${REST#*.}"

NEXT_VERSION="${MAJOR}.${MINOR}.$((PATCH + 1))"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

README="$ROOT_DIR/README.md"
COMPOSER_JSON="$ROOT_DIR/source/composer.json"

sed -i '' \
  "s|\*\*Current Version:\*\* \[.*\](https://github.com/darthjee/tent/releases/tag/.*)|**Current Version:** [$VERSION](https://github.com/darthjee/tent/releases/tag/$VERSION)|" \
  "$README"

sed -i '' \
  "s|\*\*Next Release:\*\* \[.*\](https://github.com/darthjee/tent/compare/.*)|**Next Release:** [$NEXT_VERSION](https://github.com/darthjee/tent/compare/$VERSION...main)|" \
  "$README"

sed -i '' \
  "s|\"version\": \".*\"|\"version\": \"$VERSION\"|" \
  "$COMPOSER_JSON"

echo "Bumped to $VERSION (next release: $NEXT_VERSION)"
