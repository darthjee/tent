# Use a base image from Dockerfile.tent-base
ARG BASE_SUFFIX=""
FROM darthjee/dev_tent-base:0.0.3${BASE_SUFFIX}
    
# Copy the project code to the default Apache directory
COPY --chown=app:app ./composer.* /home/app/app/

RUN mkdir -p /home/app/app/source/lib/; \
    composer install; \
    composer dump-autoload
