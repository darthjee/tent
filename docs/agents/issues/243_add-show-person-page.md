# Issue 243: Add Show Person Page

## Description
The dev frontend app currently has a `/#/persons` route listing all persons. We need to add a `/#/persons/:id` route that shows a single person's details, with links from the list to that page. This requires a new backend endpoint and frontend routing support.

## Problem
- There is no detail page for an individual person.
- The frontend has no router installed — both `PersonForm` and `PersonList` render on the same page with no URL-based navigation.
- The backend has no `GET /persons/:id` endpoint.

## Expected Behavior
- Each person's name in `PersonList` is a clickable link navigating to `/#/persons/:id`.
- The `/#/persons/:id` page loads the person's data from the backend and displays their details (first name, last name, birthdate) and their photo if one exists.
- If the person is not found, a 404 or appropriate error is shown.
- `PersonForm` remains on the same page as `PersonList` (no separate route for now).

## Solution

### Backend (`dev/api/`)
- Add `ShowPersonEndpoint` that handles `GET /persons/:id`, calls `Person::find($id)`, and returns the person as JSON (or 404 if not found).
- Register the route in `index.php`: `GET /persons/:id → ShowPersonEndpoint`.

### Frontend (`dev/frontend/`)
- Install `react-router-dom` and wrap `App` in `<HashRouter>`.
- Add `get(id)` to `PersonClient` (`GET /persons/:id`).
- Create a `PersonPage` component that fetches and displays the person's details and photo (from `/photos/<id>.jpg`) if the photo exists.
- Update `App.jsx` to route `/#/persons` → `<PersonForm> + <PersonList>` and `/#/persons/:id` → `<PersonPage>`.
- Wrap each person's name in `PersonList` with a `<Link>` to `/#/persons/${person.id}`.

## Benefits
- Users can navigate to a dedicated page for each person.
- Establishes the foundation for future person-specific features (edit, delete, photo display).

---
See issue for details: https://github.com/darthjee/tent/issues/243
