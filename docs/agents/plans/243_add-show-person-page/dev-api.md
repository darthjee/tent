# Dev-Api Plan: Add Show Person Page

Main plan: [plan.md](plan.md)

## Shared contracts

This agent produces the `GET /persons/:id` endpoint consumed by the frontend.

**Success (HTTP 200):** JSON object with fields `id` (int), `first_name` (string), `last_name` (string), `birthdate` (string|null), `created_at` (string), `updated_at` (string).

**Not found (HTTP 404):** JSON object `{"error": "Person not found"}`.

## Implementation Steps

### Step 1 — Create `ShowPersonEndpoint`

Create `dev/api/source/lib/api_dev/endpoints/ShowPersonEndpoint.php`.

- Extend `Endpoint`.
- Extract the person ID from the request URL using a regex against `requestUrl()`, matching the pattern `/persons/(\d+)` (same approach as `UploadPersonPhotoEndpoint::extractPersonId()`).
- Call `Person::find($id)`.
- If the result is `null`, return a 404 response: `new Response(json_encode(['error' => 'Person not found']), 404, ['Content-Type: application/json'])`.
- If found, build a data array with all person fields (`id`, `first_name`, `last_name`, `birthdate`, `created_at`, `updated_at`) and return `new Response(json_encode($data), 200, ['Content-Type: application/json'])`. Use the individual getter methods (`getFirstName()`, etc.) as `ListPersonsEndpoint` does — do not call `asJson()` directly, to keep the field set explicit.

### Step 2 — Register in `loader.php`

Add `require_once __DIR__ . '/lib/api_dev/endpoints/ShowPersonEndpoint.php';` to `dev/api/source/loader.php`, after `ListPersonsEndpoint.php` and before `CreatePersonEndpoint.php`, to keep endpoint requires grouped together.

### Step 3 — Register the route in `index.php`

Add `use ApiDev\ShowPersonEndpoint;` to the use-block and register:
```php
Configuration::add('GET', '/persons/:id', ShowPersonEndpoint::class);
```
Place this line after `GET /persons` and before `POST /persons` in `dev/api/source/index.php`.

### Step 4 — Write tests

Create `dev/api/tests/unit/lib/api_dev/endpoints/ShowPersonEndpointTest.php`.

Follow the pattern of `ListPersonsEndpointTest` (real DB, `setUp` inserts a known person, test asserts on status code and response body). Cover:

- `testHandleReturns200WithPersonJson` — inserts a person, fetches by ID, asserts HTTP 200 and that the JSON contains the correct `first_name`, `last_name`, `birthdate`, and `id`.
- `testHandleReturns404WhenPersonNotFound` — requests a non-existent ID (e.g. 999999), asserts HTTP 404 and that the JSON body contains `{"error": "Person not found"}`.

Use `MockRequest` with `requestUrl` set to `/persons/<id>`.

## Files to Change

- `dev/api/source/lib/api_dev/endpoints/ShowPersonEndpoint.php` — new endpoint class
- `dev/api/source/loader.php` — add `require_once` for new endpoint
- `dev/api/source/index.php` — register `GET /persons/:id` route
- `dev/api/tests/unit/lib/api_dev/endpoints/ShowPersonEndpointTest.php` — new test class

## CI Checks

- `dev/api/`: `docker compose run --rm api_dev composer tests` (CI job: `dev_api_test`)
- `dev/api/`: `docker compose run --rm api_dev composer lint` (CI job: `dev_api_checks`)

## Notes

- `Person::find(int $id)` already exists on `BaseModel` — no model changes needed.
- The route pattern `GET /persons/:id` must be registered **after** `GET /persons` (exact match) so the router does not swallow the list route.
- Endpoint follow PSR-12; run `composer lint` before committing.
