# Dev-API Plan: Add Edit Person Page

Main plan: [plan.md](plan.md)

## Shared contracts

This agent must produce `PATCH /persons/:id`:

- **Method:** `PATCH`
- **Path:** `/persons/:id` (integer ID in URL)
- **Request body:** JSON with any subset of `first_name`, `last_name`, `birthdate`.
- **Success (200):** full updated person JSON (`id`, `first_name`, `last_name`, `birthdate`, `created_at`, `updated_at`).
- **Not found (404):** `{ "error": "Person not found" }`.
- **Invalid body (422):** `{ "error": "At least one field required" }`.

## Implementation Steps

### Step 1 — Create `UpdatePersonEndpoint`

Create `dev/api/source/lib/api_dev/endpoints/UpdatePersonEndpoint.php` following the pattern of `ShowPersonEndpoint` and `CreatePersonEndpoint`:

1. Extract the person ID from the URL using `preg_match('#/persons/(\d+)#', $this->request->requestUrl(), $matches)`.
2. Call `Person::find($id)`. If `null`, return 404 with `{ "error": "Person not found" }`.
3. Parse the JSON body via `$this->request->body()`. If the result is not an array, return 422 with `{ "error": "At least one field required" }`.
4. Merge accepted fields (`first_name`, `last_name`, `birthdate`) into the existing person's attributes. At least one must be present; if all are missing, throw/return 422.
5. Call `$person->save()` which calls `connection->update($id, $attributes)` because the person already has an ID.
6. Return HTTP 200 with `$person->toJson()` and `Content-Type: application/json`.

Use the same exception-catching pattern as `CreatePersonEndpoint`: wrap in a `try/catch (RequestException $e)` to return the appropriate error response.

### Step 2 — Register the loader and route

In `dev/api/source/loader.php`, add:
```php
require_once __DIR__ . '/lib/api_dev/endpoints/UpdatePersonEndpoint.php';
```
Place it after the `CreatePersonEndpoint` require.

In `dev/api/source/index.php`, add:
```php
use ApiDev\UpdatePersonEndpoint;
...
Configuration::add('PATCH', '/persons/:id', UpdatePersonEndpoint::class);
```
Place the `use` statement with the other endpoint uses, and the `add` call after the `ShowPersonEndpoint` registration.

### Step 3 — Write unit tests

Create `dev/api/tests/unit/lib/api_dev/endpoints/UpdatePersonEndpointTest.php` following the pattern of `ShowPersonEndpointTest`:

- `setUp`: clear the persons table and insert a test person, recording `$this->personId`.
- Test: `PATCH /persons/:id` with valid JSON body returns 200 and the updated person JSON.
- Test: `PATCH /persons/999999` returns 404 with `{ "error": "Person not found" }`.
- Test: `PATCH /persons/:id` with an invalid (non-JSON) body returns 422 with an error message.

## Files to Change

- `dev/api/source/lib/api_dev/endpoints/UpdatePersonEndpoint.php` — new file
- `dev/api/source/loader.php` — add require for new endpoint
- `dev/api/source/index.php` — register `PATCH /persons/:id` route
- `dev/api/tests/unit/lib/api_dev/endpoints/UpdatePersonEndpointTest.php` — new test file

## CI Checks

- `dev/api`: `make test-api` (PHPUnit tests for the dev API)

## Notes

- The `BaseModel::save()` method already handles update when `getId()` is non-null (calls `connection->update()`), so no additional DB method is needed.
- The `UnprocessableEntityException` already exists with HTTP code 422 — use it for the invalid-body case.
- Match the JSON response structure of `ShowPersonEndpoint` exactly (all six fields).
