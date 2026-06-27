# Issue: Add Edit Person Page

## Description
In the dev application, the show person page (`/#/persons/:id`) needs an edit button that navigates to a new edit person page (`/#/persons/:id/edit`). The edit page provides a pre-filled form to update the person's data, and after saving it redirects back to the show person page.

## Problem
There is currently no way to edit a person's data in the dev application. The show person page (`PersonPage`) only displays information and provides a back link. There is no route, no form, and no backend endpoint to update an existing person.

## Expected Behavior
- The show person page (`/#/persons/:id`) displays an "Edit" button/link.
- Clicking it navigates to `/#/persons/:id/edit`.
- The edit page shows a form pre-filled with the person's current `first_name`, `last_name`, and `birthdate`.
- Submitting the form sends a `PATCH /persons/:id` request with the updated data.
- On success, the user is redirected back to `/#/persons/:id`.

## Solution
1. **Backend (dev/api)**: Add `PATCH /persons/:id` route and `UpdatePersonEndpoint` class that reads JSON body and updates the person record.
2. **API Client (dev/frontend)**: Add an `update(id, data)` method to `PersonClient` that sends a `PATCH` request.
3. **Frontend**: Add a new `EditPersonPage` component with a pre-filled form (first name, last name, birthdate). Register it in `App.jsx` at route `/persons/:id/edit`. Add an Edit button/link to `PersonPage` that points to `/persons/:id/edit`.
