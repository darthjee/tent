# Document the option
Document `conditional` for the `static` handler: what it does, that it is off by default, and that it applies to `GET` only. Include an example that pairs it with `Cache-Control: no-cache` via `SetHeadersMiddleware`, so clients revalidate on every view and get `304` when the file is unchanged.

## Files to Change
- `docs/request-handlers.md`: add a `conditional` row to the StaticFileHandler options table and a short "Conditional requests" subsection with the example.
- `docs/guides/tent/request-handlers.md`: add `conditional` to the `static` options table.
