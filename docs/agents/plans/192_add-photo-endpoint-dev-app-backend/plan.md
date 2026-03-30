# Plan: Issue 192 — Add Photo Endpoint for Dev App Backend

## Goal

Add a `POST /persons/:id/photo.json` endpoint to the dev app backend (`api_dev`) that accepts a file upload and associates the photo with the given person.

---

## Context

- Routes are registered in `dev/api/source/index.php` via `Configuration::add()`.
- `Route.php` currently does **exact** string matching only; it cannot match parameterised paths like `/persons/:id/photo.json`.
- Uploaded files are available in PHP via `$_FILES`, but only if the request reaches the dev API with the correct `Content-Type: multipart/form-data` (end-to-end upload through Tent depends on issue 195).
- The `Person` model uses `BaseModel`; new attributes must be declared in `attributeNames()`.
- All new classes require an explicit `require_once` in `dev/api/source/loader.php`.
- Tests live in `dev/api/tests/unit/` mirroring the source structure; integration tests hit a real MySQL instance.

---

## Implementation Steps

### Step 1 — DB migration: add `photo_path` column

**File:** `dev/api/migrations/0002_add_photo_path_to_persons.sql`

Add a nullable `photo_path` column to the `persons` table. The migration must be idempotent (use `IF NOT EXISTS` or `IF (SELECT COUNT(*) …)`):

```sql
ALTER TABLE persons
  ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) NULL DEFAULT NULL;
```

Run it with:

```bash
docker compose run --rm api_dev php bin/migrate_databases.php
```

---

### Step 2 — Route pattern matching

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

### Step 3 — Person model: add `photo_path`

**File:** `dev/api/source/lib/api_dev/models/Person.php`

1. Add `'photo_path'` to the array returned by `attributeNames()`.
2. Add a `getPhotoPath(): ?string` getter method.

---

### Step 4 — Upload directory

The uploaded photo will be written to a path inside the container. Use `/var/www/html/uploads/persons/` as the base directory. The Apache document root already serves `/var/www/html/`, so uploaded images are accessible at `/uploads/persons/{id}/photo.{ext}`.

The endpoint must create the directory if it does not exist (`mkdir($dir, 0755, true)`).

---

### Step 5 — New endpoint: `UploadPersonPhotoEndpoint`

**File:** `dev/api/source/lib/api_dev/endpoints/UploadPersonPhotoEndpoint.php`

Class extends `Endpoint`. Internal flow:

```
handle()
  └─ handleRequest()        (throws RequestException subtypes on error)
       ├─ extractPersonId()  ← regex on $this->request->requestUrl()
       ├─ loadPerson()       ← Person::find($id) or throw NotFoundException (404)
       ├─ validateUpload()   ← check $_FILES['photo'] exists and has no PHP upload error
       ├─ validateMimeType() ← allow image/jpeg, image/png, image/gif only (422 on fail)
       ├─ saveFile()         ← move_uploaded_file() to /uploads/persons/{id}/photo.{ext}
       ├─ updatePerson()     ← set photo_path, call $person->save()
       └─ buildResponse()    ← 200 JSON with updated person data
```

Error handling mirrors `CreatePersonEndpoint`: catch `RequestException` subtypes and return the appropriate HTTP status code with `{"error": "…"}`.

New exception classes needed (if not yet present):
- `NotFoundException` (HTTP 404) — extends `RequestException`
- `UnprocessableEntityException` (HTTP 422) — extends `RequestException`

---

### Step 6 — Register the new class

**File:** `dev/api/source/loader.php`

Add (in dependency order, after existing exception requires):

```php
require_once __DIR__ . '/lib/api_dev/exceptions/NotFoundException.php';
require_once __DIR__ . '/lib/api_dev/exceptions/UnprocessableEntityException.php';
require_once __DIR__ . '/lib/api_dev/endpoints/UploadPersonPhotoEndpoint.php';
```

---

### Step 7 — Register the route

**File:** `dev/api/source/index.php`

Add:

```php
Configuration::add('POST', '/persons/:id/photo.json', UploadPersonPhotoEndpoint::class);
```

Also add the `use` / `class` import at the top to keep parity with existing imports.

---

### Step 8 — Tests

#### 8a — Route pattern matching

**File:** `dev/api/tests/unit/lib/api_dev/RoutePatternMatchTest.php`

Cases to cover:
- Exact path still matches (no regression).
- Pattern `/persons/:id/photo.json` matches `/persons/1/photo.json`.
- Pattern `/persons/:id/photo.json` matches `/persons/999/photo.json`.
- Pattern `/persons/:id/photo.json` does NOT match `/persons/photo.json`.
- Pattern `/persons/:id/photo.json` does NOT match `/persons/1/2/photo.json`.

Use `MockRequest` (already in `tests/support/`) to inject the URL.

#### 8b — Endpoint unit tests

**File:** `dev/api/tests/unit/lib/api_dev/endpoints/UploadPersonPhotoEndpointTest.php`

Cases to cover (integration — hits real DB, no `$_FILES` mocking):
- Person not found → 404 with `{"error": "…"}`.
- No file in request → 400 with `{"error": "…"}`.
- Invalid MIME type → 422 with `{"error": "…"}`.
- Valid upload → 200, JSON with updated person including `photo_path`.

Because `$_FILES` is a superglobal, tests that exercise the file-handling path can either:
- Use a real temp file and set `$_FILES` directly in the test setup, or
- Extract the file-handling logic into a collaborator that can be injected/mocked.

The preferred approach is to inject a `FileStorage` collaborator (interface + real implementation) so the endpoint can be tested without real files.

---

## Files Changed / Created

| Action | Path |
|--------|------|
| Create | `dev/api/migrations/0002_add_photo_path_to_persons.sql` |
| Modify | `dev/api/source/lib/api_dev/Route.php` |
| Modify | `dev/api/source/lib/api_dev/models/Person.php` |
| Create | `dev/api/source/lib/api_dev/exceptions/NotFoundException.php` |
| Create | `dev/api/source/lib/api_dev/exceptions/UnprocessableEntityException.php` |
| Create | `dev/api/source/lib/api_dev/endpoints/UploadPersonPhotoEndpoint.php` |
| Modify | `dev/api/source/loader.php` |
| Modify | `dev/api/source/index.php` |
| Create | `dev/api/tests/unit/lib/api_dev/RoutePatternMatchTest.php` |
| Create | `dev/api/tests/unit/lib/api_dev/endpoints/UploadPersonPhotoEndpointTest.php` |

---

## Commit order (one logical change per commit)

1. Migration — `dev/api/migrations/0002_add_photo_path_to_persons.sql`
2. Route pattern matching — `Route.php` + its test
3. Person model update — `Person.php`
4. New exceptions — `NotFoundException.php`, `UnprocessableEntityException.php`
5. New endpoint — `UploadPersonPhotoEndpoint.php` + its test + `loader.php` + `index.php`
