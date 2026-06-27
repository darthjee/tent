# Frontend Plan: Add Edit Person Page

Main plan: [plan.md](plan.md)

## Shared contracts

This agent consumes `PATCH /persons/:id` produced by the dev-api agent:

- **Method:** `PATCH`, **Path:** `/persons/:id`
- **Request body:** JSON `{ first_name, last_name, birthdate }` (all optional but at least one required).
- **Success (200):** full updated person JSON.
- **Errors:** 404 (not found) or 422 (invalid body).

`PersonClient.update(id, data)` must:
- Send `PATCH /persons/<id>` with `Content-Type: application/json` and `JSON.stringify(data)` as body.
- Resolve with the updated person object on success.
- Throw `Error('Failed to update person')` on non-OK response.

## Implementation Steps

### Step 1 — Add `update()` to `PersonClient`

In `dev/frontend/assets/js/clients/PersonClient.js`, add after the `get()` method:

```js
async update(id, data) {
  const response = await fetch(`/persons/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  if (!response.ok) {
    throw new Error('Failed to update person');
  }
  return response.json();
}
```

### Step 2 — Add `EditPersonPage` component

Create `dev/frontend/assets/js/components/EditPersonPage.jsx`:

- Use `useParams()` to get `id`.
- On mount, fetch the existing person data via `(new PersonClient()).get(id)` and pre-fill the form.
- Display a form with fields: First Name, Last Name, Birthdate (mirroring `PersonForm.jsx` style).
- On submit, call `(new PersonClient()).update(id, { first_name, last_name, birthdate })`.
- On success, navigate to `/persons/:id` via `useNavigate()`.
- Handle and display errors (loading state, error state).

### Step 3 — Add Edit button/link to `PersonPage`

In `dev/frontend/assets/js/components/PersonPage.jsx`, add a `<Link>` (or `<a>`) to `/#/persons/:id/edit` displayed as an "Edit" button alongside the existing "Back to list" link.

### Step 4 — Register the new route in `App.jsx`

In `dev/frontend/assets/js/components/App.jsx`:

1. Import `EditPersonPage`.
2. Add `<Route path="/persons/:id/edit" element={<EditPersonPage />} />` before or after the `/persons/:id` route.

### Step 5 — Write Jasmine tests

Create `dev/frontend/spec/clients/PersonClient/PersonClientUpdate_spec.js` following the pattern of `PersonClientGet_spec.js`:

- Test: successful PATCH call resolves with the updated person.
- Test: non-OK response throws `Error('Failed to update person')`.
- Test: network rejection propagates the original error.

## Files to Change

- `dev/frontend/assets/js/clients/PersonClient.js` — add `update()` method
- `dev/frontend/assets/js/components/EditPersonPage.jsx` — new component
- `dev/frontend/assets/js/components/PersonPage.jsx` — add Edit link
- `dev/frontend/assets/js/components/App.jsx` — register new route, import new component
- `dev/frontend/spec/clients/PersonClient/PersonClientUpdate_spec.js` — new Jasmine test

## CI Checks

- `dev/frontend`: `make test-frontend` (Jasmine tests)

## Notes

- Follow the Bootstrap 5 class conventions used in `PersonForm.jsx` for the edit form.
- Use `useNavigate()` from `react-router-dom` for the post-save redirect.
- The route `/persons/:id/edit` must be registered before `/persons/:id` (or use React Router's exact matching) to avoid the `:id` segment capturing the string `edit`.
