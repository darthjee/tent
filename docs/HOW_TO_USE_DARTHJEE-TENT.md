# How to Use darthjee/tent

**Minimum version:** [0.9.1](https://github.com/darthjee/tent/releases/tag/0.9.1)

[Tent](https://github.com/darthjee/tent) is a PHP-based reverse proxy and static file server distributed as a Docker image. It acts as the single entry point for applications that combine a backend API and a frontend — routing, caching, and serving files through a simple PHP configuration layer.

---

## Table of Contents

- [Quick Start with Docker](./tent/quick-start.md) — Minimal `docker-compose.yml` snippet to pull and run the Tent image with its two required volume mounts.
- [Configuration Folder Layout](./tent/configuration-folder-layout.md) — How the `configuration/` folder and its `configure.php` entry point are structured.
- [Defining Rules](./tent/defining-rules.md) — The three parts of a rule (`handler`, `matchers`, `middlewares`) and the available matcher types.
- [Request Handlers](./tent/request-handlers.md) — `default_proxy`, `proxy`, and `static` handlers, their options, and which one to use for a given scenario.
- [Host Header and Why It Matters](./tent/host-header.md) — Why the `Host` header must be rewritten when proxying, and how `default_proxy` handles it automatically.
- [Middlewares](./tent/middlewares.md) — All built-in middlewares (`FileCacheMiddleware`, `CacheCleanupMiddleware`, `SetHeadersMiddleware`, `RenameHeaderMiddleware`, `SetPathMiddleware`, `RedirectMiddleware`) with configuration examples.
- [Cache Configuration](./tent/cache-configuration.md) — Enabling, disabling, customizing, and bypassing the `default_proxy` file cache, plus manual `FileCacheMiddleware` setup.
- [Frontend Dev Mode Flip](./tent/frontend-dev-mode.md) — Using an environment variable to switch between proxying a live dev server and serving pre-built static files.
- [Static Files](./tent/static-files.md) — Where to place static assets and how to share a build-output volume with Tent.
- [Complete Example Layout](./tent/complete-example.md) — A full project layout combining `docker-compose.yml`, configuration, and rule files.
- [Extending Tent](./tent/extending-tent.md) — Mounting a custom `loader.php` to add matchers, middlewares, or handlers, and testing them with the `darthjee/tent-test` image.
- [Reference](./tent/reference.md) — Quick-reference tables for container paths, handlers, middlewares, cache matchers, and rule matchers.
