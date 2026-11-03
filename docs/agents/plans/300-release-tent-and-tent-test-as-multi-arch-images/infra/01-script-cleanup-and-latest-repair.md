# Script cleanup and stale-latest repair

Remove the per-arch tag code paths from `scripts/build_docker_image.sh`. After #300, nothing calls `build`, `ensure` or `release`.

`merge` gets one fix: a re-run must repair a `latest` that failed to update.

## Changes

1. **Remove commands.** Delete the `build_image`, `ensure_image` and `release_image` functions and their `build` / `ensure` / `release` cases in the command dispatch.
2. **Remove `ARCH_SUFFIX`.** In `arch_config`, drop the `ARCH_SUFFIX` variable for both archs. `PLATFORM` and `EXPECTED_MACHINE` stay.
3. **`check_image` with a plain version.** When the argument contains no `@` or `:`, resolve it to `${IMAGE_NAME}:${version}`, the multi-arch tag. `docker run --platform "$PLATFORM"` already selects the arch variant. Full refs containing `@` or `:` are still used as is. Update the comment above the ref resolution.
4. **Help text.** Remove the "Per-arch tag commands (tent, tent-test until #300)" block. Document `check <image> <arch> <version|ref>` under the multi-arch commands, where a plain version means the published multi-arch tag, checked for `<arch>`. Keep the image and arch lists.
5. **Stale `latest` repair in `merge_image`.** In the `complete)` branch, instead of returning right away:
   - Read both digests:
     ```bash
     v_digest="$(docker buildx imagetools inspect "${IMAGE_NAME}:${version}" --format '{{json .Manifest}}' | jq -r .digest)"
     latest_digest="$(docker buildx imagetools inspect "${IMAGE_NAME}:latest" --format '{{json .Manifest}}' 2>/dev/null | jq -r .digest || true)"
     ```
     Factor this into a small helper, e.g. `manifest_digest <ref>`, that prints an empty string when the ref is missing.
   - If they are equal: print `"${IMAGE_NAME}:${version} is already multi-arch and latest is up to date; nothing to merge"` and return 0.
   - Otherwise, including when `latest` is missing: print that `latest` is being re-pointed, then run:
     ```bash
     docker buildx imagetools create -t "${IMAGE_NAME}:latest" "${IMAGE_NAME}:${version}"
     ```
   - This applies to every image, `dev_tent-base` included.
   - The `incomplete` and `missing` branches are unchanged.

Every other command keeps its current behavior: `push-digest`, `check-digest`, `verify`, `tag_status`, `ensure_builder` and `digest_file`.

## Files to Change

- `scripts/build_docker_image.sh`: remove `build` / `ensure` / `release` and `ARCH_SUFFIX`, resolve a plain version in `check` to the multi-arch tag, update the help text, and add `manifest_digest` plus the `latest` repair in `merge_image`.
