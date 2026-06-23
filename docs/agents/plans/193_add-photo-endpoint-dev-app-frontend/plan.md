# Plan: Add Photo Upload to Dev App (Frontend + Backend)

## Overview

Add photo upload support to the dev-app — both the PHP backend (`dev/api/`) and the React frontend (`dev/frontend/`). The backend exposes a new endpoint that saves a JPEG photo for a person; the frontend adds a client method and a React component to call it.

## Agents

- **dev-api** — backend endpoint, file storage abstraction, tests
- **frontend** — client method, React component, integration into PersonList, Jasmine spec

## Backend — dev-api

### Context

The backend uses a `RequestHandler` that matches routes to `Endpoint` subclasses. File uploads arrive via PHP's `$_FILES`. No ORM is used — the `Person` model uses raw SQL.

### Step 1 — File storage abstraction

Files:
- `dev/api/source/lib/api_dev/file_storage/FileStorageInterface.php` — defines `save(string $tmp, string $dest): bool`
- `dev/api/source/lib/api_dev/file_storage/PhpFileStorage.php` — implementation using `move_uploaded_file`
- `dev/api/tests/support/models/MockFileStorage.php` — test double that records calls

### Step 2 — Endpoint

File: `dev/api/source/lib/api_dev/endpoints/UploadPersonPhotoEndpoint.php`

Route: `POST /persons/:id/photo.json`

Flow:
1. Extract person ID from URL via regex.
2. Load person — throw `NotFoundException` (404) if not found.
3. Validate upload via `$request->uploadedFile('photo')` — throw `InvalidRequestException` (400) if missing or errored.
4. Validate MIME type via `mime_content_type($file['tmp_name'])` — throw `UnprocessableEntityException` (422) if not `image/jpeg`.
5. Save to `/home/app/app/photos/<person_id>.jpg` — throw `ServerErrorException` (500) if save fails.
6. Return 200 with person JSON.

All `RequestException` subclasses are caught and returned as `{ "error": "..." }` with the appropriate HTTP status code.

Constructor accepts optional `FileStorageInterface` (defaults to `PhpFileStorage`) and `$photosDir` (defaults to `/home/app/app/photos`) for testability.

### Step 3 — Tests

File: `dev/api/tests/unit/lib/api_dev/endpoints/UploadPersonPhotoEndpointTest.php`

Use `MockRequest` and `MockFileStorage`. Cover:
- Success: returns 200 with person JSON.
- Person not found: returns 404 with error.
- No file uploaded / upload error: returns 400 with error.
- Invalid MIME type: returns 422 with error.
- Save failure: returns 500 with error.

### Backend files

| Action | File |
|--------|------|
| Create | `dev/api/source/lib/api_dev/endpoints/UploadPersonPhotoEndpoint.php` |
| Create | `dev/api/source/lib/api_dev/file_storage/FileStorageInterface.php` |
| Create | `dev/api/source/lib/api_dev/file_storage/PhpFileStorage.php` |
| Create | `dev/api/tests/unit/lib/api_dev/endpoints/UploadPersonPhotoEndpointTest.php` |
| Create | `dev/api/tests/support/models/MockFileStorage.php` |

---

## Frontend — frontend

### Context

The frontend follows these conventions:
- HTTP logic lives in `dev/frontend/assets/js/clients/PersonClient.js` (plain `fetch`, no framework).
- UI components live in `dev/frontend/assets/js/components/`, using React 19 + Bootstrap 5.
- Tests live in `dev/frontend/spec/`, mirroring the source structure, using Jasmine + `spyOn(global, 'fetch')`.

**Do not touch `source/`** (the Tent proxy). This issue is scoped entirely to `dev/api/` and `dev/frontend/`.

### Step 4 — Add `uploadPhoto(id, file)` to `PersonClient`

File: `dev/frontend/assets/js/clients/PersonClient.js`

Add after `create()`:

```js
async uploadPhoto(id, file) {
  const formData = new FormData();
  formData.append('photo', file);
  const response = await fetch(`/persons/${id}/photo`, {
    method: 'POST',
    body: formData,
  });
  if (!response.ok) {
    throw new Error('Failed to upload photo');
  }
  return response.json();
}
```

Do **not** set a `Content-Type` header — the browser sets it automatically with the correct boundary.

### Step 5 — Write tests for `uploadPhoto`

File: `dev/frontend/spec/clients/PersonClient/PersonClientUploadPhoto_spec.js`

Cases: success (verify URL `/persons/1/photo` and method `POST`), failure (non-ok response throws `Error('Failed to upload photo')`), network error (rejection propagates).

### Step 6 — Create `PersonPhotoForm` component

File: `dev/frontend/assets/js/components/PersonPhotoForm.jsx`

Props: `{ personId }`. State: `file`, `loading`, `error`, `success`. File input with `accept="image/jpeg"`, submit button disabled while loading or no file selected, inline success/error feedback via Bootstrap spans.

### Step 7 — Integrate into `PersonList`

File: `dev/frontend/assets/js/components/PersonList.jsx`

Import `PersonPhotoForm` and render `<PersonPhotoForm personId={person.id} />` inside each `<li>`.

### Frontend files

| Action | File |
|--------|------|
| Modify | `dev/frontend/assets/js/clients/PersonClient.js` |
| Create | `dev/frontend/assets/js/components/PersonPhotoForm.jsx` |
| Modify | `dev/frontend/assets/js/components/PersonList.jsx` |
| Create | `dev/frontend/spec/clients/PersonClient/PersonClientUploadPhoto_spec.js` |

---

## Commit order

1. Backend: `FileStorageInterface`, `PhpFileStorage`, `MockFileStorage`, `UploadPersonPhotoEndpoint`, tests
2. Frontend: `PersonClient.uploadPhoto` + spec
3. Frontend: `PersonPhotoForm` + `PersonList` integration

## Notes

- The `MockRequest` needs to support `uploadedFile(string $field): ?array` — add if not already present.
- Do **not** set `Content-Type` manually on the frontend `fetch` call.
- The backend only accepts `image/jpeg`; use `accept="image/jpeg"` on the frontend input.
- No component-level Jasmine specs for `PersonPhotoForm` — consistent with existing components.
- Photos directory (`docker_volumes/photos`) is mounted at `/home/app/app/photos` inside the container.
