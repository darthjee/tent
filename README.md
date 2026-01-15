# Tent

Tent is an intelligent PHP-based proxy server that can route requests to backend services, serve cached responses, or deliver static files directly - all based on configuration.

[![Build Status](https://circleci.com/gh/darthjee/tent.svg?style=shield)](https://circleci.com/gh/darthjee/tent)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/35480a5e82e74ff7a0186697b3f61a4b)](https://app.codacy.com/gh/darthjee/tent/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)

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
   ┌──────────┬──────────┬──────────┐
   ↓          ↓          ↓          ↓
Proxy     Cache     Static     404
Handler   Handler   Handler   Handler
```

## Contributing

Contributions are welcome! Please feel free to submit pull requests or open issues.

## License

See [LICENSE](LICENSE) file for details.
