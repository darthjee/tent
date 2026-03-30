# Plan: Issue 192 — Add Photo Endpoint for Dev App Backend

## Goal

Add a `POST /persons/:id/photo.json` endpoint to the dev app backend (`api_dev`) that accepts a file upload and saves the photo to disk. No database changes are needed.

---

## Context

- Routes are registered in `dev/api/source/index.php` via `Configuration::add()`.
- `Route.php` currently does **exact** string matching only; it cannot match parameterised paths like `/persons/:id/photo.json`.
- Uploaded files are available in PHP via `$_FILES`, but only if the request reaches the dev API with the correct `Content-Type: multipart/form-data` (end-to-end upload through Tent depends on issue 195).
- Photos are stored at `/home/app/app/photos/<person_id>.jpg`. This directory is mounted from `docker_volumes/photos`, so files persist across container restarts.
- No database column is needed — the filename convention (`<person_id>.jpg`) is sufficient to associate the photo with a person.
- All new classes require an explicit `require_once` in `dev/api/source/loader.php`.
- Tests live in `dev/api/tests/unit/` mirroring the source structure; integration tests hit a real MySQL instance.

---

## Implementation Steps

### Step 1 — Route pattern matching

**File:** `dev/api/source/lib/api_dev/Route.php`

The current `matchPath()` method does a simple equality check:

```php
return $this->path === null || $request->requestUrl() === $this->path;
```

Change it to detect `:param` placeholders and convert the stored path to a regex when needed:

- If `$this->path` contains no `:` characters, keep the current equality check.
- Otherwise, convert each `:word` segment to `[^/]+` and wrap in `^…$`, then use `preg_match()`.

Example conversion: `/persons/:id/photo.json` → `#^/persons/[^/]+/photo\.json$#`

No changes are needed to `RouteConfiguration.php`, `RequestInterface`, or `Request.php`; the endpoint itself will extract the ID by parsing the URL with a regex.

---

### Step 2 — Upload directory

Photos are stored in `/home/app/app/photos/` inside the container. This maps to `docker_volumes/photos` on the host. The endpoint must create the directory if it does not exist (`mkdir($dir, 0755, true)`).

The filename for each person is `<person_id>.jpg`.

Full path example for person 5: `/home/app/app/photos/5.jpg`

---

### Step 3 — New endpoint: `UploadPersonPhotoEndpoint`

**File:** `dev/api/source/lib/api_dev/endpoints/UploadPersonPhotoEndpoint.php`

Class extends `Endpoint`. Internal flow:

```
handle()
  └─ handleRequest()        (throws RequestException subtypes on error)
       ├─ extractPersonId()  ← regex on $this->request->requestUrl()
       ├─ loadPerson()       ← Person::find($id) or throw NotFoundException (404)
       ├─ validateUpload()   ← check $_FILES['photo'] exists and has no PHP upload error
       ├─ validateMimeType() ← allow image/jpeg, image/png, image/gif only (422 on fail)
       ├─ saveFile()         ← move_uploaded_file() to /home/app/app/photos/{id}.jpg
       └─ buildResponse()    ← 200 JSON with person data
```

Error handling mirrors `CreatePersonEndpoint`: catch `RequestException` subtypes and return the appropriate HTTP status code with `{"error": "…"}`.

New exception classes needed (if not yet present):
- `NotFoundException` (HTTP 404) — extends `RequestException`
- `UnprocessableEntityException` (HTTP 422) — extends `RequestException`

---

### Step 4 — Register the new class

**File:** `dev/api/source/loader.php`

Add (in dependency order, after existing exception requires):

```php
require_once __DIR__ . '/lib/api_dev/exceptions/NotFoundException.php';
require_once __DIR__ . '/lib/api_dev/exceptions/UnprocessableEntityException.php';
require_once __DIR__ . '/lib/api_dev/endpoints/UploadPersonPhotoEndpoint.php';
```

---

### Step 5 — Register the route

**File:** `dev/api/source/index.php`

Add:

```php
Configuration::add('POST', '/persons/:id/photo.json', UploadPersonPhotoEndpoint::class);
```

Also add the class import at the top to keep parity with existing imports.

---

### Step 6 — Tests

#### 6a — Route pattern matching

**File:** `dev/api/tests/unit/lib/api_dev/RoutePatternMatchTest.php`

Cases to cover:
- Exact path still matches (no regression).
- Pattern `/persons/:id/photo.json` matches `/persons/1/photo.json`.
- Pattern `/persons/:id/photo.json` matches `/persons/999/photo.json`.
- Pattern `/persons/:id/photo.json` does NOT match `/persons/photo.json`.
- Pattern `/persons/:id/photo.json` does NOT match `/persons/1/2/photo.json`.

Use `MockRequest` (already in `tests/support/`) to inject the URL.

#### 6b — Endpoint unit tests

**File:** `dev/api/tests/unit/lib/api_dev/endpoints/UploadPersonPhotoEndpointTest.php`

Cases to cover:
- Person not found → 404 with `{"error": "…"}`.
- No file in request → 400 with `{"error": "…"}`.
- Invalid MIME type → 422 with `{"error": "…"}`.
- Valid upload → 200, JSON with person data; file exists at `/home/app/app/photos/{id}.jpg`.

Because `$_FILES` is a superglobal, extract the file-handling logic into a `FileStorage` collaborator (interface + real implementation) so the endpoint can be tested without real files. The test injects a mock `FileStorage`.

---

## Files Changed / Created

| Action | Path |
|--------|------|
| Modify | `dev/api/source/lib/api_dev/Route.php` |
| Create | `dev/api/source/lib/api_dev/exceptions/NotFoundException.php` |
| Create | `dev/api/source/lib/api_dev/exceptions/UnprocessableEntityException.php` |
| Create | `dev/api/source/lib/api_dev/endpoints/UploadPersonPhotoEndpoint.php` |
| Modify | `dev/api/source/loader.php` |
| Modify | `dev/api/source/index.php` |
| Create | `dev/api/tests/unit/lib/api_dev/RoutePatternMatchTest.php` |
| Create | `dev/api/tests/unit/lib/api_dev/endpoints/UploadPersonPhotoEndpointTest.php` |

---

## Commit order (one logical change per commit)

1. Route pattern matching — `Route.php` + its test
2. New exceptions — `NotFoundException.php`, `UnprocessableEntityException.php`
3. New endpoint — `UploadPersonPhotoEndpoint.php` + its test + `loader.php` + `index.php`
