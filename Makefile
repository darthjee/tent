.PHONY: build-base push-base build push dev tests

PROJECT?=tent
BASE_VERSION?=0.0.1
VERSION?=0.0.1
BASE_IMAGE?=$(DOCKER_ID_USER)/$(PROJECT)-base
IMAGE=$(DOCKER_ID_USER)/$(PROJECT)
DOCKER_FILE_BASE=dockerfiles/Dockerfile.$(PROJECT)-base

all:
	@echo "Usage:"
	@echo "  make build\n    Build docker image for $(PROJECT)"
	@echo "  make build-base\n    Build base docker image for $(PROJECT)"
	@echo "  make push-base\n    Pushes base docker image for $(PROJECT) to dockerhub"

build-base:
	if (docker images | grep $(BASE_IMAGE):latest); then \
		docker tag $(BASE_IMAGE):latest $(BASE_IMAGE):cached; \
		docker rmi $(BASE_IMAGE):latest; \
	fi
	docker build -f $(DOCKER_FILE_BASE) . -t $(BASE_IMAGE):latest -t $(BASE_IMAGE):$(BASE_VERSION)
	if (docker images | grep $(BASE_IMAGE):cached); then \
	  docker rmi $(BASE_IMAGE):cached; \
	fi

push-base:
	make build-base
	docker push $(BASE_IMAGE)
	docker push $(BASE_IMAGE):$(BASE_VERSION)

build:
	if (docker images | grep $(IMAGE):latest); then \
		docker tag $(IMAGE):latest $(IMAGE):cached; \
		docker rmi $(IMAGE):latest; \
	fi
	docker build -f dockerfiles/Dockerfile.$(PROJECT) . -t $(IMAGE) -t $(IMAGE) -t $(IMAGE):$(BASE_VERSION)
	if (docker images | grep $(IMAGE) | grep cached); then \
	  docker rmi $(IMAGE):cached; \
	fi

push:
	make build
	docker push $(IMAGE)
	docker push $(IMAGE):$(BASE_VERSION)

tests:
	docker-compose run $(PROJECT)_tests /bin/bash

dev:
	docker-compose run $(PROJECT)_tests /bin/bash

dev-up:
	docker-compose up $(PROJECT)_app
