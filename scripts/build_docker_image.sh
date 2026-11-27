#!/usr/bin/env bash
set -euo pipefail

show_help() {
  cat <<'EOF'
Usage: scripts/build_docker_image.sh <command> <args...>

Multi-arch commands:
  push-digest  <image> <arch> <version>  Build and push untagged by digest, writing
                                         digests/<image>-<arch>. Skips (writing a
                                         .skip marker) when <version> is already
                                         multi-arch; fails when it is single-arch.
  check        <image> <arch> <version|ref>
                                         Check <arch>'s variant of the published
                                         multi-arch tag <image>:<version>, or a full
                                         ref (anything with '@' or ':', e.g.
                                         darthjee/dev_tent-base@sha256:...) as is
  check-digest <image> <arch>            Check the digest in digests/<image>-<arch>
  merge        <image> <version>         imagetools create <version> and latest from
                                         both per-arch digests. When <version> is
                                         already complete, only re-points a stale
                                         or missing latest to it.
  verify       <image> <arch> <version>  Build with --load (no push) and check it

  help                                   Show this help

Images:        tent, tent-test, dev_tent, dev_tent-base
Architectures: amd64, arm64
EOF
}

image_config() {
  local image="$1"
  EXTRA_BUILD_CONTEXT=""
  # Per-image check extras (empty unless set in the image's branch below):
  # extensions/composer for dev_tent-base, deploy tooling and Apache access
  # log for tent.
  CHECK_EXTENSIONS=""
  CHECK_COMPOSER=""
  CHECK_DEPLOY=""
  CHECK_ACCESS_LOG=""

  case "$image" in
    tent)
      DOCKERFILE="dockerfiles/tent/Dockerfile"
      CONTEXT="source"
      IMAGE_NAME="darthjee/tent"
      # The Apache access-log assets live under dockerfiles/tent/, outside
      # the "source" build context, so they are wired in as an extra named
      # build context (see buildx_base_cmd()).
      EXTRA_BUILD_CONTEXT="tent-assets=dockerfiles/tent"
      CHECK_DEPLOY=true
      CHECK_ACCESS_LOG=true
      ;;
    tent-test)
      DOCKERFILE="dockerfiles/tent-test/Dockerfile"
      CONTEXT="source"
      IMAGE_NAME="darthjee/tent-test"
      # phpunit.xml/bootstrap.php baked into the image live under
      # dockerfiles/tent-test/, outside the "source" build context, so they
      # are wired in as an extra named build context (see buildx_base_cmd()).
      EXTRA_BUILD_CONTEXT="tent-test-assets=dockerfiles/tent-test"
      ;;
    dev_tent)
      DOCKERFILE="dockerfiles/dev_tent/Dockerfile"
      CONTEXT="dev/api"
      IMAGE_NAME="darthjee/dev_tent"
      ;;
    dev_tent-base)
      DOCKERFILE="dockerfiles/dev_tent-base/Dockerfile"
      CONTEXT="dev/api"
      IMAGE_NAME="darthjee/dev_tent-base"
      CHECK_EXTENSIONS="pcov"
      CHECK_COMPOSER=true
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
      EXPECTED_MACHINE="x86_64"
      ;;
    arm64)
      PLATFORM="linux/arm64"
      EXPECTED_MACHINE="aarch64"
      ;;
    *)
      echo "Invalid arch: $arch" >&2
      show_help >&2
      exit 1
      ;;
  esac
}

# PHP runtime contract every Tent image must satisfy (GD with JPEG/PNG, exif,
# upload limits) and the expected machine for the target arch, which catches
# images accidentally built on a base of the wrong architecture.
# EXPECTED_EXTENSIONS adds per-image extensions (comma separated, e.g. pcov
# for dev_tent-base); empty for every other image.
read -r -d '' CHECK_PHP <<'PHP' || true
$failures = [];

