# Plan: Add Edit Person Page

Issue: [245-add-edit-person-page.md](../issues/245-add-edit-person-page.md)

## Overview

Add a full edit-person flow to the dev application: a `PATCH /persons/:id` backend endpoint, an `update()` method in `PersonClient`, and a new `EditPersonPage` React component reachable at `/#/persons/:id/edit`. The show page (`PersonPage`) gains an Edit button/link. On save the edit page redirects back to the show page.

## Agents involved

- [dev-api](dev-api.md)
- [frontend](frontend.md)

## Shared contracts

### `PATCH /persons/:id`

- **Method:** `PATCH`
- **Path:** `/persons/:id` (integer ID in the URL segment)
- **Request body:** JSON object with any subset of:
  ```json
  { "first_name": "string", "last_name": "string", "birthdate": "YYYY-MM-DD" }
  ```
- **Success response:** HTTP 200, `Content-Type: application/json`, body is the full updated person object:
  ```json
  {
    "id": 1,
    "first_name": "string",
    "last_name": "string",
    "birthdate": "YYYY-MM-DD",
    "created_at": "datetime string",
    "updated_at": "datetime string"
  }
  ```
- **Not-found response:** HTTP 404, `Content-Type: application/json`, body: `{ "error": "Person not found" }`
- **Invalid-body response:** HTTP 422, `Content-Type: application/json`, body: `{ "error": "At least one field required" }`

### `PersonClient.update(id, data)`

- Sends `PATCH /persons/<id>` with `Content-Type: application/json` and the data object as JSON body.
- Resolves with the updated person object on success.
- Throws `Error('Failed to update person')` on non-OK response.
