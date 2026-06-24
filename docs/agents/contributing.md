# Contributing

## Commit Guidelines

- **Atomic and Unitary:** Each commit must represent a single logical change.
  *Example:*
  - Good: `Add needsParams() method to ResourceRequest`
  - Bad: `Add needsParams() and refactor Registry logic`
- **No Unrelated Changes:** Do not mix unrelated changes in the same commit.
- **Separate Refactoring:** Whenever possible, separate refactoring commits from new feature or bugfix commits.

## Pull Requests

- **Descriptive Summary:** Every PR must include a clear and descriptive summary of its purpose and changes.
- **PR Description Files:** If a description cannot be provided directly in the PR, generate a file with the PR description (e.g., `docs/issues/<pr_number>_description.md`), but do not commit this file.

## Definition of Done for PRs

A PR is considered complete when:

- The stated objective has been achieved.
- All tests are passing.
- Linting passes without errors.
- Code coverage is as high as reasonably possible.
- Code is not overly complex:
  - Classes and methods should have clear, focused responsibilities.
  - If a class or method is taking on too many responsibilities, refactor to simplify.
  - Methods should be small and do exactly one thing. If a method is growing, extract parts into private helper methods or separate classes.
  - *Example:*
    ```php
    // Good: Each method does one thing
    class Worker {
        public function fetchJob() { ... }
        public function processJob(Job $job) { ... }
    }

    // Bad: Method does too much
    class Worker {
        public function run() {
            $this->fetchJob();
            $this->processJob();
            $this->sendMetrics();
            $this->cleanup();
        }
    }
    ```
  - This requirement applies primarily to source code. For specs, refactor only if there is excessive duplication.

### CI Checks

Before a PR is considered complete, all CI checks relevant to the modified parts of the project must pass locally.

| Modified folder | CircleCI job | Local command |
|----------------|-------------|---------------|
| `source/` | `unit_test` | `docker compose run --rm tent_tests composer tests` |
| `source/` | `checks` | `docker compose run --rm tent_tests composer lint` |
| `dev/api/` | `dev_api_test` | `docker compose run --rm api_dev composer tests` |
| `dev/api/` | `dev_api_checks` | `docker compose run --rm api_dev composer lint` |
| `dev/frontend/` | `dev_frontend_test` | `docker compose run --rm frontend_dev npm test` |
| `dev/frontend/` | `dev_frontend_checks` | `docker compose run --rm frontend_dev npm run lint` |

Run only the checks that correspond to the folders you changed. This same process must be followed when **planning how to resolve an issue**: include a final step in the plan that identifies the affected folders and lists the CI commands to run before opening a PR.

## Code Organization

### File Responsibility: Class Declarers vs Scripts

Every source file (excluding test files) must act as a **class declarer** — it should define one class per file. Files must not act as **scripts** (i.e., they must not execute logic or perform side effects when loaded via `require_once`).

The only exceptions are **entrypoints**:

| Application | Entrypoint |
|-------------|-----------|
| Main app (`source/`) | `source/source/index.php` |
| Dev app (`dev/api/`) | `dev/api/source/index.php` |

*Example:*
```php
// Good: class declarer — defines a class, no side effects at load time
class Router {
    public function register(string $path, callable $handler): void { ... }
}

// Bad: script — executes logic when the file is loaded
$router = new Router();
$router->get('/path', $handler);
```

Test files are exempt from this rule and may import modules and execute setup code freely.

### File Naming: PascalCase for Class Files

Files that define a class must use **PascalCase** naming, matching the class name exactly.

*Examples:*

- `Router.php` for `class Router`
- `Configuration.php` for `class Configuration`
- `RouteConfiguration.php` for `class RouteConfiguration`
- `RequestHandler.php` for `class RequestHandler`

This applies to both source files and their corresponding test files:
- `Router.php` → test: `RouterTest.php`
- `RequestHandler.php` → test: `RequestHandlerTest.php`

### Method Order: Public Before Private

Within a class, **public methods must be declared before private/protected methods**. Private methods serve as implementation helpers and should appear at the end of the class body.

*Example:*
```php
// Good: public methods first, private methods last
class Worker {
    public function run(): void {
        $this->prepare();
        $this->execute();
    }

    public function getStatus(): string { ... }

    private function prepare(): void { ... }
    private function execute(): void { ... }
}

// Bad: private methods mixed in with or before public methods
class Worker {
    private function prepare(): void { ... }

    public function run(): void { ... }

    private function execute(): void { ... }
}
```

## Dependency Injection

Classes must receive their dependencies (data, configuration, collaborators) as constructor arguments. A class must never reach out to load files, read environment variables, or fetch configuration on its own.

**The entry script is the only place responsible for loading configuration** (e.g. reading environment variables, parsing configuration files). It then passes the loaded data down to the classes that need it.

This makes every class independently testable: tests simply instantiate the class with the data they need, without touching the filesystem or environment.

*Example:*
```php
// Good: class receives dependencies as arguments — easy to test
class ProxyRequestHandler {
    public function __construct(string $host, ?HttpClientInterface $httpClient = null) {
        $this->server = new Server($host);
        $this->httpClient = $httpClient ?? new CurlHttpClient();
    }
}

// In index.php (entry script):
$host = getenv('BACKEND_HOST') ?: 'http://localhost:80';
$handler = new ProxyRequestHandler($host);

// Bad: class loads its own config — hard to test and couples to the environment
class ProxyRequestHandler {
    public function forward(Request $request): Response {
        $host = getenv('BACKEND_HOST'); // ❌
        ...
    }
}
```

This principle applies to all classes — including helpers and collaborators. If a class needs data, it gets it through its constructor.

## Refactoring Guidelines

When refactoring, aim to:

- **Reduce Code Duplication:**
  *Example:* Move repeated setup code in tests to a helper method or `setUp()`.
  ```php
  // Good
  private function buildPerson(array $attrs = []): Person {
      return new Person(array_merge(['first_name' => 'John', 'last_name' => 'Doe'], $attrs));
  }
  // In tests:
  $person = $this->buildPerson(['first_name' => 'Jane']);

  // Bad
  $person = new Person(['first_name' => 'Jane', 'last_name' => 'Doe']);
  // ...repeated in many test methods
  ```

- **Improve Readability:**
  Extract complex conditionals or long method chains into named variables or helper methods.
  ```php
  // Good
  $isExpired = $token->expiresAt() < new DateTime();
  if ($isExpired) { ... }

  // Bad
  if ($token->expiresAt() < new DateTime()) { ... }
  ```

- **Keep Methods Small:**
  If a method grows beyond ~10 lines, consider extracting logic into private helpers.

- **Refactoring Commits Must Not Change Behaviour:**
  A refactoring commit should not alter observable behaviour. If behaviour must change, do it in a separate commit.
