# Dev API - Dummy Backend Application

This is a lightweight PHP-based backend API application used for development and testing of the Tent proxy server. It provides sample endpoints and demonstrates how requests are routed and handled.

## Overview

The Dev API is a simple PHP backend that mimics a typical RESTful API. All HTTP requests are received by `index.php` and processed by the `RequestHandler` based on configured routes. This application is particularly useful for:

- Testing the Tent proxy functionality
- Demonstrating request routing patterns
- Providing sample data for frontend development
- Testing caching and middleware behavior

## Architecture

### Request Flow

```
HTTP Request
    ↓
Apache (.htaccess rewrite)
    ↓
index.php (Entry Point)
    ↓
RequestHandler
    ↓
Configuration::getConfigurations() - Get registered routes
    ↓
Route Matching - Find matching RouteConfiguration
    ↓
Endpoint Execution - Instantiate and execute endpoint class
    ↓
Response - JSON response with status code and headers
```

### Key Components

1. **index.php**: Entry point that receives all requests via Apache rewrite
2. **Configuration**: Static class that manages route registration
3. **RequestHandler**: Processes incoming requests and matches them to configured endpoints
4. **Route/RouteConfiguration**: Defines route patterns (HTTP method + path)
5. **Endpoint**: Abstract base class for all endpoint handlers
6. **Request/Response**: Models for handling HTTP requests and responses
7. **Person Model**: Sample model demonstrating database integration

### Directory Structure

```
dev/api/
├── bin/                          # Utility scripts
│   ├── create_databases.php      # Database initialization
│   ├── migrate_databases.php     # Run migrations
│   └── wait_for_database.php     # Wait for MySQL to be ready
├── migrations/                   # SQL migration files
│   └── 0001_create_persons.sql   # Example: Create persons table
├── source/                       # Application source code
│   ├── index.php                 # Entry point
│   └── lib/
│       ├── api_dev/              # API application logic
│       │   ├── Configuration.php           # Route registry
│       │   ├── Endpoint.php               # Base endpoint class
│       │   ├── RequestHandler.php         # Request processor
│       │   ├── Route.php                  # Route matching logic
│       │   ├── RouteConfiguration.php     # Route definition
│       │   ├── endpoints/                 # Endpoint implementations
│       │   │   ├── HealthCheckEndpoint.php
│       │   │   └── ListPersonsEndpoint.php
│       │   └── models/                    # Data models
│       │       ├── Request.php
│       │       ├── Response.php
│       │       ├── MissingResponse.php
│       │       └── Person.php
│       └── mysql/                         # Database layer
│           ├── Configuration.php
│           ├── Connection.php
│           ├── ModelConnection.php
│           ├── Migration.php
│           ├── MigrationsProcessor.php
│           └── DatabaseInitializer.php
├── tests/                        # Test files
├── composer.json                 # PHP dependencies
└── phpunit.xml                   # PHPUnit configuration
```

## How It Works

### Route Registration

Routes are registered in `index.php` using the `Configuration::add()` method:

```php
Configuration::add('GET', '/health', HealthCheckEndpoint::class);
Configuration::add('GET', '/persons', ListPersonsEndpoint::class);
```

Each route associates:
- HTTP method (GET, POST, PUT, DELETE, etc.)
- URL path (exact match)
- Endpoint class that handles the request

### Request Processing

1. Apache rewrites all requests to `index.php`
2. `RequestHandler` is instantiated and processes the request
3. Handler iterates through registered `RouteConfiguration` objects
4. First matching route instantiates the corresponding `Endpoint` class
5. Endpoint's `handle()` method processes the request
6. Response is sent back with appropriate headers and status code

### Endpoint Implementation

Endpoints extend the `Endpoint` base class and implement the `handle()` method:

```php
<?php

namespace ApiDev;

class HealthCheckEndpoint extends Endpoint
{
    public function handle()
    {
        $body = json_encode(['status' => 'ok']);
        $headers = ['Content-Type: application/json'];
        
        return new Response($body, 200, $headers);
    }
}
```

## Adding New Endpoints

To add a new endpoint to the Dev API:

### 1. Create the Endpoint Class

Create a new file in `source/lib/api_dev/endpoints/` that extends the `Endpoint` class:

```php
<?php

namespace ApiDev;

class MyNewEndpoint extends Endpoint
{
    public function handle()
    {
        // Your endpoint logic here
        $data = [
            'message' => 'Hello from my new endpoint',
            'timestamp' => time()
        ];
        
        $body = json_encode($data);
        $headers = ['Content-Type: application/json'];
        
        return new Response($body, 200, $headers);
    }
}
```

