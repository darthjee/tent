# Issue 192: Add Photo Endpoint for Dev App Backend

## Description
In the backend of the dev app, we need an endpoint that allows uploading a photo to a person.

## Endpoint

```
POST /persons/:id/photo.json
```

## Expected Behavior
- The endpoint accepts a file upload (multipart/form-data) and saves the photo to disk.
- The photo is saved as `/home/app/app/photos/<person_id>.jpg`. This directory is the same as `docker_volumes/photos` (mounted volume).
- No database tracking is needed — the filename convention (`<person_id>.jpg`) is enough to associate the photo with the person.
- Returns an appropriate JSON response on success or failure.

---
See issue for details: https://github.com/darthjee/tent/issues/192
