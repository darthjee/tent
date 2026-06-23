# Issue 193: Add Photo Upload to Dev App (Frontend + Backend)

## Description

Add photo upload support to the dev-app — both the PHP backend (`dev/api/`) and the React frontend (`dev/frontend/`). This allows uploading a JPEG photo for a person and serves as the integration layer for testing Tent's file-upload handling (see issue 195).

## Scope

This issue covers only the **dev-app** (`dev/api/` and `dev/frontend/`). Do not touch `source/` (the Tent proxy).

## Backend — `dev/api/`

### Endpoint

```
POST /persons/:id/photo.json
```

- Accepts `multipart/form-data` with a `photo` field (JPEG only).
- Saves the file to `/home/app/app/photos/<person_id>.jpg` (mounted as `docker_volumes/photos`).
- No database changes — filename convention is the association.
- Returns the person JSON on success or `{ "error": "..." }` on failure.

### Files

| Action | File |
|--------|------|
| Create | `dev/api/source/lib/api_dev/endpoints/UploadPersonPhotoEndpoint.php` |
| Create | `dev/api/source/lib/api_dev/file_storage/FileStorageInterface.php` |
| Create | `dev/api/source/lib/api_dev/file_storage/PhpFileStorage.php` |
| Create | `dev/api/tests/unit/lib/api_dev/endpoints/UploadPersonPhotoEndpointTest.php` |
| Create | `dev/api/tests/support/models/MockFileStorage.php` |

## Frontend — `dev/frontend/`

### `PersonClient.uploadPhoto(id, file)`

New method on `dev/frontend/assets/js/clients/PersonClient.js`:

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

Do **not** set `Content-Type` manually — the browser sets it with the correct boundary.

### `PersonPhotoForm` component

New React component at `dev/frontend/assets/js/components/PersonPhotoForm.jsx`. Props: `personId`. Uses Bootstrap 5:
- File input with `accept="image/jpeg"`.
- Submit button (disabled while loading).
- Inline success/error feedback via Bootstrap alert spans.
- On submit, calls `new PersonClient().uploadPhoto(personId, file)`.

### Integration into `PersonList`

Render `<PersonPhotoForm personId={person.id} />` inside each `<li>` in `dev/frontend/assets/js/components/PersonList.jsx`.

### Files

| Action | File |
|--------|------|
| Modify | `dev/frontend/assets/js/clients/PersonClient.js` |
| Create | `dev/frontend/assets/js/components/PersonPhotoForm.jsx` |
| Modify | `dev/frontend/assets/js/components/PersonList.jsx` |
| Create | `dev/frontend/spec/clients/PersonClient/PersonClientUploadPhoto_spec.js` |

---
See issue for details: https://github.com/darthjee/tent/issues/193
