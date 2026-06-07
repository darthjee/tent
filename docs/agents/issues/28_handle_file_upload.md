# Issue: Proxy Should Support File Uploads

## Description
The proxy handler currently does not support forwarding file uploads (multipart/form-data) to the backend. This limitation prevents clients from uploading files through the proxy, which is required for many modern web and API applications.

## Problem
- File uploads (e.g., via forms or API clients) are not properly handled or forwarded by the proxy.
- Requests with `multipart/form-data` content type may be dropped, altered, or not reach the backend as intended.

## Expected Behavior
- The proxy should accept file upload requests (typically POST or PUT with `multipart/form-data`), forward the entire request (including files, fields, and headers) to the backend, and return the backend's response to the client.
- All file data and metadata should be preserved.

## Solution
- Update the proxy handler to detect and properly handle file upload requests.
- Ensure that multipart bodies, file streams, and all headers are forwarded without modification.
- Test with various file upload scenarios to confirm correct behavior.

## Benefits
- Enables file upload features in applications using the proxy.
- Improves compatibility with modern web and API clients.

---
See issue for details: https://github.com/darthjee/tent/issues/28
