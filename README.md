# Tent

Tent is an intelligent PHP-based proxy server that can route requests to backend services, serve cached responses, or deliver static files directly - all based on configuration.

[![Build Status](https://circleci.com/gh/darthjee/tent.svg?style=shield)](https://circleci.com/gh/darthjee/tent)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/c8849c295a394af4ba34adaf979f811d)](https://app.codacy.com/gh/darthjee/tent/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)

![tent](https://raw.githubusercontent.com/darthjee/tent/master/tent.png)

## Overview

Tent is designed to sit in front of your services and intelligently handle incoming HTTP requests. It can act as a reverse proxy, cache layer, or static file server - making it ideal for optimizing resource usage and improving response times.

## How It Works

Tent uses Apache with PHP to process all incoming requests through a centralized entry point:

1. **Request Routing**: Apache's `.htaccess` rewrites all requests to `index.php`
2. **Request Processing**: The PHP application analyzes the request and configuration
3. **Action Selection**: Based on configuration, Tent will:
   - **Proxy Mode**: Forward requests to configured backend servers
   - **Cache Mode**: Serve cached responses (future feature)
   - **Static Mode**: Serve static files directly (future feature)

## Docker Image

Tent is available as a Docker image: `darthjee/tent` *(coming soon)*

## Current Status

Tent is in active development. Currently implemented:

- ✅ Basic proxy functionality
- ✅ Request routing and matching
- ✅ Header forwarding
- ✅ Static file serving (serves files from a directory)
- ✅ Single file serving (always serves the same file)
- ⏳ Configuration system (in progress)
- ⏳ Response caching (planned)

### Error Responses (403/404)

Currently, 404 (Not Found) and 403 (Forbidden) responses return a simple default body. In the future, Tent will support custom bodies or templates for these responses, allowing more complex or branded error pages.

## Architecture

```
Client Request
      ↓
   Apache (.htaccess rewrite)
      ↓
   index.php
      ↓
RequestProcessor
      ↓
 ┌────────────┬──────────┬──────────────┬───────────┬──────────┐
 ↓            ↓          ↓              ↓           ↓
Proxy     Cache     StaticFile     SingleFile   Error
Handler   Handler   Handler        Handler      Handler
                                            ┌─────────────┐
                                            ↓             ↓
                                      404 Not Found   403 Forbidden
```


## Development

To develop Tent, you will run the main Tent application (in the source/source directory) along with three auxiliary services:

- **Backend (api_dev):** A simple PHP backend with endpoints (currently /persons).
- **Frontend (frontend_dev):** A React frontend, served by Vite in development mode.
- **phpMyAdmin (api_dev_phpmyadmin):** For managing and inserting data into the backend database.

### How requests are routed

Tent is configured so that backend requests are proxied to the backend service. Frontend requests depend on the `FRONTEND_DEV_MODE` environment variable:

- If `FRONTEND_DEV_MODE=true`, frontend requests are proxied to the Vite development server (hot reload, etc).
- If `FRONTEND_DEV_MODE=false`, the frontend is served statically from the built files (as in production).

```
Browser
   ↓
Tent (index.php)
   ↓
 ┌───────────────┬────────────────────┐
 │               │                    │
Backend       Frontend (React)   Static Files
 (api_dev)    (frontend_dev)     (frontend/dist)
   ↑               ↑                    ↑
   │               │                    │
phpMyAdmin   (Vite dev server)   (Served by Tent)
```

Depending on FRONTEND_DEV_MODE:

- If true: frontend requests → Vite dev server (hot reload)
- If false: frontend requests → static files from build

### Docker Volumes

- **Static files:** The static files are mounted from `frontend/dist` into the Tent container, so the built frontend is served in production mode.
- **Configuration:** The `docker_volumes/configuration` directory is mounted into the Tent app for configuration. The shipped code does not include a configuration; users are expected to provide their own to define proxy rules.

```
Host Directory                →   Container Path
----------------------------------------------------------
./source                      →   /home/app/app
./dev/frontend/dist           →   /home/app/app/source/static
./docker_volumes/vendor       →   /home/app/app/vendor
./docker_volumes/configuration→   /home/app/app/source/configuration
./dev/api                     →   /home/app/app (for api_dev)
./docker_volumes/mysql_data   →   /var/lib/mysql (for api_dev_mysql)
./docker_volumes/node_modules →   /home/node/app/node_modules (for frontend_dev)
```

See `docker-compose.yml` for details on service setup and volume mounts.

## Contributing

Contributions are welcome! Please feel free to submit pull requests or open issues.

## License

See [LICENSE](LICENSE) file for details.
