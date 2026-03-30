# Plan: Issue 194 — Add Ability to Upload a Photo to a Person

## Goal

On the person listing page, each person entry should have an "Upload Photo" link that opens a form. Submitting the form uploads a photo for that person via `POST /persons/:id/photo`.

---

## Context

- The frontend is React 19 + Vite with TanStack Query and Bootstrap 5.
- `PersonClient.js` already has `list()` and `create()` methods; it needs a new `uploadPhoto()` method.
- `PersonList.jsx` renders a `<ul>` of persons; it needs a per-person upload trigger.
- Jasmine specs live in `dev/frontend/spec/`, mirroring `assets/js/`. HTTP calls are stubbed via `spyOn(global, 'fetch')`.
- This issue depends on issue 193 (Tent proxy route) being live for end-to-end functionality, but the frontend code can be developed and unit-tested independently.

---

## Implementation Steps

### Step 1 — `PersonClient.js`: add `uploadPhoto(personId, file)`

**File:** `dev/frontend/assets/js/clients/PersonClient.js`

```javascript
async uploadPhoto(personId, file) {
  const formData = new FormData();
  formData.append('photo', file);

  const response = await fetch(`/persons/${personId}/photo`, {
    method: 'POST',
    body: formData,
    // Do NOT set Content-Type manually — the browser sets it with the
    // correct multipart boundary when using FormData.
  });

  if (!response.ok) {
    throw new Error('Failed to upload photo');
  }
  return response.json();
}
```

Key point: passing `FormData` to `fetch` without an explicit `Content-Type` header causes the browser to generate the correct `multipart/form-data; boundary=…` header automatically.

---

### Step 2 — New component: `PhotoUploadForm`

**File:** `dev/frontend/assets/js/components/PhotoUploadForm.jsx`

Props:
- `personId` (number) — the ID of the person to attach the photo to.
- `onSuccess` (function) — callback invoked after a successful upload.
- `onCancel` (function) — callback to close/hide the form.

Internal state:
- `file` — the selected `File` object (from `<input type="file">`).
- `uploading` (boolean) — disables the submit button while the request is in flight.
- `error` (string|null) — displays an error message on failure.

Render:
```jsx
<form onSubmit={handleSubmit}>
  <input type="file" accept="image/*" onChange={e => setFile(e.target.files[0])} />
  <button type="submit" disabled={!file || uploading}>Upload</button>
  <button type="button" onClick={onCancel}>Cancel</button>
  {error && <p className="text-danger">{error}</p>}
</form>
```

`handleSubmit`:
1. Set `uploading = true`.
2. Call `(new PersonClient()).uploadPhoto(personId, file)`.
3. On success: call `onSuccess()`.
4. On error: set `error` message, set `uploading = false`.

---

### Step 3 — Update `PersonList.jsx`

**File:** `dev/frontend/assets/js/components/PersonList.jsx`

Changes:
1. Add state: `uploadingForPersonId` (number|null) — tracks which person's upload form is open (null = none).
2. Import `PhotoUploadForm`.
3. In the person list item, add an "Upload Photo" link/button:
   ```jsx
   <button onClick={() => setUploadingForPersonId(person.id)}>
     Upload Photo
   </button>
   ```
4. Conditionally render `PhotoUploadForm` below (or instead of) the button:
   ```jsx
   {uploadingForPersonId === person.id && (
     <PhotoUploadForm
       personId={person.id}
       onSuccess={() => setUploadingForPersonId(null)}
       onCancel={() => setUploadingForPersonId(null)}
     />
   )}
   ```

---

### Step 4 — Tests

#### 4a — `PersonClient.uploadPhoto` spec

**File:** `dev/frontend/spec/clients/PersonClient/PersonClientUploadPhoto_spec.js`

Cases:
- Successful upload: `fetch` is called with `POST /persons/1/photo`, `body` is a `FormData` instance, no explicit `Content-Type` header; resolves with the parsed JSON.
- Failed upload (non-ok response): throws `Error('Failed to upload photo')`.
- Network error (rejected promise): propagates the rejection.

Spy on `global.fetch` as in the existing specs.

Checking that `Content-Type` is absent from the options is important to ensure the browser generates the multipart boundary correctly.

#### 4b — `PhotoUploadForm` spec

**File:** `dev/frontend/spec/components/PhotoUploadForm_spec.js`

Cases:
- Renders file input and Upload/Cancel buttons.
- Submit button is disabled when no file is selected.
- Submit button is disabled while upload is in progress.
- On success: calls `onSuccess` callback.
- On failure: displays error message.

Use Jasmine spies to stub `PersonClient.prototype.uploadPhoto`.

#### 4c — `PersonList` spec update

**File:** `dev/frontend/spec/components/PersonList_spec.js` (create if not yet present)

Cases:
- Each person row contains an "Upload Photo" button.
- Clicking the button shows `PhotoUploadForm` for that person.
- Successful upload closes the form (upload form no longer rendered).
- Clicking Cancel closes the form.

---

## Files Changed / Created

| Action | Path |
|--------|------|
| Modify | `dev/frontend/assets/js/clients/PersonClient.js` |
| Create | `dev/frontend/assets/js/components/PhotoUploadForm.jsx` |
| Modify | `dev/frontend/assets/js/components/PersonList.jsx` |
| Create | `dev/frontend/spec/clients/PersonClient/PersonClientUploadPhoto_spec.js` |
| Create | `dev/frontend/spec/components/PhotoUploadForm_spec.js` |
| Create/Modify | `dev/frontend/spec/components/PersonList_spec.js` |

---

## Commit order

1. `PersonClient.uploadPhoto` — method + spec
2. `PhotoUploadForm` component — new file + spec
3. `PersonList` update — integrate button + form + spec
