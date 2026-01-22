.PHONY: build-base push-base build push dev tests

PROJECT?=tent
BASE_VERSION?=0.0.1
VERSION?=0.0.2
MOD?=dev_
BASE_IMAGE?=$(DOCKER_ID_USER)/$(MOD)$(PROJECT)-base
IMAGE?=$(DOCKER_ID_USER)/$(MOD)$(PROJECT)
DOCKER_FILE_BASE?=dockerfiles/Dockerfile.$(MOD)$(PROJECT)-base
DOCKER_FILE?=dockerfiles/Dockerfile.$(MOD)$(PROJECT)

all:
	@echo "Usage:"
	@echo "  make build\n    Build docker image for $(PROJECT)"
	@echo "  make build-base\n    Build base docker image for $(PROJECT)"
	@echo "  make push-base\n    Pushes base docker image for $(PROJECT) to dockerhub"

build-base:
	make DOCKER_FILE=$(DOCKER_FILE_BASE) IMAGE=$(BASE_IMAGE) build

push-base:
	make DOCKER_FILE=$(DOCKER_FILE_BASE) IMAGE=$(BASE_IMAGE) push

build:
	if (docker images | grep $(IMAGE):latest); then \
		docker tag $(IMAGE):latest $(IMAGE):cached; \
		docker rmi $(IMAGE):latest; \
	fi
	docker build --platform linux/amd64 -f $(DOCKER_FILE) . -t $(IMAGE) -t $(IMAGE) -t $(IMAGE):$(BASE_VERSION)
	if (docker images | grep $(IMAGE) | grep cached); then \
	  docker rmi $(IMAGE):cached; \
	fi

ensure-image:
	if !(docker images | grep $(IMAGE):latest); then \
		make build; \
	fi

push: ensure-image
	docker push $(IMAGE)
	docker push $(IMAGE):$(BASE_VERSION)
	docker push $(IMAGE):latest

tests:
	docker-compose run $(PROJECT)_tests /bin/bash

dev:
	docker-compose run $(PROJECT)_tests /bin/bash

dev-api:
	docker-compose run api_dev /bin/bash

dev-up:
	docker-compose up $(PROJECT)_app
