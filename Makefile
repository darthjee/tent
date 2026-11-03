.PHONY: build-base build dev tests \
        ci-push-digest-tent ci-check-digest-tent ci-merge-tent ci-verify-tent \
        ci-push-digest-tent-test ci-check-digest-tent-test ci-merge-tent-test \
        ci-verify-tent-test \
        ci-push-digest-base ci-check-digest-base ci-merge-base ci-verify-base

PROJECT?=tent
DOCKER_ID_USER?=darthjee
BASE_VERSION?=0.1.0
VERSION?=1.1.0
ARCH?=amd64
MOD?=dev_
BASE_IMAGE?=$(DOCKER_ID_USER)/$(MOD)$(PROJECT)-base
IMAGE?=$(DOCKER_ID_USER)/$(MOD)$(PROJECT)
DOCKER_FILE_BASE?=dockerfiles/$(MOD)$(PROJECT)-base/Dockerfile
DOCKER_FILE?=dockerfiles/$(MOD)$(PROJECT)/Dockerfile

all:
	@echo "Usage:"
	@echo "  make build\n    Build docker image for $(PROJECT)"
	@echo "  make build-base\n    Build base docker image for $(PROJECT) (local, native arch only)"
	@echo "  The base image is released as a multi-arch manifest by CI only."

# Local native single-arch build, for debugging and for building on a new
# BASE_VERSION before CI has published it. Never push this build: it would
# overwrite the multi-arch manifest released by CI.
build-base:
	make DOCKER_FILE=$(DOCKER_FILE_BASE) IMAGE=$(BASE_IMAGE) build

build:
	if (docker images | grep $(IMAGE):latest); then \
		docker tag $(IMAGE):latest $(IMAGE):cached; \
		docker rmi $(IMAGE):latest; \
	fi
	docker build -f $(DOCKER_FILE) source -t $(IMAGE) -t $(IMAGE) -t $(IMAGE):$(BASE_VERSION)
	if (docker images | grep $(IMAGE) | grep cached); then \
	  docker rmi $(IMAGE):cached; \
	fi

tests:
	docker compose run $(PROJECT)_tests /bin/bash

dev:
	docker compose run $(PROJECT)_tests /bin/bash

dev-api:
	docker compose run api_dev /bin/bash

dev-up:
	docker compose up $(PROJECT)_app

ci-push-digest-tent:
	./scripts/build_docker_image.sh push-digest tent $(ARCH) $(VERSION)

ci-check-digest-tent:
	./scripts/build_docker_image.sh check-digest tent $(ARCH) $(VERSION)

ci-merge-tent:
	./scripts/build_docker_image.sh merge tent $(VERSION)

ci-verify-tent:
	./scripts/build_docker_image.sh verify tent $(ARCH) $(VERSION)

ci-push-digest-tent-test:
	./scripts/build_docker_image.sh push-digest tent-test $(ARCH) $(VERSION)

ci-check-digest-tent-test:
	./scripts/build_docker_image.sh check-digest tent-test $(ARCH) $(VERSION)

ci-merge-tent-test:
	./scripts/build_docker_image.sh merge tent-test $(VERSION)

ci-verify-tent-test:
	./scripts/build_docker_image.sh verify tent-test $(ARCH) $(VERSION)

ci-push-digest-base:
	./scripts/build_docker_image.sh push-digest dev_tent-base $(ARCH) $(BASE_VERSION)

ci-check-digest-base:
	./scripts/build_docker_image.sh check-digest dev_tent-base $(ARCH) $(BASE_VERSION)

ci-merge-base:
	./scripts/build_docker_image.sh merge dev_tent-base $(BASE_VERSION)

ci-verify-base:
	./scripts/build_docker_image.sh verify dev_tent-base $(ARCH) $(BASE_VERSION)
