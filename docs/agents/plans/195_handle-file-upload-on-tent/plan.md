# Plan: Issue 195 — Handle File Upload on Tent

## Goal

Make Tent transparently forward `multipart/form-data` (file upload) requests to the backend, preserving the raw multipart body, all headers (including the `Content-Type` with the boundary), and the uploaded file data without modification.

---

## Context and Root Cause

### Why file uploads fail today

When a `multipart/form-data` request reaches Apache/PHP, PHP's **post data reading** feature (`enable_post_data_reading`, enabled by default) automatically parses the multipart body and populates `$_POST` and `$_FILES`. As a side-effect, `php://input` is **emptied** — it returns an empty string.

`Tent\Models\Request::body()` reads from `php://input`:

```php
public function body()
{
    return file_get_contents('php://input');  // returns '' for multipart
}
```

`ProxyRequestHandler` then forwards `$request->body()` as the POST body:

```php
$this->httpClient->request($request->requestMethod(), $url, $request->headers(), $request->body());
```

`CurlHttpExecutor\Post::addExtraCurlOptions()` passes that (empty) string to cURL:

```php
curl_setopt($this->curlHandle, CURLOPT_POST, true);
curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $this->body);  // ''
```

The backend receives an empty body — the upload is completely lost.

### Why the rest of the pipeline is correct once the body is available

- `CurlHttpExecutor\Base` already forwards the original `Content-Type` header (including the multipart `boundary` parameter) via `CURLOPT_HTTPHEADER`.
- cURL treats a string value for `CURLOPT_POSTFIELDS` as a raw body and does **not** re-encode it.
- Therefore, once the raw multipart bytes are available in `Request::body()`, no other changes to the cURL layer are required.

---

## Implementation Steps

### Step 1 — Disable PHP's automatic multipart parsing in Tent

**File:** `source/source/.htaccess`

Add the following directive so PHP does not consume the request body before Tent reads it:

```apache
php_value enable_post_data_reading Off
```

With this setting, `$_POST` and `$_FILES` will always be empty inside the Tent process — which is correct, as Tent never accesses them. `php://input` will contain the full raw body for every request method and content type, including `multipart/form-data`.

This is a one-line change and requires no code modifications.

> **Note:** The dev API (`dev/api/source/.htaccess`) must **not** have this setting, because `api_dev` is a regular PHP application that relies on `$_FILES` being populated by PHP. Tent is the only process that should disable post data reading.

---

### Step 2 — Verify `CurlHttpExecutor\Post` handles raw multipart correctly

No code changes are needed, but the behaviour should be documented and tested.

When `CURLOPT_POSTFIELDS` receives a **string** (not an array), cURL sends it as the raw POST body without any re-encoding. The `Content-Type` set in `CURLOPT_HTTPHEADER` takes precedence over cURL's own auto-generated headers. Therefore, the original `Content-Type: multipart/form-data; boundary=----WebKitFormBoundary…` header is forwarded correctly.

If a regression is ever introduced (e.g., switching `CURLOPT_POSTFIELDS` to accept an array), cURL would silently switch to its own multipart encoding, which is unlikely to match the original boundary. A regression test (step 3b) guards against this.

---

### Step 3 — Tests

#### 3a — Unit test: `Request::body()` returns raw multipart bytes

**File:** `source/tests/unit/lib/models/RequestTest.php` (extend existing test)

Because `php://input` behaviour depends on the PHP runtime, the unit test should use the `options` injection path that `Request` already supports:

```php
$request = new Request(['body' => "--boundary\r\nContent-Disposition: …\r\n\r\ndata\r\n--boundary--"]);
$this->assertStringContainsString('--boundary', $request->body());
```

This confirms `body()` passes through opaque binary strings unchanged — no encoding or truncation.

#### 3b — Unit test: `CurlHttpExecutor\Post` preserves raw body string

**File:** `source/tests/unit/lib/http/CurlHttpExecutor/PostTest.php` (extend existing tests if any)

Verify that when `body` is a raw multipart string, `CURLOPT_POSTFIELDS` is set to exactly that string (not an array, not URL-encoded). This can be tested by inspecting the options passed to a cURL handle stub or by checking the options array returned by `curl_getinfo`.

#### 3c — Integration test: end-to-end file upload through Tent

**File:** `source/tests/integration/FileUploadProxyTest.php` (new)

Uses the `tent_httpbin` service (already linked in `tent_tests`) which can echo back request details.

Test flow:
1. Create a temporary file with known content.
2. Send `POST /upload` (or an equivalent httpbin endpoint) through Tent as `multipart/form-data`.
3. Assert the response from httpbin confirms the file was received with the correct name and content.

This proves the full pipeline: Apache → PHP → `Request::body()` → `ProxyRequestHandler` → `CurlHttpExecutor\Post` → backend.

#### 3d — Integration test using the dev API photo endpoint

Once issues 192 and 193 are also implemented, add a test that:
1. Creates a person via `POST /persons`.
2. Uploads an image via `POST /persons/:id/photo` through Tent.
3. Asserts the response is HTTP 200 with updated `photo_path`.

This is the acceptance test for the complete feature described in issues 192–195.

---

## Files Changed / Created

| Action | Path |
|--------|------|
| Modify | `source/source/.htaccess` |
| Modify/Create | `source/tests/unit/lib/models/RequestTest.php` |
| Modify/Create | `source/tests/unit/lib/http/CurlHttpExecutor/PostTest.php` |
| Create | `source/tests/integration/FileUploadProxyTest.php` |

---

## Commit order

1. `.htaccess` change — `enable_post_data_reading Off` + unit tests confirming body passthrough
2. Integration test — end-to-end file upload through Tent proxy

---

## Dependency graph

```
195 (Tent core) ← must land before end-to-end tests work
192 (api_dev endpoint) ← independent, can land in parallel
193 (Tent proxy rule) ← depends on 195 (for multipart forwarding) and 192 (for backend)
194 (React UI) ← depends on 193 being live; unit-testable independently
```
