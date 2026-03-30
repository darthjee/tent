# Issue 192: Add Photo Endpoint for Dev App Backend

## Description
In the backend of the dev app, we need an endpoint that allows uploading a photo to a person.

## Endpoint

```
POST /persons/:id/photo.json
```

## Expected Behavior
- The endpoint accepts a file upload (multipart/form-data) and associates the photo with the given person.
- Returns an appropriate JSON response on success or failure.

---
See issue for details: https://github.com/darthjee/tent/issues/192
