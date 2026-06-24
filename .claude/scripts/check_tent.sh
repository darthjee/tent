#!/usr/bin/env bash
set -euo pipefail
set -x

docker compose run --rm tent_tests composer tests
docker compose run --rm tent_tests composer lint
