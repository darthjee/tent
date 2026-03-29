# AGENTS.md

Shared guidance for AI agents (Claude Code, GitHub Copilot, etc.) working in this repository.

## What Is Tent

Tent is a PHP-based intelligent reverse proxy server that routes HTTP requests to backend services, caches responses, or serves static files based on configuration rules. It runs on Apache with PHP inside Docker containers.

## Documentation

Agent-specific documentation lives under [`docs/agents/`](docs/agents/). Developer-facing guides live under [`docs/`](docs/).

### Agent docs (`docs/agents/`)

| File | Contents |
|------|----------|
| [Architecture](docs/agents/architecture.md) | Source layout, key components, configuration patterns, class loading, dev API/frontend, testing conventions. |
| [Runtime Flow](docs/agents/flow.md) | Entry point, request lifecycle, execution path from Apache to response. |
| [Dev Application](docs/agents/dev-app.md) | Sample app used to test Tent: backend (PHP/MySQL), frontend (React/Vite), tests, CI jobs, and Docker Compose layout. |
| [Contributing](docs/agents/contribute.md) | Explanation on how to contribute, commit and open PRs |
| [Plans](docs/agents/plans/) | Implementation plans for ongoing or upcoming work. |
| [Issues](docs/agents/issues/) | Detailed specs for open GitHub issues. |

### Developer docs (`docs/`)

| File | Contents |
|------|----------|
| [Request Handlers](docs/request-handlers.md) | Differences between `default_proxy`, `proxy`, and `static`, including options and examples. |
| [Creating Middlewares](docs/creating-middlewares.md) | How to build custom middlewares; interface, short-circuiting, built-in middlewares. |
| [FileCacheMiddleware Matchers](docs/file-cache-middleware-matchers.md) | Matcher configuration for `FileCacheMiddleware`; migration from deprecated `httpCodes`. |
| [Adding Request Matchers](docs/adding-request-matchers.md) | How to add new `RequestMatcher` classes. |

### Issues (`docs/agents/issues/`)

Each file documents a GitHub issue. Naming convention:

    docs/agents/issues/<github_issue_id>_<issue_name>.md

Example: `docs/agents/issues/42_add-negative-matcher.md` for issue #42.

### Plans (`docs/agents/plans/`)

Each plan is a directory named after the GitHub issue ID and topic:

    docs/agents/plans/<github_issue_id>_<topic>/plan.md

Example: `docs/agents/plans/42_add-negative-matcher/plan.md` for issue #42.

## Engineering Standards

- **PHP**: PSR-12 code style, enforced via `composer lint` / `composer lint:fix`.
- **JavaScript/React**: ESLint, enforced via `npm run lint` / `npm run lint_fix`.
- **PHP tests**: PHPUnit — unit and integration tests under `source/tests/`.
- **Frontend tests**: Jasmine — specs under `dev/frontend/spec/`.
- **PHP dependencies**: Composer.
- **JS dependencies**: npm.
- **Atomic commits**: one logical change per commit. Tests and implementation go in the same commit; documentation updates (issue files, plan files) are separate commits.
- **Small PRs**: one PR per GitHub issue. For large features, break into sub-issues and open one PR per sub-issue.

## Commands

**Always use Docker Compose (v2 syntax). Never run commands directly on the host.**

Use `docker compose run` for one-off commands and `docker compose exec` only when the service is already running via `docker compose up`.

### Backend (PHP)
```bash
docker compose run --rm tent_tests composer tests              # All tests
docker compose run --rm tent_tests composer tests:unit        # Unit tests only
docker compose run --rm tent_tests composer tests:integration # Integration tests
docker compose run --rm tent_tests vendor/bin/phpunit tests/unit/path/to/TestFile.php  # Single test file
docker compose run --rm tent_tests composer lint              # Check code style
docker compose run --rm tent_tests composer lint:fix          # Auto-fix code style
docker compose run --rm tent_tests composer docs              # Generate PHPDoc
docker compose run --rm tent_tests composer complexity        # PHPMD analysis
```

### Frontend (React/Vite)
```bash
docker compose run --rm frontend_dev npm test          # Jasmine tests
docker compose run --rm frontend_dev npm run lint      # ESLint
docker compose run --rm frontend_dev npm run lint_fix  # Auto-fix
docker compose run --rm frontend_dev npm run build     # Build static files for production
```

### Dev API
```bash
docker compose run --rm api_dev composer tests
docker compose run --rm api_dev php bin/migrate_databases.php  # Run DB migrations
```

### Startup
```bash
make build && docker compose up   # Full setup
make dev                          # Interactive test shell
```

## Services

| Service | Port | Purpose |
|---------|------|---------|
| `tent_app` | 8080 | Main Tent proxy |
| `api_dev` | 8040 | Mock backend API |
| `frontend_dev` | 8030 | Vite dev server |
| `api_dev_phpmyadmin` | 8050 | DB management UI |
| `tent_httpbin` | 3060 | HTTPBin for testing |

## Language

All code, comments, and documentation must be in **English**.
