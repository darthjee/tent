# Dev Frontend - React Application

This is a lightweight React-based frontend application used for development and testing of the Tent proxy server. It provides a sample user interface that interacts with the backend API and demonstrates how the proxy handles frontend requests.

## Overview

The Dev Frontend is a React application built with Vite that serves as a demonstration of:

- Testing Tent proxy functionality for frontend applications
- Hot module reloading during development
- Production static file serving
- Integration with backend APIs through the proxy
- Modern React development patterns

## Architecture

### Tech Stack

- **React 19**: Modern React with hooks and functional components
- **Vite**: Fast build tool and development server
- **Bootstrap 5**: CSS framework for styling
- **TanStack Query (React Query)**: Data fetching and state management
- **Jasmine**: Testing framework
- **ESLint**: Code linting and style enforcement

### Project Structure

```
dev/frontend/
├── assets/                    # Application source code
│   ├── css/                   # Stylesheets
│   │   ├── styles.css         # Custom CSS
│   │   └── main.scss          # SASS styles
│   └── js/                    # JavaScript/JSX files
│       ├── main.jsx           # Application entry point
│       ├── components/        # React components
│       │   ├── App.jsx        # Root application component
│       │   └── PersonList.jsx # Example component
│       └── clients/           # API client modules
│           └── PersonClient.js # Backend API client
├── dist/                      # Built static files (production)
├── spec/                      # Test files
│   ├── clients/               # Client test specs
│   │   └── PersonClient_spec.js
│   └── example_spec.js        # Example test
├── index.html                 # HTML entry point
├── package.json               # Node.js dependencies and scripts
├── vite.config.js             # Vite configuration
├── eslint.config.mjs          # ESLint configuration
└── .eslintrc.yml              # Additional ESLint rules
```

## How It Works

### Development Mode vs Production Mode

The frontend can be served in two different ways, controlled by the `FRONTEND_DEV_MODE` environment variable:

#### Development Mode (`FRONTEND_DEV_MODE=true`)

```
Browser Request
    ↓
Tent Proxy (index.php)
    ↓
Proxy to Vite Dev Server (port 8030)
    ↓
Hot Module Reloading, Fast Refresh
    ↓
Browser receives response with HMR enabled
```

In development mode:

- Requests are proxied to the Vite development server
- Hot Module Reloading (HMR) is enabled
- Changes are reflected immediately without manual refresh
- Faster development iteration

#### Production Mode (`FRONTEND_DEV_MODE=false`)

```
Browser Request
    ↓
Tent Proxy (index.php)
    ↓
Static File Handler
    ↓
Serves files from dist/ directory
    ↓
Browser receives static response
```

In production mode:

- Static files are served directly from the `dist/` directory
- No development server overhead
- Optimized and minified assets
- Mimics production deployment

### Application Entry Point

The application bootstraps in `assets/js/main.jsx`:

```javascript
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import App from './components/App';

const queryClient = new QueryClient();

createRoot(document.getElementById('root')).render(
  <QueryClientProvider client={queryClient}>
    <App />
  </QueryClientProvider>
);
```

## Development Workflow

### 1. Set Development Mode

Edit the `.env` file at the repository root:

```bash
FRONTEND_DEV_MODE=true
```

### 2. Start the Services

```bash
# Start all services including the frontend dev server
docker compose up

# Or start in detached mode
docker compose up -d
```

The frontend development server will start on port 8030, but you should access it through the Tent proxy at:

- **Via Tent Proxy**: <http://localhost:8080> (recommended)
- **Direct Dev Server**: <http://localhost:8030> (for debugging)

### 3. Making Changes

With HMR enabled, changes to React components, CSS, or JavaScript files will be reflected immediately in the browser without a manual refresh.

### 4. Building for Production

To build static files for production:

```bash
# Build the frontend
docker compose run --rm frontend_dev npm run build

# Built files will be in the dist/ directory
```

## Running Tests

The frontend uses Jasmine for testing:

```bash
# Run all tests (container must be running)
docker compose exec frontend_dev npm test

# Or run tests in a new container
docker compose run --rm frontend_dev npm test

# Run tests with coverage
docker compose run --rm frontend_dev npm run coverage
```

### Writing Tests

Tests are located in the `spec/` directory and follow Jasmine conventions:

```javascript
// spec/clients/MyClient_spec.js
import MyClient from '../../assets/js/clients/MyClient';

describe('MyClient', () => {
  it('should fetch data', async () => {
    const result = await MyClient.fetchData();
    expect(result).toBeDefined();
  });
});
```

## Linting

The frontend uses ESLint for code quality and style enforcement:

```bash
# Lint all files
docker compose exec frontend_dev npm run lint

# Or run in a new container
docker compose run --rm frontend_dev npm run lint

# Automatically fix linting issues
docker compose exec frontend_dev npm run lint_fix

# Generate HTML lint report
docker compose run --rm frontend_dev npm run lint_report
```

