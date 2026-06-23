#!/usr/bin/env bash
set -euo pipefail
set -x

docker compose run --rm frontend_dev npm test
docker compose run --rm frontend_dev npm run lint