### 2. Register the Route

Add the route registration in `source/index.php`:

```php
require_once __DIR__ . '/lib/api_dev/endpoints/MyNewEndpoint.php';

// Register the route
Configuration::add('GET', '/my-new-endpoint', MyNewEndpoint::class);
```

### 3. Test the Endpoint

Access your new endpoint through the Dev API service:

```bash
curl http://localhost:8040/my-new-endpoint
```

### Example: Endpoint with Database Access

If your endpoint needs to interact with the database:

```php
<?php

namespace ApiDev;

use ApiDev\Models\Person;

class GetPersonEndpoint extends Endpoint
{
    public function handle()
    {
        // Extract ID from query parameters or path
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            return new Response(
                json_encode(['error' => 'ID required']),
                400,
                ['Content-Type: application/json']
            );
        }
        
        // Use the Person model to query the database
        $connection = Person::getConnection();
        $person = $connection->find($id);
        
        if (!$person) {
            return new Response(
                json_encode(['error' => 'Person not found']),
                404,
                ['Content-Type: application/json']
            );
        }
        
        $body = json_encode($person);
        $headers = ['Content-Type: application/json'];
        
        return new Response($body, 200, $headers);
    }
}
```

## Database Migrations

The Dev API uses a simple SQL-based migration system for managing database schema changes.

### Migration Files

Migration files are stored in the `migrations/` directory and must:
- Have a `.sql` extension
- Use a numbered prefix for ordering (e.g., `0001_`, `0002_`)
- Contain valid SQL statements

Example migration file (`migrations/0001_create_persons.sql`):

```sql
CREATE TABLE persons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    birthdate DATE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Running Migrations

Migrations are automatically run when the `api_dev` container starts, but you can also run them manually:

**Using Docker Compose:**

```bash
# Run migrations for both test and dev databases
docker compose run --rm api_dev php bin/migrate_databases.php
```

**Inside the container:**

```bash
# Access the container shell
docker compose exec api_dev /bin/bash

# Run migrations
php bin/migrate_databases.php
```

### Creating a New Migration

1. **Create the migration file** in `migrations/` with an incrementing number:

```bash
# migrations/0002_add_email_to_persons.sql
ALTER TABLE persons ADD COLUMN email VARCHAR(255);
```

2. **Run the migration**:

```bash
docker compose run --rm api_dev php bin/migrate_databases.php
```

### How Migrations Work

1. The `MigrationsProcessor` scans the `migrations/` directory
2. All `.sql` files are sorted alphabetically by filename
3. Each migration is executed in order against the database
4. SQL statements are run directly via the MySQL connection

**Important Notes:**
- Migrations are NOT tracked (no migration table)
- Each migration is re-run every time the script executes
- Use idempotent SQL when possible (e.g., `CREATE TABLE IF NOT EXISTS`)
- Or ensure migrations are only run once during initial setup

## Database Configuration

The Dev API connects to a MySQL database using environment variables:

```bash
API_DEV_MYSQL_HOST=mysql          # Database host (container name)
API_DEV_MYSQL_USER=root           # Database user
API_DEV_MYSQL_PASSWORD=tent       # Database password
API_DEV_MYSQL_PORT=3306           # Database port
API_DEV_MYSQL_DEV_DATABASE=api_tent_dev_db   # Development database
API_DEV_MYSQL_TEST_DATABASE=api_tent_test_db # Test database
```

These are configured in the `.env` file at the root of the repository.

## Accessing the Services

When the Docker Compose environment is running, you can access:

### Dev API Service
- **URL**: http://localhost:8040
- **Available Endpoints**:
  - `GET /health` - Health check endpoint
  - `GET /persons` - List all persons from the database

### phpMyAdmin (Database Management)
- **URL**: http://localhost:8050
- **Username**: root
- **Password**: tent
- Use this to:
  - View and manage database tables
  - Insert sample data for testing
  - Run SQL queries directly

### Testing Through Tent Proxy

The Dev API is typically accessed through the Tent proxy:

- **Via Tent**: http://localhost:8080/persons
- **Direct**: http://localhost:8040/persons

This allows you to test proxy behavior, caching, and middleware.

## Development Workflow

### 1. Start the Services

```bash
# Start all services including the dev API
docker compose up

