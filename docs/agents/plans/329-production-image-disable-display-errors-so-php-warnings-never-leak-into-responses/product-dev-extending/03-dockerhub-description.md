# Docker Hub description

`DOCKERHUB_DESCRIPTION.md` is pushed to Docker Hub by the `update-description` CI job, so keep it short and consumer-facing. Add a short subsection, under `## Configuration` or next to `### Environment Variables`. It should say that PHP diagnostics are written to the container stderr and never to responses, show how to override them (a `zz-*.ini` in `/usr/local/etc/php/conf.d/`, and do not replace `tent.ini`), and recommend log rotation and restricted log access. Link to the reference guide section from step 02 under `## More Information` for details. If `## Example Compose Service` shows a service, consider adding a `logging:` `max-size`/`max-file` example there. Keep it to a few lines.

## Files to Change
- `DOCKERHUB_DESCRIPTION.md` — short PHP error output and override note, link to the reference guide.