### ESLint Configuration

ESLint is configured in `eslint.config.mjs` with:

- Standard JavaScript style guide
- React-specific rules
- Jasmine testing rules
- Complexity checks

## Adding New Features

### Creating a New Component

1. Create a new file in `assets/js/components/`:

```jsx
// assets/js/components/MyComponent.jsx
import React from 'react';

const MyComponent = () => {
  return (
    <div className="my-component">
      <h1>My Component</h1>
    </div>
  );
};

export default MyComponent;
```

2. Import and use it in `App.jsx`:

```jsx
import MyComponent from './MyComponent';

const App = () => {
  return (
    <div>
      <MyComponent />
    </div>
  );
};
```

### Creating an API Client

1. Create a new file in `assets/js/clients/`:

```javascript
// assets/js/clients/ProductClient.js
const ProductClient = {
  async fetchAll() {
    const response = await fetch('/api/products');
    return await response.json();
  },

  async fetchById(id) {
    const response = await fetch(`/api/products/${id}`);
    return await response.json();
  }
};

export default ProductClient;
```

2. Use it in a component with TanStack Query:

```jsx
import { useQuery } from '@tanstack/react-query';
import ProductClient from '../clients/ProductClient';

const ProductList = () => {
  const { data, isLoading, error } = useQuery({
    queryKey: ['products'],
    queryFn: ProductClient.fetchAll
  });

  if (isLoading) return <div>Loading...</div>;
  if (error) return <div>Error: {error.message}</div>;

  return (
    <ul>
      {data.map(product => (
        <li key={product.id}>{product.name}</li>
      ))}
    </ul>
  );
};
```

## Integration with Tent

The frontend is integrated with Tent through configuration rules. When `FRONTEND_DEV_MODE=true`, Tent proxies frontend requests to the Vite dev server:

```php
// Example Tent configuration (docker_volumes/configuration/rules/frontend.php)
Configuration::buildRule([
    'handler' => [
        'type' => 'proxy',
        'host' => 'http://frontend_dev:8080'  // Points to Vite dev server
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/', 'type' => 'exact']
    ],
    'middlewares' => [
        [
            'class' => 'Tent\Middlewares\SetHeadersMiddleware',
            'headers' => ['Host' => 'frontend.local']
        ]
    ]
]);
```

When `FRONTEND_DEV_MODE=false`, Tent serves static files from the `dist/` directory:

```php
Configuration::buildRule([
    'handler' => [
        'type' => 'static',
        'location' => '/var/www/html/static/'
    ],
    'matchers' => [
        ['method' => 'GET', 'uri' => '/', 'type' => 'exact']
    ],
    'middlewares' => [
        [
            'class' => 'Tent\Middlewares\SetPathMiddleware',
            'path' => '/index.html'
        ]
    ]
]);
```

## Accessing the Application

When the Docker Compose environment is running:

- **Via Tent Proxy**: <http://localhost:8080> (recommended for development)
- **Direct to Dev Server**: <http://localhost:8030> (debugging only)
- **Backend API**: <http://localhost:8040> (or via Tent at `/api/...` paths)

## Available Scripts

```bash
# Development server (starts Vite)
npm run server

# Build for production
npm run build

# Run tests
npm test

# Run tests with coverage
npm run coverage

# Lint code
npm run lint

# Fix lint issues automatically
npm run lint_fix

# Generate HTML lint report
npm run lint_report

# Generate code duplication report
npm run report
```

## Troubleshooting

### Hot Module Reloading Not Working

1. Ensure `FRONTEND_DEV_MODE=true` in `.env`
2. Restart services: `docker compose restart`
3. Check that `frontend_dev` container is running: `docker compose ps`
4. Check logs: `docker compose logs frontend_dev`

### Build Errors

If the build fails:

1. Check Node.js logs: `docker compose logs frontend_dev`
2. Clear node_modules and reinstall:
   ```bash
   docker compose run --rm frontend_dev rm -rf node_modules
   docker compose run --rm frontend_dev npm install
   ```
3. Check for syntax errors in your code

### CORS Issues

If you encounter CORS errors:

1. Ensure requests go through Tent proxy (<http://localhost:8080>), not directly to the dev server
2. Check Vite configuration has `cors: true` in `vite.config.js`
3. Verify Tent proxy configuration includes appropriate headers

### Test Failures

If tests fail:

1. Run tests with verbose output: `docker compose run --rm frontend_dev npm test`
2. Check for syntax errors in test files
3. Ensure all dependencies are installed: `docker compose run --rm frontend_dev npm install`

## Summary

The Dev Frontend is a modern React application that demonstrates:

- Integration with Tent proxy server
- Development and production deployment modes
- Hot module reloading for fast development
- React Query for data fetching
- Component-based architecture
- Testing with Jasmine
- Code quality with ESLint

It serves as both a testing tool for Tent development and a reference implementation for building frontend applications that work with Tent as a proxy server.
