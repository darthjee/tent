# Issue 195: Handle File Upload on Tent

## Description
The Tent application should be able to handle file uploads and forward them to the backend. This can be verified using the photo upload endpoint created in issues 192–194.

## Context
Related to issue #28 (proxy should support file uploads / multipart/form-data).

## Expected Behavior
- Tent correctly proxies `multipart/form-data` requests, including file streams and all headers, to the backend without modification.
- Verified end-to-end using the `POST /persons/:id/photo.json` endpoint.

---
See issue for details: https://github.com/darthjee/tent/issues/195
