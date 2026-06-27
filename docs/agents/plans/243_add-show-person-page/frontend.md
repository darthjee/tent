# Frontend Plan: Add Show Person Page

Main plan: [plan.md](plan.md)

## Shared contracts

This agent consumes the `GET /persons/:id` endpoint produced by the dev-api agent.

**Success (HTTP 200):** JSON object with fields `id` (int), `first_name` (string), `last_name` (string), `birthdate` (string|null), `created_at` (string), `updated_at` (string).

**Not found (HTTP 404):** JSON object `{"error": "Person not found"}`.

Photos are served at `/photos/<id>.jpg`. The `PersonPage` should attempt to display this image and gracefully skip it if it does not exist (e.g. using an `onError` handler to hide the `<img>` element).

## Implementation Steps

### Step 1 — Install `react-router-dom`

Add `react-router-dom` to `dev/frontend/package.json` dependencies. Use version `^7.0.0` (compatible with React 19). Run `yarn install` (or `npm install`) inside the container to update `yarn.lock` / `package-lock.json`.

### Step 2 — Add `get(id)` to `PersonClient`

Add a `get(id)` method to `dev/frontend/assets/js/clients/PersonClient.js`:

```js
async get(id) {
  const response = await fetch(`/persons/${id}`);
  if (!response.ok) {
    throw new Error('Failed to fetch person data');
  }
  return response.json();
}
```

### Step 3 — Create `PersonPage` component

Create `dev/frontend/assets/js/components/PersonPage.jsx`.

- Use `useParams()` from `react-router-dom` to extract `:id`.
- Fetch the person on mount using `(new PersonClient()).get(id)` (via `useEffect` + `useState`, following the same pattern as `PersonList`).
- Display loading / error states.
- On success, render the person's `first_name`, `last_name`, and `birthdate`.
- Render `<img src={/photos/${person.id}.jpg} alt="photo" onError={(e) => e.target.style.display='none'} />` to show the photo if it exists.
- Include a back link to `/#/persons`.

### Step 4 — Update `App.jsx` with router and routes

Wrap the app in `<HashRouter>` (imported from `react-router-dom`) and define routes:

- `/#/persons` → renders `<PersonForm />` and `<PersonList />`
- `/#/persons/:id` → renders `<PersonPage />`

Update `dev/frontend/assets/js/components/App.jsx`:

```jsx
import { HashRouter, Routes, Route } from 'react-router-dom';
import PersonList from './PersonList';
import PersonForm from './PersonForm';
import PersonPage from './PersonPage';

export default function App() {
  return (
    <HashRouter>
      <Routes>
        <Route path="/persons" element={<><PersonForm /><PersonList /></>} />
        <Route path="/persons/:id" element={<PersonPage />} />
      </Routes>
    </HashRouter>
  );
}
```

### Step 5 — Add `<Link>` in `PersonList`

In `dev/frontend/assets/js/components/PersonList.jsx`, import `Link` from `react-router-dom` and wrap each person's name:

```jsx
import { Link } from 'react-router-dom';
// ...
<Link to={`/persons/${person.id}`}>{person.first_name} {person.last_name}</Link>
```

### Step 6 — Write Jasmine tests

Add tests for the new `get(id)` method in a new spec file:
`dev/frontend/spec/clients/PersonClient/PersonClientGet_spec.js`

Follow the existing pattern in `PersonClientList_spec.js`:
- Spy on `global.fetch`.
- Test successful fetch returns parsed JSON.
- Test that a non-ok response throws `'Failed to fetch person data'`.
- Test that a network rejection propagates the error.

## Files to Change

- `dev/frontend/package.json` — add `react-router-dom` dependency
- `dev/frontend/assets/js/clients/PersonClient.js` — add `get(id)` method
- `dev/frontend/assets/js/components/PersonPage.jsx` — new component
- `dev/frontend/assets/js/components/App.jsx` — add `HashRouter` and `Routes`
- `dev/frontend/assets/js/components/PersonList.jsx` — wrap name with `<Link>`
- `dev/frontend/spec/clients/PersonClient/PersonClientGet_spec.js` — new spec

## CI Checks

- `dev/frontend/`: `docker compose run --rm frontend_dev npm test` (CI job: `dev_frontend_test`)
- `dev/frontend/`: `docker compose run --rm frontend_dev npm run lint` (CI job: `dev_frontend_checks`)

## Notes

- `react-router-dom` v7 requires React 18+; this project uses React 19 so it is compatible.
- The `HashRouter` uses `/#/` URLs which avoids any server-side routing changes — Tent's proxy config does not need updating.
- Since Jasmine tests run in Node (no DOM), component tests for `PersonPage` are out of scope here; only the `PersonClient.get()` method needs a spec.
- After installing the package, verify `yarn.lock` (or `package-lock.json`) is committed alongside `package.json`.
