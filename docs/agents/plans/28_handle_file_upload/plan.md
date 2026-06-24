# Plan: Handle File Upload (Issue #28)

## Overview

Enable the Tent proxy to forward `multipart/form-data` file uploads to the backend without dropping the files.

## Context

When PHP receives a `multipart/form-data` POST request it consumes the input stream and stores:
- Files in `$_FILES`
- Regular text fields in `$_POST`
- `php://input` becomes **empty**

The current proxy reads `$request->body()` → `file_get_contents('php://input')` → `""` for multipart.  
The `Post` executor then calls `curl_setopt($handle, CURLOPT_POSTFIELDS, "")` → backend receives no files.

The fix is to expose `$_FILES` and `$_POST` through the request model, forward them via the curl executor using `CURLFile` objects, and strip the original `Content-Type` header so curl can generate a new multipart boundary.

## Implementation Steps

### Step 1 — `RequestInterface`: add `uploadedFiles()` and `postFields()`

File: `source/source/lib/models/RequestInterface.php`

```php
/** Returns uploaded files (mirrors $_FILES). */
public function uploadedFiles(): array;

/** Returns POST form fields (mirrors $_POST). */
public function postFields(): array;
```

---

### Step 2 — `Request`: implement new methods

File: `source/source/lib/models/Request.php`

```php
public function uploadedFiles(): array
{
    return $this->options['uploadedFiles'] ?? $_FILES;
}

public function postFields(): array
{
    return $this->options['postFields'] ?? $_POST;
}
```

Tests in `source/tests/unit/lib/models/RequestTest.php`:
- `testUploadedFilesReturnsFilesGlobal` — verifies delegation to `$_FILES` via option override
- `testPostFieldsReturnsPostGlobal` — verifies delegation to `$_POST` via option override

---

### Step 3 — `ProcessingRequest`: add `uploadedFiles()` and `postFields()`

File: `source/source/lib/models/ProcessingRequest.php`

Add to `ATTRIBUTES`:
```php
'uploadedFiles',
'postFields',
```

Add private properties and lazy-loading methods (same pattern as existing `body()`, `headers()`, etc.):

```php
private ?array $uploadedFiles = null;
private ?array $postFields = null;

public function uploadedFiles(): array
{
    if ($this->uploadedFiles === null && $this->request) {
        $this->uploadedFiles = $this->request->uploadedFiles();
    }
    return $this->uploadedFiles ?? [];
}

public function postFields(): array
{
    if ($this->postFields === null && $this->request) {
        $this->postFields = $this->request->postFields();
    }
    return $this->postFields ?? [];
}
```

Tests in a new file `source/tests/unit/lib/models/ProcessingRequest/ProcessingRequestUploadedFilesTest.php`:
- `testUploadedFilesDelegatesToRequest` — verifies delegation to underlying request
- `testUploadedFilesCachesResult` — verifies request is only called once
- `testUploadedFilesReturnsEmptyWhenNoRequest` — verifies empty array default
- `testPostFieldsDelegatesToRequest` — same pattern for `postFields()`

---

### Step 4 — `HttpClientInterface`: update signature

File: `source/source/lib/http/HttpClientInterface.php`

```php
public function request(
    string $method,
    string $url,
    array $headers,
    ?string $body = null,
    array $uploadedFiles = [],
    array $postFields = []
): array;
```

---

### Step 5 — `CurlHttpExecutor/Base`: add `$uploadedFiles` and `$postFields`

File: `source/source/lib/http/CurlHttpExecutor/Base.php`

Add properties:
```php
protected array $uploadedFiles;
protected array $postFields;
```

In constructor, read from options:
```php
$this->uploadedFiles = $options['uploadedFiles'] ?? [];
$this->postFields    = $options['postFields']    ?? [];
```

---

### Step 6 — `CurlHttpExecutor/Post`: handle file uploads

File: `source/source/lib/http/CurlHttpExecutor/Post.php`

When `$this->uploadedFiles` is non-empty, build a `CURLFile` array and pass it as the post body:

```php
protected function addExtraCurlOptions(): void
{
    curl_setopt($this->curlHandle, CURLOPT_POST, true);

    if (!empty($this->uploadedFiles)) {
        $fields = $this->postFields;
        foreach ($this->uploadedFiles as $fieldName => $file) {
            $fields[$fieldName] = new \CURLFile(
                $file['tmp_name'],
                $file['type'] ?? '',
                $file['name'] ?? ''
            );
        }
        curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $fields);
    } else {
        curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $this->body);
    }
}
```

