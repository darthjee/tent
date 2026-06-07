# Plan: Add Photo Endpoint for Dev App Frontend

## Overview

Add photo upload support to the dev-app React frontend: a new `PersonClient.uploadPhoto()` method and a `PersonPhotoForm` component rendered per person in `PersonList`.

## Context

The backend already exposes `POST /persons/:id/photo.json` (implemented in issue 192). It accepts a `multipart/form-data` request with a `photo` field (JPEG only) and returns the person JSON on success (200) or `{ error }` on failure (400, 404, 422, 500).

The frontend follows these conventions:
- HTTP logic lives in `dev/frontend/assets/js/clients/PersonClient.js` (plain `fetch`, no framework).
- UI components live in `dev/frontend/assets/js/components/`, using React 19 + Bootstrap 5.
- Tests live in `dev/frontend/spec/`, mirroring the source structure, using Jasmine + `spyOn(global, 'fetch')`.

**Do not touch `source/`** (the Tent proxy). This issue is scoped entirely to `dev/frontend/`.

## Implementation Steps

### Step 1 — Add `uploadPhoto(id, file)` to `PersonClient`

File: `dev/frontend/assets/js/clients/PersonClient.js`

Add the method inside the existing `PersonClient` class, after `create()`:

```js
async uploadPhoto(id, file) {
  const formData = new FormData();
  formData.append('photo', file);

  const response = await fetch(`/persons/${id}/photo.json`, {
    method: 'POST',
    body: formData,
  });
  if (!response.ok) {
    throw new Error('Failed to upload photo');
  }
  return response.json();
}
```

Do **not** set a `Content-Type` header — the browser sets it automatically with the correct `multipart/form-data` boundary when `body` is a `FormData`.

### Step 2 — Write tests for `uploadPhoto`

File: `dev/frontend/spec/clients/PersonClient/PersonClientUploadPhoto_spec.js`

Follow the exact same structure as `PersonClientCreate_spec.js` (same `describe` nesting, same spy pattern). The `file` argument passed to `uploadPhoto` can be any value in tests — it is passed directly to `FormData.append`, and `fetch` is fully stubbed.

Cases to cover:

**Success:**
```js
spyOn(global, 'fetch').and.returnValue(
  Promise.resolve({ ok: true, json: () => Promise.resolve(mockPersonData) })
);
const result = await client.uploadPhoto(1, mockFile);
expect(global.fetch).toHaveBeenCalledWith(
  '/persons/1/photo.json',
  jasmine.objectContaining({ method: 'POST' })
);
expect(result).toEqual(mockPersonData);
```

**Failure (non-ok response, e.g. 422):**
```js
spyOn(global, 'fetch').and.returnValue(Promise.resolve({ ok: false, status: 422 }));
// expect Error('Failed to upload photo') to be thrown
```

**Network error:**
```js
const networkError = new Error('Network error');
spyOn(global, 'fetch').and.returnValue(Promise.reject(networkError));
// expect the same networkError instance to propagate
```

> Note: verifying the exact `FormData` contents via `toHaveBeenCalledWith` is not straightforward in Jasmine (FormData instances are opaque). Verifying the URL and `method` is sufficient.

### Step 3 — Create `PersonPhotoForm` component

File: `dev/frontend/assets/js/components/PersonPhotoForm.jsx`

Props: `{ personId }`

State:
- `file` — the selected `File` object (or `null`)
- `loading` — boolean
- `error` — string or null
- `success` — boolean

Behaviour:
- File input with `accept="image/jpeg"`. On change, update `file` state from `e.target.files[0]`.
- Submit button disabled when `loading` is true or `file` is null.
- On submit, call `new PersonClient().uploadPhoto(personId, file)`. On success set `success = true` and clear `file`. On error set `error = err.message`. Always clear `loading`.
- Show a Bootstrap `alert-success` div when `success` is true and a `alert-danger` div when `error` is set (same pattern as `PersonForm.jsx`).

Reference skeleton:

```jsx
import { useState } from 'react';
import { PersonClient } from '../clients/PersonClient';

export default function PersonPhotoForm({ personId }) {
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setSuccess(false);
    try {
      await new PersonClient().uploadPhoto(personId, file);
      setSuccess(true);
      setFile(null);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      {error && <div className="alert alert-danger">{error}</div>}
      {success && <div className="alert alert-success">Photo uploaded successfully!</div>}
      <input
        type="file"
        accept="image/jpeg"
        onChange={(e) => setFile(e.target.files[0])}
      />
      <button type="submit" disabled={loading || !file}>
        {loading ? 'Uploading...' : 'Upload Photo'}
      </button>
    </form>
  );
}
```

### Step 4 — Integrate into `PersonList`

File: `dev/frontend/assets/js/components/PersonList.jsx`

Import `PersonPhotoForm` and render it inside each list item:

```jsx
import PersonPhotoForm from './PersonPhotoForm';

// inside the map:
<li key={person.id}>
  {person.first_name} {person.last_name} ({person.birthdate})
  <PersonPhotoForm personId={person.id} />
</li>
```

## Files to Change

| Action | File |
|--------|------|
| Modify | `dev/frontend/assets/js/clients/PersonClient.js` |
| Create | `dev/frontend/assets/js/components/PersonPhotoForm.jsx` |
| Modify | `dev/frontend/assets/js/components/PersonList.jsx` |
| Create | `dev/frontend/spec/clients/PersonClient/PersonClientUploadPhoto_spec.js` |

## Commit order

1. `PersonClient.uploadPhoto` + its spec (client logic and tests together)
2. `PersonPhotoForm` + integration in `PersonList` (UI changes together)

## Notes

- Do **not** set `Content-Type` manually on the `fetch` call.
- The backend only accepts `image/jpeg`; use `accept="image/jpeg"` on the input to guide the user.
- No component-level Jasmine specs are planned (consistent with existing components which have no specs).
- The mock person data shape for tests: `{ id, first_name, last_name, birthdate, created_at, updated_at }` — see existing specs for reference values.