$extensions = array_merge(
    ['gd', 'exif'],
    array_filter(array_map('trim', explode(',', getenv('EXPECTED_EXTENSIONS') ?: '')))
);
foreach ($extensions as $ext) {
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

# Deploy tooling contract for the tent image (darthjee/scripts 0.12.0):
# ssh/rsync/bash on PATH, deploy_frontend.sh prints the 0.12.0 usage (stderr,
# exit 1) with no action, and generate_key_file writes $HOME/ssh_key (mode
# 600) as the image's default user. Runs without set -e so every failure is
# collected and reported together.
read -r -d '' CHECK_DEPLOY_SH <<'SH' || true
failures=()

for cmd in ssh rsync bash; do
  command -v "$cmd" >/dev/null 2>&1 || failures+=("command not on PATH: $cmd")
done

# No action: 0.12.0 prints usage on stderr and exits 1 (expected, not a failure).
output="$(deploy_frontend.sh 2>&1)"
status=$?
[ "$status" -eq 1 ] || failures+=("deploy_frontend.sh without action: expected exit 1, got $status")
expected="Actions: generate_key_file build generate_folder copy_files upload link run release cleanup"
grep -qxF "$expected" <<<"$output" || failures+=("deploy_frontend.sh usage does not list: $expected")

# Smoke test as the image's default user: the key lands in a writable $HOME with mode 600.
if SSH_PRIVATE_KEY=dummy deploy_frontend.sh generate_key_file; then
  mode="$(stat -c %a "$HOME/ssh_key" 2>/dev/null)"
  [ "$mode" = "600" ] || failures+=("\$HOME/ssh_key: expected mode 600, got ${mode:-missing}")
else
  failures+=("deploy_frontend.sh generate_key_file failed (HOME=$HOME, user=$(id -un))")
fi

if [ "${#failures[@]}" -gt 0 ]; then
  printf 'Deploy check FAILED:\n' >&2
  printf '  - %s\n' "${failures[@]}" >&2
  exit 1
fi

echo "Deploy check OK ($(id -un))"
SH

# Access-log contract for the tent image: TENT_ACCESS_LOG_FORMAT selects the
# preset (safe, the default, logs neither query strings nor Referer) and a
# /etc/apache2/tent.d drop-in always wins. Starts Apache as the image's default
# user, sends one probe request and asserts on its access line according to
# $EXPECT (safe, combined or dropin). Runs without set -e so every failure is
# collected and reported together.
read -r -d '' CHECK_ACCESS_LOG_SH <<'SH' || true
failures=()
log=/tmp/out
line=""
ready=""

# In foreground mode Apache signals its whole process group on shutdown, which
# includes this script.
trap ':' TERM

apache2-foreground >>"$log" 2>&1 &
apache_pid=$!

# Poll until Apache answers (any status), giving up early if it exits.
for _ in $(seq 1 150); do
  if curl -s -o /dev/null --max-time 10 http://localhost/; then
    ready=true
    break
  fi
  kill -0 "$apache_pid" 2>/dev/null || break
  sleep 0.2
done

if [ -n "$ready" ]; then
  curl -s -o /dev/null --max-time 10 -H 'Referer: http://x/?ref=LEAK' \
    'http://localhost/probe?token=SMOKE' || failures+=("probe request failed")

  # The access line is written after the response is sent, so poll for it.
  # Lines starting with "[" belong to the error log, which shares the output.
  for _ in $(seq 1 50); do
    line="$(grep -F '/probe' "$log" | grep -v '^\[')"
    [ -n "$line" ] && break
    sleep 0.2
  done
  [ -n "$line" ] || failures+=("no access line for /probe")
else
  failures+=("Apache did not become ready (user=$(id -un))")
fi

if [ -n "$line" ]; then
  case "$EXPECT" in
    safe)
      # status, %O, %D and then the quoted user agent (no Referer in between).
      pattern='"GET /probe HTTP/1\.1" [0-9]+ [0-9]+ [0-9]+ "curl/'
      [[ "$line" =~ $pattern ]] || failures+=('expected "GET /probe HTTP/1.1" <status> <bytes> <duration> "<user agent>"')
      [[ "$line" != *SMOKE* ]] || failures+=("query string was logged (SMOKE)")
      [[ "$line" != *LEAK* ]] || failures+=("Referer was logged (LEAK)")
      ;;
    combined)
      [[ "$line" == *'?token=SMOKE'* ]] || failures+=("expected the query string: ?token=SMOKE")
      [[ "$line" == *'http://x/?ref=LEAK'* ]] || failures+=("expected the Referer: http://x/?ref=LEAK")
      ;;
    dropin)
      [[ "$line" == *'DROPIN GET /probe '* ]] || failures+=("expected the tent.d drop-in format: DROPIN GET /probe <status>")
      [[ "$line" != *SMOKE* ]] || failures+=("query string was logged (SMOKE)")
      ;;
    *)
      failures+=("unknown EXPECT: ${EXPECT:-unset}")
      ;;
  esac
