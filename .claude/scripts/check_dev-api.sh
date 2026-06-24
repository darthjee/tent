#!/usr/bin/env bash
set -euo pipefail
set -x

docker compose run --rm api_dev composer tests
docker compose run --rm api_dev composer lint
