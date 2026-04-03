# Plan: Add Photo Endpoint for Dev App Frontend

## Overview

Add photo upload support to the dev-app React frontend: a new `PersonClient.uploadPhoto()` method and a `PersonPhotoForm` component rendered per person in `PersonList`.

## Context

The backend already exposes `POST /persons/:id/photo.json` (implemented in issue 192). It accepts a `multipart/form-data` request with a `photo` field (JPEG only) and returns the person JSON on success (200) or `{ error }` on failure (400, 404, 422, 500).

The frontend follows these conventions:
- HTTP logic lives in `dev/frontend/assets/js/clients/PersonClient.js` (plain `fetch`, no framework).
- UI components live in `dev/frontend/assets/js/components/`, using React 19 + Bootstrap 5.
- Tests live in `dev/frontend/spec/`, mirroring the source structure, using Jasmine + `spyOn(global, 'fetch')`.

## Implementation Steps

### Step 1 — Add `uploadPhoto(id, file)` to `PersonClient`

Add a new method to `PersonClient.js`:

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

No `Content-Type` header is set manually — the browser sets it automatically with the correct boundary for `multipart/form-data`.

### Step 2 — Write tests for `uploadPhoto`

Create `spec/clients/PersonClient/PersonClientUploadPhoto_spec.js`, following the pattern of `PersonClientCreate_spec.js`:

- success: stub `fetch` as `ok: true`, verify URL, method, and return value.
- failure (non-ok response): verify that `Error('Failed to upload photo')` is thrown.
- network error: verify that the rejection propagates.

### Step 3 — Create `PersonPhotoForm` component

Create `dev/frontend/assets/js/components/PersonPhotoForm.jsx`:

- Props: `personId`
- State: `file`, `loading`, `error`, `success`
- Renders a file input (`accept="image/jpeg"`) and a submit button.
- On submit, calls `new PersonClient().uploadPhoto(personId, file)`.
- Shows success/error feedback inline (same Bootstrap alert pattern as `PersonForm`).

### Step 4 — Integrate into `PersonList`

In `PersonList.jsx`, render `<PersonPhotoForm personId={person.id} />` inside each `<li>` for every person in the list.

## Files to Change

- `dev/frontend/assets/js/clients/PersonClient.js` — add `uploadPhoto(id, file)` method
- `dev/frontend/assets/js/components/PersonPhotoForm.jsx` — new component (create)
- `dev/frontend/assets/js/components/PersonList.jsx` — render `PersonPhotoForm` per person
- `dev/frontend/spec/clients/PersonClient/PersonClientUploadPhoto_spec.js` — new spec file (create)

## Notes

- No `Content-Type` header should be set manually on the `fetch` call; omitting it lets the browser add the correct `multipart/form-data` boundary.
- The backend only accepts `image/jpeg`; the file input should use `accept="image/jpeg"` to guide the user, though validation still happens on the backend.
- No component-level Jasmine specs are planned (consistent with existing components which have no specs).