fi

kill "$apache_pid" 2>/dev/null
wait "$apache_pid" 2>/dev/null

case_name="EXPECT=${EXPECT:-unset}, TENT_ACCESS_LOG_FORMAT=${TENT_ACCESS_LOG_FORMAT-<unset>}"

if [ "${#failures[@]}" -gt 0 ]; then
  printf 'Access-log check FAILED: (%s)\n' "$case_name" >&2
  printf '  - %s\n' "${failures[@]}" >&2
  printf 'Apache output:\n' >&2
  cat "$log" >&2
  exit 1
fi

echo "Access-log check OK ($case_name, $(id -un))"
SH

# Runs CHECK_ACCESS_LOG_SH in a fresh container of <ref>; the remaining
# arguments are extra "docker run" options. No --user: Apache runs as the
# image's default user (app).
run_access_log_case() {
  local ref="$1"
  shift

  docker run --rm --platform "$PLATFORM" "$@" \
    --entrypoint bash \
    "$ref" \
    -c "$CHECK_ACCESS_LOG_SH"
}

# Runs every access-log case against <ref>. The body is a subshell, so the EXIT
# trap removing the drop-in temp dir stays local to this function.
check_access_log() (
  ref="$1"
  dropin_dir="$(mktemp -d)"
  trap 'rm -rf "$dropin_dir"' EXIT

  mkdir "${dropin_dir}/tent.d"
  echo 'LogFormat "DROPIN %m %U %>s" combined' > "${dropin_dir}/tent.d/zz-test.conf"
  # Readable by the image's user (uid 1000), whatever the host umask is.
  chmod 755 "$dropin_dir" "${dropin_dir}/tent.d"
  chmod 644 "${dropin_dir}/tent.d/zz-test.conf"

  run_access_log_case "$ref" -e EXPECT=safe
  run_access_log_case "$ref" -e EXPECT=safe -e TENT_ACCESS_LOG_FORMAT=
  run_access_log_case "$ref" -e EXPECT=combined -e TENT_ACCESS_LOG_FORMAT=combined
  run_access_log_case "$ref" -e EXPECT=dropin -e TENT_ACCESS_LOG_FORMAT=combined \
    -v "${dropin_dir}/tent.d:/etc/apache2/tent.d:ro"
)

check_image() {
  local image="$1"
  local arch="$2"
  local version_or_ref="$3"

  image_config "$image"
  arch_config "$arch"

  # A full ref containing '@' or ':' (e.g. darthjee/dev_tent-base@sha256:...,
  # or the local tag built by verify) is used as is; a plain version means the
  # published multi-arch tag, whose <arch> variant is selected by --platform.
  local ref
  if [[ "$version_or_ref" == *@* || "$version_or_ref" == *:* ]]; then
    ref="$version_or_ref"
  else
    ref="${IMAGE_NAME}:${version_or_ref}"
  fi

  echo "Checking ${ref} (${PLATFORM})"

  # --entrypoint php: tent's CMD is apache and tent-test's is phpunit.
  docker run --rm --platform "$PLATFORM" \
    -e "EXPECTED_MACHINE=${EXPECTED_MACHINE}" \
    -e "EXPECTED_EXTENSIONS=${CHECK_EXTENSIONS}" \
    --entrypoint php \
    "$ref" \
    -r "$CHECK_PHP"

  if [[ -n "$CHECK_COMPOSER" ]]; then
    docker run --rm --platform "$PLATFORM" \
      --entrypoint composer \
      "$ref" \
      --version
  fi

  # No --user: the deploy check runs as the image's default user (app);
  # --rm makes sure the dummy key never persists.
  if [[ -n "$CHECK_DEPLOY" ]]; then
    docker run --rm --platform "$PLATFORM" \
      --entrypoint bash \
      "$ref" \
      -c "$CHECK_DEPLOY_SH"
  fi

  if [[ -n "$CHECK_ACCESS_LOG" ]]; then
    check_access_log "$ref"
  fi
}