# Or start in detached mode
docker compose up -d
```

### 2. Initialize the Database

The database is automatically initialized and migrated on container startup. To manually trigger:

```bash
# Create databases
docker compose run --rm api_dev php bin/create_databases.php

# Run migrations
docker compose run --rm api_dev php bin/migrate_databases.php
```

### 3. Add Sample Data

Use phpMyAdmin (http://localhost:8050) to insert sample data:

```sql
INSERT INTO persons (first_name, last_name, birthdate) VALUES
('John', 'Doe', '1990-01-15'),
('Jane', 'Smith', '1985-07-22'),
('Bob', 'Johnson', '1992-11-30');
```

### 4. Test the API

```bash
# Health check
curl http://localhost:8040/health

# List persons
curl http://localhost:8040/persons

# Test through Tent proxy
curl http://localhost:8080/persons
```

### 5. View Logs

```bash
# Follow api_dev logs
docker compose logs -f api_dev

# View all logs
docker compose logs
```

## Running Tests

The Dev API has its own test suite:

```bash
# Run all tests
docker compose run --rm api_dev composer tests

# Run only unit tests
docker compose run --rm api_dev composer tests:unit

# Run only integration tests
docker compose run --rm api_dev composer tests:integration

# Lint code
docker compose run --rm api_dev composer lint

# Fix linting issues
docker compose run --rm api_dev composer lint:fix
```

## Integration with Tent

The Dev API is configured as a backend service in Tent's configuration. Requests to certain paths are proxied from Tent to this API:

```php
// Example Tent configuration (docker_volumes/configuration/rules/backend.php)
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://api:80'  // Points to api_dev container
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/persons', 'type' => 'exact']
    ],
    'middlewares' => [
        [
            'class' => 'Tent\Middlewares\SetHeadersMiddleware',
            'headers' => ['Host' => 'backend.local']
        ]
    ]
]);
```

This setup allows you to:
- Test proxy functionality
- Verify caching behavior
- Test middleware processing
- Simulate a real backend service

## Models and Database Layer

### Person Model

The `Person` model demonstrates basic ORM-like functionality:

```php
use ApiDev\Models\Person;

// Get all persons
$persons = Person::all();

// Access attributes
foreach ($persons as $person) {
    echo $person->getFirstName() . ' ' . $person->getLastName();
}
```

### Creating New Models

To create a new model:

1. Create the model class in `source/lib/api_dev/models/`
2. Extend it with appropriate getters for your fields
3. Implement a static `getConnection()` method
4. Use `ModelConnection` for database operations

Example:

```php
<?php

namespace ApiDev\Models;

use ApiDev\Mysql\ModelConnection;
use ApiDev\Mysql\Configuration;

class Product
{
    private $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function getId()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getName()
    {
        return $this->attributes['name'] ?? null;
    }

    public static function all(): array
    {
        $rows = self::getConnection()->list();
        return array_map(function ($attrs) {
            return new self($attrs);
        }, $rows);
    }

    private static $connection = null;

    public static function getConnection(): ModelConnection
    {
        if (self::$connection instanceof ModelConnection) {
            return self::$connection;
        }
        self::$connection = new ModelConnection(
            Configuration::connect(),
            'products'  // Table name
        );
        return self::$connection;
    }
}
```

## Troubleshooting

### Database Connection Issues

If the API cannot connect to the database:

1. Check that MySQL container is running: `docker compose ps`
2. Verify environment variables in `.env` file
3. Check MySQL logs: `docker compose logs api_dev_mysql`
4. Wait for database to be ready: `docker compose run --rm api_dev php bin/wait_for_database.php`

### 404 Not Found

If you get 404 errors:

1. Verify the route is registered in `index.php`
2. Check the endpoint file is included via `require_once`
3. Ensure the HTTP method matches (GET, POST, etc.)
4. Verify the exact path match

### Database Migration Errors

If migrations fail:

1. Check the SQL syntax in your migration file
2. Ensure the database exists: `docker compose run --rm api_dev php bin/create_databases.php`
3. Check MySQL logs for detailed error messages
4. Verify database credentials in `.env`

## Summary

The Dev API is a straightforward PHP application demonstrating:
- Simple request routing without external frameworks
- Configuration-based endpoint registration
- Database integration with models
- SQL-based migrations
- Integration with the Tent proxy for testing

It serves as both a testing tool for Tent development and a reference implementation for creating similar lightweight APIs.