Tests in `source/tests/unit/lib/http/CurlHttpExecutor/PostTest.php` (integration against httpbin):
- `testRequestWithFileUpload` — upload a real temp file, verify httpbin echoes back the file data in `files` key.

---

### Step 7 — `CurlHttpClient`: pass through `$uploadedFiles` and `$postFields`

File: `source/source/lib/http/CurlHttpClient.php`

Update method signature and executor construction:

```php
public function request(
    string $method,
    string $url,
    array $headers,
    ?string $body = null,
    array $uploadedFiles = [],
    array $postFields = []
): array {
    $executorClass = match (strtoupper($method)) { /* ... same as before */ };

    $executor = new $executorClass([
        'url'           => $url,
        'headers'       => $headers,
        'body'          => $body,
        'uploadedFiles' => $uploadedFiles,
        'postFields'    => $postFields,
    ]);
    return $executor->request();
}
```

Tests in `source/tests/unit/lib/http/CurlHttpClient/CurlHttpClientPostTest.php`:
- `testRequestWithFileUploadCallsPostExecutor` — verify files are forwarded (integration with httpbin).

---

### Step 8 — `ProxyRequestHandler`: forward files, strip `Content-Type`

File: `source/source/lib/request_handlers/ProxyRequestHandler.php`

When files are present, the original `Content-Type: multipart/form-data; boundary=<old>` must be stripped so curl generates a new boundary that matches the new multipart body:

```php
protected function processsRequest(RequestInterface $request): Response
{
    $url     = $this->server()->fullUrl($request->requestPath(), $request->query());
    $headers = $request->headers();
    $files   = $request->uploadedFiles();

    if (!empty($files)) {
        // Remove Content-Type so curl generates the correct multipart boundary.
        unset($headers['Content-Type'], $headers['content-type']);
    }

    $response = $this->httpClient->request(
        $request->requestMethod(),
        $url,
        $headers,
        $request->body(),
        $files,
        $request->postFields()
    );
    $response['request'] = $request;

    $result = new Response($response);

    Logger::debug(/* ... same as before ... */);

    return $result;
}
```

Tests in `source/tests/unit/lib/request_handlers/ProxyRequestHandler/ProxyRequestHandlerGeneralTest.php`:
- `testProcessRequestWithFileUploadForwardsFiles` — inject a mock `HttpClientInterface`, verify it is called with the correct `$uploadedFiles` and `$postFields`.
- `testProcessRequestWithFileUploadStripsContentTypeHeader` — verify `Content-Type` is absent from the headers passed to the HTTP client.

---

## Files Changed

| Action | File |
|--------|------|
| Modify | `source/source/lib/models/RequestInterface.php` |
| Modify | `source/source/lib/models/Request.php` |
| Modify | `source/source/lib/models/ProcessingRequest.php` |
| Modify | `source/source/lib/http/HttpClientInterface.php` |
| Modify | `source/source/lib/http/CurlHttpExecutor/Base.php` |
| Modify | `source/source/lib/http/CurlHttpExecutor/Post.php` |
| Modify | `source/source/lib/http/CurlHttpClient.php` |
| Modify | `source/source/lib/request_handlers/ProxyRequestHandler.php` |
| Modify | `source/tests/unit/lib/models/RequestTest.php` |
| Create | `source/tests/unit/lib/models/ProcessingRequest/ProcessingRequestUploadedFilesTest.php` |
| Modify | `source/tests/unit/lib/http/CurlHttpExecutor/PostTest.php` |
| Modify | `source/tests/unit/lib/http/CurlHttpClient/CurlHttpClientPostTest.php` |
| Modify | `source/tests/unit/lib/request_handlers/ProxyRequestHandler/ProxyRequestHandlerGeneralTest.php` |

## CI Checks

```bash
docker compose run --rm tent_tests composer tests       # All PHPUnit tests
docker compose run --rm tent_tests composer lint        # PSR-12 style check
```

## Commit Order

1. `RequestInterface` + `Request` + `ProcessingRequest` — model layer (with tests)
2. `HttpClientInterface` + `CurlHttpExecutor/Base` + `CurlHttpExecutor/Post` + `CurlHttpClient` — HTTP layer (with tests)
3. `ProxyRequestHandler` — proxy layer (with tests)