# --- Multi-arch helpers ------------------------------------------------------

# Prints the publication status of <image_name>:<version>:
#   complete   - manifest list with both linux/amd64 and linux/arm64
#   incomplete - exists, but not multi-arch (e.g. a legacy per-arch tag)
#   missing    - tag not found
# Any other inspect failure (network, auth) exits 1.
tag_status() {
  local image_name="$1"
  local version="$2"
  local raw

  if ! raw="$(docker buildx imagetools inspect --raw "${image_name}:${version}" 2>&1)"; then
    if grep -qi "not found" <<<"$raw"; then
      echo "missing"
      return 0
    fi
    echo "Failed to inspect ${image_name}:${version}:" >&2
    echo "$raw" >&2
    exit 1
  fi

  # Attestation entries (unknown/unknown) are ignored by only looking for the
  # two platforms we publish.
  if jq -e '
      (.manifests // [])
      | map(select(.platform != null) | "\(.platform.os)/\(.platform.architecture)")
      | (index("linux/amd64") != null) and (index("linux/arm64") != null)
    ' >/dev/null 2>&1 <<<"$raw"; then
    echo "complete"
  else
    echo "incomplete"
  fi
}

not_multi_arch_error() {
  echo "tag $1:$2 exists but is not multi-arch; bump the version" >&2
  exit 1
}

# The default "docker" buildx driver can't push by digest, so make sure the
# current builder uses the docker-container driver.
ensure_builder() {
  local driver
  driver="$(docker buildx inspect 2>/dev/null | awk -F': *' '/^Driver:/ {print $2; exit}')"

  if [[ "$driver" == "docker-container" ]]; then
    return 0
  fi

  if docker buildx inspect tent-builder >/dev/null 2>&1; then
    docker buildx use tent-builder
  else
    docker buildx create --use --name tent-builder --driver docker-container
  fi
}

# Prints the manifest (list) digest of <ref>, or an empty string when the ref
# is missing or cannot be inspected.
manifest_digest() {
  local ref="$1"
  local raw

  if ! raw="$(docker buildx imagetools inspect "$ref" --format '{{json .Manifest}}' 2>/dev/null)"; then
    echo ""
    return 0
  fi

  jq -r '.digest // empty' <<<"$raw" 2>/dev/null || echo ""
}

digest_file() {
  echo "digests/$1-$2"
}

# Sets BUILDX_CMD to the common buildx build invocation for the configured
# image/arch; callers append their output flags.
buildx_base_cmd() {
  BUILDX_CMD=(docker buildx build --platform "$PLATFORM" -f "$DOCKERFILE")

  if [[ -n "$EXTRA_BUILD_CONTEXT" ]]; then
    BUILDX_CMD+=(--build-context "$EXTRA_BUILD_CONTEXT")
  fi

  BUILDX_CMD+=("$CONTEXT")
}

push_digest() {
  local image="$1"
  local arch="$2"
  local version="$3"

  image_config "$image"
  arch_config "$arch"

  local file status
  file="$(digest_file "$image" "$arch")"
  status="$(tag_status "$IMAGE_NAME" "$version")"

  case "$status" in
    complete)
      mkdir -p digests
      touch "${file}.skip"
      echo "${IMAGE_NAME}:${version} is already multi-arch; skipping ${arch} build"
      return 0
      ;;
    incomplete)
      not_multi_arch_error "$IMAGE_NAME" "$version"
      ;;
  esac

  ensure_builder

  local tmp
  tmp="$(mktemp -d)"

  buildx_base_cmd
  BUILDX_CMD+=(
    --output "type=image,name=${IMAGE_NAME},push-by-digest=true,name-canonical=true,push=true"
    --metadata-file "${tmp}/meta.json"
  )

  "${BUILDX_CMD[@]}"

  local digest
  digest="$(jq -r '."containerimage.digest" // empty' "${tmp}/meta.json")"
  rm -rf "$tmp"

  if [[ -z "$digest" ]]; then
    echo "No containerimage.digest in buildx metadata for ${IMAGE_NAME} (${arch})" >&2
    exit 1
  fi

  mkdir -p digests
  echo "$digest" > "$file"
  echo "Pushed ${IMAGE_NAME}@${digest} (${PLATFORM}) -> ${file}"
}

