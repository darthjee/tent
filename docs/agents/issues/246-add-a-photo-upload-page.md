# Issue: Add a photo upload page

## Description
On the person show page (`/#/persons/:id`), add an "Upload Photo" link that navigates to a dedicated upload photo page (`/#/persons/:id/upload_photo`). The upload page contains a photo upload form inside a card with a header showing the person's name; on successful submission or cancel the user is returned to the person show page.

## Problem
There is currently no way to upload a photo from the person show page. The `PhotoUploadForm` component exists and is used inline in the person list (`PersonList.jsx`), but it is not accessible as a dedicated page reachable from the show page.

## Expected Behavior
- The person show page (`/#/persons/:id`) has an "Upload Photo" link styled consistently with the existing "Edit" link.
- Clicking it navigates to `/#/persons/:id/upload_photo`.
- That page uses the same Bootstrap card layout as `EditPersonPage`, with a card header showing the person's name (e.g. "Upload Photo for John Doe").
- The card body contains the `PhotoUploadForm` component.
- On successful upload, the user is redirected back to the person show page (`/#/persons/:id`).
- On cancel, the user is also returned to the person show page.

## Solution
All API and client-side upload logic already exists (`UploadPersonPhotoEndpoint`, `PersonClient#uploadPhoto`, `PhotoUploadForm`). Only frontend wiring is needed:

1. **`dev/frontend/assets/js/App.jsx`** — add a route for `/persons/:id/upload_photo` pointing to a new `UploadPersonPhotoPage` component.
2. **`dev/frontend/assets/js/components/UploadPersonPhotoPage.jsx`** — new page component that reads `personId` from `useParams`, fetches the person's name, renders a Bootstrap card with a header ("Upload Photo for {first_name} {last_name}") and `PhotoUploadForm` in the body, and uses `useNavigate` to redirect to `/persons/:id` on `onSuccess` or `onCancel`.
3. **`dev/frontend/assets/js/components/PersonPage.jsx`** — add an "Upload Photo" link pointing to `/persons/:id/upload_photo`, styled consistently with the existing "Edit" link.

## Benefits
Provides a dedicated, bookmarkable page for photo upload that is naturally reachable from the person show page, improving the UX without duplicating any existing upload logic.
