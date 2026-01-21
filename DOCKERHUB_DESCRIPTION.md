# Tent

Tent is an intelligent PHP-based proxy server that can route requests to backend services, serve cached responses, or deliver static files directly - all based on configuration.

[![Build Status](https://circleci.com/gh/darthjee/tent.svg?style=shield)](https://circleci.com/gh/darthjee/tent)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/c8849c295a394af4ba34adaf979f811d)](https://app.codacy.com/gh/darthjee/tent/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)

![tent](https://raw.githubusercontent.com/darthjee/tent/master/tent.png)

## Overview

Tent is designed to sit in front of your services and intelligently handle incoming HTTP requests. It can act as a reverse proxy, cache layer, or static file server - making it ideal for optimizing resource usage and improving response times.

## Usage

## How It Works

Tent uses Apache with PHP to process all incoming requests through a centralized entry point:

1. **Request Routing**: Apache's `.htaccess` rewrites all requests to `index.php`
2. **Request Processing**: The PHP application analyzes the request and configuration
3. **Action Selection**: Based on configuration, Tent will:
   - **Proxy Mode**: Forward requests to configured backend servers
   - **Cache Mode**: Serve cached responses (future feature)
   - **Static Mode**: Serve static files directly (future feature)

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
Middleware (chain)
      ↓
 ┌────────────┬──────────┬──────────────┬───────────┬──────────┐
 ↓            ↓          ↓              ↓           ↓
Proxy     Cache     StaticFile     SingleFile   Error
Handler   Handler   Handler        Handler      Handler
                                            ┌─────────────┐
                                            ↓             ↓
                                      404 Not Found   403 Forbidden
```