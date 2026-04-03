# Issue 193: Add Photo Endpoint for Dev App Frontend

## Description

Add photo upload support to the dev-app React frontend (`dev/frontend/`). The backend endpoint already exists (issue 192): `POST /persons/:id/photo.json`, which accepts `multipart/form-data` with a `photo` field (JPEG only) and returns the person JSON on success or `{ error }` on failure.

## Scope

This issue covers only the **frontend** (`dev/frontend/`). Do not touch `source/` (the Tent proxy).

## What to implement

### 1. `PersonClient.uploadPhoto(id, file)` — `dev/frontend/assets/js/clients/PersonClient.js`

New method on the existing `PersonClient` class:

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

Do **not** set `Content-Type` manually — the browser sets it with the correct `multipart/form-data` boundary.

### 2. Spec for `uploadPhoto` — `dev/frontend/spec/clients/PersonClient/PersonClientUploadPhoto_spec.js`

Follow the pattern of `PersonClientCreate_spec.js`. Cover:
- Success: stub `fetch` as `ok: true`, verify URL (`/persons/1/photo.json`), method (`POST`), and return value.
- Failure (non-ok response): verify `Error('Failed to upload photo')` is thrown.
- Network error: verify the rejection propagates.

### 3. `PersonPhotoForm` component — `dev/frontend/assets/js/components/PersonPhotoForm.jsx`

New React component. Props: `personId`. Follow the same Bootstrap 5 + state pattern as `PersonForm.jsx`:
- File input with `accept="image/jpeg"`.
- Submit button (disabled while loading).
- Inline success/error feedback via Bootstrap alert divs.
- On submit, call `new PersonClient().uploadPhoto(personId, file)`.

### 4. Integrate into `PersonList` — `dev/frontend/assets/js/components/PersonList.jsx`

Render `<PersonPhotoForm personId={person.id} />` inside each `<li>` item.

## Files to change

| Action | File |
|--------|------|
| Modify | `dev/frontend/assets/js/clients/PersonClient.js` |
| Create | `dev/frontend/assets/js/components/PersonPhotoForm.jsx` |
| Modify | `dev/frontend/assets/js/components/PersonList.jsx` |
| Create | `dev/frontend/spec/clients/PersonClient/PersonClientUploadPhoto_spec.js` |

## Reference

See the plan: `docs/agents/plans/193_add-photo-endpoint-dev-app-frontend/plan.md`

See issue: https://github.com/darthjee/tent/issues/193
