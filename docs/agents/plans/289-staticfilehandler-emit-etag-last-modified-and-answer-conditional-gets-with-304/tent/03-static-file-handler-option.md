# Wire the option into StaticFileHandler
Add the `conditional` option and the early-304 flow.

- Constructor: `__construct(FolderLocation $folderLocation, bool $conditional = false)`. `build()` reads `$params['conditional'] ?? false`.
- `processsRequest`: keep the existing try/catch. Inside it, when `$this->conditional` is true and `strtoupper($request->requestMethod()) === 'GET'`:
  1. Run the same validation as today before anything else, so `403` / `404` are unchanged. One option is to expose `ResponseContentReader::validate()` as public, or add a public `ensureReadable()` that calls it. Pick whichever keeps the reader's existing API intact.
  2. Build a `ConditionalRequestMatcher` with `$file->etag()` and `$file->lastModified()`. If it matches, return `new NotModifiedResponse($request, $file->validatorHeaders())` without reading the content.
  3. Otherwise get the response from `ResponseContentReader` and append `$file->validatorHeaders()` to its headers (`setHeaders(array_merge(...))`).
- When the option is off or the method is not `GET`, the code path stays exactly as today.
- Update the class docblock with an example that uses `'conditional' => true` together with a `SetHeadersMiddleware` setting `Cache-Control: no-cache`.

## Files to Change
- `source/source/lib/request_handlers/StaticFileHandler.php`: the option, flow, and docblock.
- `source/source/lib/service/ResponseContentReader.php`: only if validation needs to be callable on its own (keep it backwards compatible).
- `source/tests/unit/lib/request_handlers/StaticFileHandler/StaticFileHandlerBuildTest.php`: `conditional` is parsed and defaults to off.
- `source/tests/unit/lib/request_handlers/StaticFileHandler/StaticFileHandlerGeneralTest.php`: add tests (or a new `StaticFileHandlerConditionalTest.php` alongside) for: option off gives no validators; option on gives a 200 with ETag/Last-Modified; a matching `If-None-Match` gives a 304 with an empty body, validators, and no Content-Type/Length; a matching `If-Modified-Since` gives a 304; a stale ETag gives a 200; a missing file gives a 404 and a traversal path gives a 403 with the option on; `HEAD`/`POST` with the option on are unchanged; and a rule-level `SetHeadersMiddleware` header is present on the 304 (via `handleRequest`).
