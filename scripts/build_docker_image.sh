#!/usr/bin/env bash
set -euo pipefail

show_help() {
  cat <<'EOF'
Usage: scripts/build_docker_image.sh <build|ensure|release|check|help> <image> <arch> <version>

Images:        tent, tent-test, dev_tent, dev_tent-base
Architectures: amd64, arm64
EOF
}

image_config() {
  local image="$1"
  EXTRA_BUILD_CONTEXT=""
  BASE_SUFFIX_ARG=""

  case "$image" in
    tent)
      DOCKERFILE="dockerfiles/tent/Dockerfile"
      CONTEXT="source"
      IMAGE_NAME="darthjee/tent"
      ;;
    tent-test)
      DOCKERFILE="dockerfiles/tent-test/Dockerfile"
      CONTEXT="source"
      IMAGE_NAME="darthjee/tent-test"
      # phpunit.xml/bootstrap.php baked into the image live under
      # dockerfiles/tent-test/, outside the "source" build context, so they
      # are wired in as an extra named build context (see build_image()).
      EXTRA_BUILD_CONTEXT="tent-test-assets=dockerfiles/tent-test"
      # Built FROM darthjee/dev_tent-base; the base tag must carry the same
      # arch suffix (see build_image()).
      BASE_SUFFIX_ARG=true
      ;;
    dev_tent)
      DOCKERFILE="dockerfiles/dev_tent/Dockerfile"
      CONTEXT="dev/api"
      IMAGE_NAME="darthjee/dev_tent"
      BASE_SUFFIX_ARG=true
      ;;
    dev_tent-base)
      DOCKERFILE="dockerfiles/dev_tent-base/Dockerfile"
      CONTEXT="dev/api"
      IMAGE_NAME="darthjee/dev_tent-base"
      ;;
    *)
      echo "Invalid image: $image" >&2
      show_help >&2
      exit 1
      ;;
  esac
}

arch_config() {
  local arch="$1"
  case "$arch" in
    amd64)
      PLATFORM="linux/amd64"
      ARCH_SUFFIX=""
      EXPECTED_MACHINE="x86_64"
      ;;
    arm64)
      PLATFORM="linux/arm64"
      ARCH_SUFFIX="-arm64"
      EXPECTED_MACHINE="aarch64"
      ;;
    *)
      echo "Invalid arch: $arch" >&2
      show_help >&2
      exit 1
      ;;
  esac
}

build_image() {
  local image="$1"
  local arch="$2"
  local version="$3"

  image_config "$image"
  arch_config "$arch"

  local cmd=(docker build --platform "$PLATFORM" -f "$DOCKERFILE")

  if [[ -n "$EXTRA_BUILD_CONTEXT" ]]; then
    cmd+=(--build-context "$EXTRA_BUILD_CONTEXT")
  fi

  # Images built FROM darthjee/dev_tent-base must use the base matching the
  # target arch ("" for amd64, "-arm64" for arm64).
  if [[ -n "$BASE_SUFFIX_ARG" ]]; then
    cmd+=(--build-arg "BASE_SUFFIX=${ARCH_SUFFIX}")
  fi

  cmd+=(
    "$CONTEXT"
    -t "${IMAGE_NAME}:${version}${ARCH_SUFFIX}"
    -t "${IMAGE_NAME}:latest${ARCH_SUFFIX}"
  )

  "${cmd[@]}"
}

ensure_image() {
  local image="$1"
  local arch="$2"
  local version="$3"

  image_config "$image"
  arch_config "$arch"

  if docker pull "${IMAGE_NAME}:${version}${ARCH_SUFFIX}"; then
    docker tag "${IMAGE_NAME}:${version}${ARCH_SUFFIX}" "${IMAGE_NAME}:latest${ARCH_SUFFIX}"
  else
    build_image "$image" "$arch" "$version"
  fi
}

release_image() {
  local image="$1"
  local arch="$2"
  local version="$3"

  image_config "$image"
  arch_config "$arch"

  docker push "${IMAGE_NAME}:${version}${ARCH_SUFFIX}"
  docker push "${IMAGE_NAME}:latest${ARCH_SUFFIX}"
}

# PHP runtime contract every Tent image must satisfy (GD with JPEG/PNG, exif,
# upload limits) and the expected machine for the target arch, which catches
# images accidentally built on a base of the wrong architecture.
read -r -d '' CHECK_PHP <<'PHP' || true
$failures = [];

foreach (['gd', 'exif'] as $ext) {
    if (!extension_loaded($ext)) {
        $failures[] = "extension not loaded: $ext";
    }
}

$gd = function_exists('gd_info') ? gd_info() : [];
foreach (['JPEG Support', 'PNG Support'] as $feature) {
    if (empty($gd[$feature])) {
        $failures[] = "gd missing: $feature";
    }
}

$functions = [
    'imagecreatefromjpeg', 'imagecreatefrompng', 'imagescale', 'imagerotate',
    'imagejpeg', 'imagepng', 'exif_read_data',
];
foreach ($functions as $function) {
    if (!function_exists($function)) {
        $failures[] = "function missing: $function";
    }
}

$ini = ['upload_max_filesize' => '20M', 'post_max_size' => '25M'];
foreach ($ini as $key => $expected) {
    $actual = ini_get($key);
    if ($actual !== $expected) {
        $failures[] = "ini $key: expected $expected, got " . var_export($actual, true);
    }
}

$expectedMachine = getenv('EXPECTED_MACHINE');
$machine = php_uname('m');
if ($machine !== $expectedMachine) {
    $failures[] = "machine: expected $expectedMachine, got $machine";
}

if ($failures) {
    fwrite(STDERR, "Image check FAILED:\n  - " . implode("\n  - ", $failures) . "\n");
    exit(1);
}

echo "Image check OK ($machine)\n";
PHP

check_image() {
  local image="$1"
  local arch="$2"
  local version="$3"

  image_config "$image"
  arch_config "$arch"

  # --entrypoint php: tent's CMD is apache and tent-test's is phpunit.
  docker run --rm --platform "$PLATFORM" \
    -e "EXPECTED_MACHINE=${EXPECTED_MACHINE}" \
    --entrypoint php \
    "${IMAGE_NAME}:${version}${ARCH_SUFFIX}" \
    -r "$CHECK_PHP"
}

COMMAND="${1:-help}"
IMAGE="${2:-}"
ARCH="${3:-}"
VERSION="${4:-}"

case "$COMMAND" in
  build)
    build_image "$IMAGE" "$ARCH" "$VERSION"
    ;;
  ensure)
    ensure_image "$IMAGE" "$ARCH" "$VERSION"
    ;;
  release)
    release_image "$IMAGE" "$ARCH" "$VERSION"
    ;;
  check)
    check_image "$IMAGE" "$ARCH" "$VERSION"
    ;;
  help)
    show_help
    ;;
  *)
    echo "Invalid command: $COMMAND" >&2
    show_help >&2
    exit 1
    ;;
esac
