# Add the image check command

Add a `check` command so CI can verify each built image before it's pushed. The contract from the main plan must hold, and the image must actually be built for the target architecture (this catches the arm64-on-amd64-base bug from step 02).

`scripts/build_docker_image.sh`:

- Add `check_image <image> <arch> <version>`. It calls `image_config` + `arch_config`, then runs:
  ```bash
  docker run --rm --platform "$PLATFORM" --entrypoint php "${IMAGE_NAME}:${version}${ARCH_SUFFIX}" -r "$CHECK_PHP"
  ```
  (`--entrypoint php` because `tent-test`'s `CMD` is phpunit and `tent`'s is apache.) `CHECK_PHP` is a PHP snippet that collects failures and `exit(1)`s with a readable message if any check fails:
  - `extension_loaded('gd')` and `extension_loaded('exif')`;
  - `gd_info()['JPEG Support']` and `gd_info()['PNG Support']` are true;
  - `function_exists()` for `imagecreatefromjpeg`, `imagecreatefrompng`, `imagescale`, `imagerotate`, `imagejpeg`, `imagepng`, `exif_read_data`;
  - `ini_get('upload_max_filesize') === '20M'` and `ini_get('post_max_size') === '25M'`;
  - `php_uname('m')` equals the expected machine for the arch: `x86_64` for `amd64`, `aarch64` for `arm64`. Set this in `arch_config` as e.g. `EXPECTED_MACHINE`, and pass it in with `-e` or by interpolating it into the snippet.
- Add a `check)` branch to the command `case`, and add `check` to `show_help`'s usage line.

`Makefile`:

- Add `ci-check-tent` and `ci-check-tent-test` targets, mirroring `ci-build-*`:
  ```make
  ci-check-tent:
  	./scripts/build_docker_image.sh check tent $(ARCH) $(VERSION)

  ci-check-tent-test:
  	./scripts/build_docker_image.sh check tent-test $(ARCH) $(VERSION)
  ```
  and add them to `.PHONY`.

## Files to Change
- `scripts/build_docker_image.sh` — new `check` command (`check_image`, expected machine per arch, help text).
- `Makefile` — `ci-check-tent` / `ci-check-tent-test` targets and `.PHONY` entries.
