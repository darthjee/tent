#!/usr/bin/env bash
set -euo pipefail

show_help() {
  cat <<'EOF'
Usage: scripts/build_docker_image.sh <build|ensure|release|help> <image> <arch> <version>

Images:        tent, dev_tent, dev_tent-base
Architectures: amd64, arm64
EOF
}

image_config() {
  local image="$1"
  case "$image" in
    tent)
      DOCKERFILE="dockerfiles/tent/Dockerfile"
      CONTEXT="source"
      IMAGE_NAME="darthjee/tent"
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
      ;;
    arm64)
      PLATFORM="linux/arm64/v8"
      ARCH_SUFFIX="-arm64"
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

  set -x
  docker build \
    --platform "$PLATFORM" \
    -f "$DOCKERFILE" \
    "$CONTEXT" \
    -t "${IMAGE_NAME}:${version}${ARCH_SUFFIX}" \
    -t "${IMAGE_NAME}:latest${ARCH_SUFFIX}"
  set +x
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
  help)
    show_help
    ;;
  *)
    echo "Invalid command: $COMMAND" >&2
    show_help >&2
    exit 1
    ;;
esac