check_digest() {
  local image="$1"
  local arch="$2"

  image_config "$image"

  local file
  file="$(digest_file "$image" "$arch")"

  if [[ -f "${file}.skip" ]]; then
    echo "${file}.skip found; build was skipped, nothing to check"
    return 0
  fi

  if [[ ! -s "$file" ]]; then
    echo "Missing digest file: ${file}" >&2
    exit 1
  fi

  check_image "$image" "$arch" "${IMAGE_NAME}@$(cat "$file")"
}

merge_image() {
  local image="$1"
  local version="$2"

  image_config "$image"

  local status
  status="$(tag_status "$IMAGE_NAME" "$version")"

  case "$status" in
    complete)
      # A previous merge may have published <version> but failed before
      # latest; re-point latest when it does not match <version>.
      local v_digest latest_digest
      v_digest="$(manifest_digest "${IMAGE_NAME}:${version}")"
      latest_digest="$(manifest_digest "${IMAGE_NAME}:latest")"

      if [[ -n "$v_digest" && "$v_digest" == "$latest_digest" ]]; then
        echo "${IMAGE_NAME}:${version} is already multi-arch and latest is up to date; nothing to merge"
        return 0
      fi

      echo "${IMAGE_NAME}:${version} is already multi-arch; re-pointing ${IMAGE_NAME}:latest to it"
      docker buildx imagetools create \
        -t "${IMAGE_NAME}:latest" \
        "${IMAGE_NAME}:${version}"
      return 0
      ;;
    incomplete)
      not_multi_arch_error "$IMAGE_NAME" "$version"
      ;;
  esac

  local refs=() arch file
  for arch in amd64 arm64; do
    file="$(digest_file "$image" "$arch")"
    if [[ ! -s "$file" ]]; then
      echo "Missing digest file: ${file}; refusing to create a partial manifest" >&2
      exit 1
    fi
    refs+=("${IMAGE_NAME}@$(cat "$file")")
  done

  docker buildx imagetools create \
    -t "${IMAGE_NAME}:${version}" \
    -t "${IMAGE_NAME}:latest" \
    "${refs[@]}"
}

verify_image() {
  local image="$1"
  local arch="$2"
  local version="$3"

  image_config "$image"
  arch_config "$arch"

  ensure_builder

  local tag="${IMAGE_NAME}:${version}-verify-${arch}"

  buildx_base_cmd
  BUILDX_CMD+=(--load -t "$tag")

  "${BUILDX_CMD[@]}"

  check_image "$image" "$arch" "$tag"
}

COMMAND="${1:-help}"
IMAGE="${2:-}"
ARCH="${3:-}"
VERSION="${4:-}"

case "$COMMAND" in
  check)
    check_image "$IMAGE" "$ARCH" "$VERSION"
    ;;
  push-digest)
    push_digest "$IMAGE" "$ARCH" "$VERSION"
    ;;
  check-digest)
    check_digest "$IMAGE" "$ARCH"
    ;;
  merge)
    # merge <image> <version>: the version is the third argument.
    merge_image "$IMAGE" "${3:-}"
    ;;
  verify)
    verify_image "$IMAGE" "$ARCH" "$VERSION"
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
