# Plan: Add Show Person Page

Issue: [243_add-show-person-page.md](../issues/243_add-show-person-page.md)

## Overview

Add a `GET /persons/:id` backend endpoint and a `/#/persons/:id` frontend route so that each person in the list links to a dedicated detail page showing their name, birthdate, and photo. The backend returns the person as JSON (or 404 if not found); the frontend introduces `react-router-dom` for hash-based routing, a new `PersonPage` component, and a `get(id)` method on `PersonClient`.

## Agents involved

- [dev-api](dev-api.md)
- [frontend](frontend.md)

## Shared contracts

### `GET /persons/:id`

| Field | Type | Nullable | Notes |
|-------|------|----------|-------|
| `id` | int | no | Auto-increment primary key |
| `first_name` | string | no | |
| `last_name` | string | no | |
| `birthdate` | string (ISO 8601 date) | yes | |
| `created_at` | string (datetime) | no | |
| `updated_at` | string (datetime) | no | |

**Success:** HTTP 200, `Content-Type: application/json`, body is a single object (not an array).

**Not found:** HTTP 404, `Content-Type: application/json`, body `{"error": "Person not found"}`.

### Photo URL

Photos are served at `/photos/<id>.jpg`. The frontend checks whether the image exists by attempting to load it and falling back gracefully if it does not.
