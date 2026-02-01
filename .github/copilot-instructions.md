# Copilot Instructions: Project Folder Architecture

This document provides an overview of the main folder structure in this repository to help contributors and Copilot understand the project organization and responsibilities of each directory.

## Folder Structure Overview

```
source/
  ├── source/           # Main application source code (core logic, models, services, etc.)
  ├── tests/            # Unit and integration tests for the application
  ├── vendor/           # Composer dependencies (auto-generated)
  ├── coverage/         # Code coverage reports (auto-generated)
  ├── ...               # Other supporting files (config, docs, etc.)

dev/
  ├── api/              # Development environment for the backend API
  │   ├── composer.json     # PHP dependencies for API
  │   ├── phpcs.xml         # PHP code style configuration
  │   ├── phpunit.xml       # PHPUnit configuration
  │   ├── bin/              # API scripts and binaries
  │   ├── migrations/       # Database migrations
  │   ├── source/           # API source code (may mirror main source/)
  │   ├── tests/            # API-specific tests
  │   ├── vendor/           # API Composer dependencies
  │   └── ...
  ├── frontend/         # Development environment for the frontend
  │   ├── package.json      # Node.js dependencies for frontend
  │   ├── vite.config.js    # Vite build configuration
  │   ├── assets/           # Static assets (images, styles, etc.)
  │   ├── bin/              # Frontend scripts and binaries
  │   ├── spec/             # Frontend tests/specs
  │   └── ...
  └── ...
```

## Directory Responsibilities

- **source/**: Contains the main application logic, core models, services, and supporting files. This is the heart of the project and is used in both development and production.
- **dev/api/**: Provides a development environment for the backend API, including its own dependencies, configuration, and scripts. Useful for local development, testing, and isolated API work.
- **dev/frontend/**: Provides a development environment for the frontend, including its own dependencies, configuration, and scripts. Useful for local development, UI testing, and frontend-specific workflows.

> Note: The `dev/` folder is intended for local development setups and may contain environment-specific overrides or tools that are not part of the main production build.

---

## Running the Project

This project is designed to be run using Docker Compose. All services and development environments are defined in the `docker-compose.yml` file at the root of the repository.

Example:

```
docker-compose exec tent_app composer install
docker-compose exec tent_tests composer tests
docker-compose exec frontend_dev npm install
```

> Do not run commands directly on the host. Always use Docker Compose to ensure the correct environment and dependencies.

## Language Guidelines

- All code, comments, and documentation must be written in **English**.
- Avoid using other languages in code, comments, commit messages, and documentation files.

This ensures consistency and makes the project accessible to a wider audience.

For more details on each folder or to contribute, please refer to the README.md or open an issue.
