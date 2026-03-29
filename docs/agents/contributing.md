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