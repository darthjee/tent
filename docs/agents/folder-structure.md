# Folder Structure

## Project Root

| Directory / File | Description |
|-----------------|-------------|
| `source/` | Tent PHP source code, unit/integration tests, and Composer config. |
| `dev/` | Dev application used to test Tent: mock backend API and React/Vite frontend. |
| `dockerfiles/` | Dockerfiles for all container images used by the project and CI. |
| `docker_volumes/` | Directories mounted as Docker volumes at runtime (cache, DB data, uploads, etc.). |
| `docs/` | Developer and agent documentation. |
| `scripts/` | Shell scripts for building, releasing, and bumping versions. |
| `docker-compose.yml` | Defines all services (Tent, dev API, frontend, HTTPBin, phpMyAdmin). |
| `Makefile` | Convenience targets: `make build`, `make dev`, etc. |
| `phpcs.xml` | PHP CodeSniffer config (PSR-12 enforcement). |
| `codacity.yml` | Codacy code-quality configuration. |
| `AGENTS.md` | Shared instructions for AI agents (Claude, Copilot, etc.). |
| `README.md` | Project overview and quick-start guide. |

## `source/`

| Subdirectory / File | Description |
|--------------------|-------------|
| `source/` | PHP class files — the Tent library itself. |
| `tests/` | PHPUnit tests (unit and integration). |
| `composer.json` | PHP dependency manifest. |
| `phpunit.xml` | PHPUnit configuration. |
| `phpmd.xml` / `phpmd_unusedcode.xml` | PHPMD complexity/unused-code rules. |

## `dev/`

| Subdirectory | Description |
|-------------|-------------|
| `api/` | Mock backend API (PHP/MySQL) used during development and integration tests. |
| `frontend/` | React/Vite frontend served through Tent during development. |

## `dockerfiles/`

| Subdirectory | Description |
|-------------|-------------|
| `tent/` | Production image for the Tent proxy. |
| `dev_tent/` | Development image with test tooling included. |
| `dev_tent-base/` | Base layer for the dev image. |
| `circleci_tent-base/` | Base image for CI jobs. |

## `docker_volumes/`

| Subdirectory | Description |
|-------------|-------------|
| `cache/` | File cache written by `FileCacheMiddleware`. |
| `configuration/` | Tent configuration files mounted at runtime. |
| `mysql_data/` | MySQL data directory for the dev API database. |
| `photos/` | Uploaded photos for the dev application. |
| `vendor/` | Composer vendor directory (shared between host and container). |
| `node_modules/` | npm packages (shared between host and container). |
