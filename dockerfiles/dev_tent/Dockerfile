# Use a base image from Dockerfile.tent-base
FROM darthjee/dev_tent-base:0.0.1
    
# Copy the project code to the default Apache directory
COPY --chown=app:app ./source/composer.* /home/app/app/

RUN mkdir -p /home/app/app/source/lib/; \
    composer install; \
    composer dump-autoload
