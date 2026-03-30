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
  - *Example (pseudo-code):*
    ```js
    // Good: Each method does one thing
    class Worker {
      fetchJob() { ... }
      processJob(job) { ... }
    }

    // Bad: Method does too much
    class Worker {
      run() {
        this.fetchJob();
        this.processJob();
        this.sendMetrics();
        this.cleanup();
      }
    }
    ```
  - This requirement applies primarily to source code. For specs, refactor only if there is excessive duplication.

## Code Organization

### File Responsibility: Class Declarers vs Scripts

Every source file (excluding test files) must act as a **class declarer** — it should define and export one or more classes or modules. Files must not act as **scripts** (i.e., they must not execute logic at import time or perform side effects directly).

The only exceptions are **entrypoints**:

| Application | Entrypoint |
|-------------|-----------|
| Main app (`source/`) | `source/bin/navi.js` |
| Dev app (`new-dev/`) | `new-dev/server.js` |

`new-dev/app.js` is the application module (exports the configured Express app) and is imported by both `server.js` and the test suite. It is not a script.

*Example:*
```js
// Good: class declarer — defines and exports a class
class Router {
  register(app) { ... }
}
export default Router;

// Bad: script — executes logic at module level
const router = Router();
router.get('/path', handler);
export default router;
```

Test files are exempt from this rule and may import modules and execute setup code freely.

### File Naming: CamelCase for Class Files

Files that define and export a class must use **CamelCase** naming, matching the class name exactly.

*Examples:*

- `Router.js` for `class Router`
- `Config.js` for `class Config`
- `RouteRegistrar.js` for `class RouteRegistrar`
- `DataNavigator.js` for `class DataNavigator`

This applies to both source files and their corresponding spec files:
- `Router.js` → spec: `Router_spec.js`
- `DataNavigator.js` → spec: `DataNavigator_spec.js`
- `Router.js` â spec: `Router_spec.js`
Non-class files (e.g., utility modules that export functions) use lowercase or camelCase at the author's discretion.

## Dependency Injection

Classes must receive their dependencies (data, configuration, collaborators) as constructor arguments. A class must never reach out to load files, read environment variables, or fetch configuration on its own.

**The entry script is the only place responsible for loading configuration** (e.g. reading a YAML file, parsing CLI arguments). It then passes the loaded data down to the classes that need it.

This makes every class independently testable: tests simply instantiate the class with the data they need, without touching the filesystem or environment.

*Example:*
```js
// Good: class receives data as an argument — easy to test
class Router {
  constructor(data) {
    this._data = data;
  }
  build() { ... }
}

// In server.js (entry script):
const data = load(readFileSync(dataPath, 'utf8'));
const router = new Router(data);

// Bad: class loads its own config — hard to test and couples to the filesystem
class Router {
  build() {
    const data = load(readFileSync('./data.yml', 'utf8')); // ❌
    ...
  }
}
```

This principle applies to all classes — including helpers and registrars. If a class needs data, it gets it through its constructor.

## Refactoring Guidelines

When refactoring, aim to:

- **Reduce Code Duplication:**
  *Example:* Move repeated setup code in specs to a factory function.
  ```js
  // Good
  function buildCategory(attrs = {}) {
    return { id: 1, name: 'Books', ...attrs };
  }
  // In tests:
  const category = buildCategory({ id: 2 });

  // Bad
  const category = { id: 2, name: 'Books' };
  // ...repeated in many files
