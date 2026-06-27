# Plan: Add a Photo Upload Page

Issue: [246-add-a-photo-upload-page.md](../issues/246-add-a-photo-upload-page.md)

## Overview

Wire up a dedicated photo upload page (`/#/persons/:id/upload_photo`) that is reachable from the person show page via an "Upload Photo" link. The new page wraps the existing `PhotoUploadForm` component in a Bootstrap card layout (matching `EditPersonPage`) and redirects back to the person show page on success or cancel.

## Context

All API and client-side upload logic already exists (`UploadPersonPhotoEndpoint`, `PersonClient#uploadPhoto`, `PhotoUploadForm`). Only three frontend files need changes: `App.jsx` (new route), `PersonPage.jsx` (new link), and a new `UploadPersonPhotoPage.jsx` component.

## Implementation Steps

### Step 1 — Create `UploadPersonPhotoPage.jsx`

Create `dev/frontend/assets/js/components/UploadPersonPhotoPage.jsx`. The component should:
- Read `id` from `useParams()`.
- Fetch the person via `PersonClient#get(id)` to display their name in the card header.
- Render a Bootstrap card (same structure as `EditPersonPage`) with header `"Upload Photo for {first_name} {last_name}"`.
- Place `<PhotoUploadForm personId={id} onSuccess={...} onCancel={...} />` in the card body.
- Use `useNavigate()` to redirect to `/persons/:id` on both `onSuccess` and `onCancel`.

### Step 2 — Add the route in `App.jsx`

In `dev/frontend/assets/js/components/App.jsx`, import `UploadPersonPhotoPage` and add a route before the existing `/persons/:id` route:

```jsx
<Route path="/persons/:id/upload_photo" element={<UploadPersonPhotoPage />} />
```

The new route must be placed **before** the `/persons/:id` route to avoid the router matching `/persons/:id` when the path is `/persons/:id/upload_photo`.

### Step 3 — Add the "Upload Photo" link in `PersonPage.jsx`

In `dev/frontend/assets/js/components/PersonPage.jsx`, add a `<Link>` to `/persons/${id}/upload_photo` styled consistently with the existing "Edit" link (`className="btn btn-secondary btn-sm"`). Place it next to the "Edit" link.

### Step 4 — Add Jasmine tests for the new component

Add a spec file at `dev/frontend/spec/components/UploadPersonPhotoPage_spec.js` (or similar path following project conventions) covering:
- Loading state while fetching person data.
- Rendered card header shows the person's full name.
- `onSuccess` callback redirects to `/persons/:id`.
- `onCancel` callback redirects to `/persons/:id`.

## Files to Change

- `dev/frontend/assets/js/components/UploadPersonPhotoPage.jsx` — new page component (create)
- `dev/frontend/assets/js/components/App.jsx` — add route for `/persons/:id/upload_photo`
- `dev/frontend/assets/js/components/PersonPage.jsx` — add "Upload Photo" link

## CI Checks

- `dev/frontend`: `npm run coverage` (CI job: `dev_frontend_test`)
- `dev/frontend`: `npm run lint` (CI job: `dev_frontend_checks`)

## Notes

- The `/persons/:id/upload_photo` route must be declared before `/persons/:id` in `App.jsx` so React Router does not swallow the upload_photo segment.
- The `PhotoUploadForm` already handles both success and error display internally; `UploadPersonPhotoPage` only needs to pass `onSuccess` and `onCancel` callbacks.
- No API, backend, or infrastructure changes are required.
