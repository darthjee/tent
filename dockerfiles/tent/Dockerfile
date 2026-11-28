FROM darthjee/scripts:0.12.0 AS scripts
FROM php:8.4-apache AS base

# Create app user and directory structure
RUN useradd -u 1000 app; \
    mkdir -p /home/app/; \
    chown app:app -R /var/www/html /home/app

COPY --from=scripts /home/scripts/sbin/deploy_frontend.sh /usr/sbin/

# Install necessary dependencies for Composer and PHP extensions
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl rsync \
    libzip-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    telnet \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install zip pdo pdo_mysql gd exif; \
    a2enmod rewrite; \
    rm -rf /var/lib/apt/lists/*

# PHP runtime settings: production ini (display_errors off, log_errors on,
# upload limits), shipped from the tent-assets context.
COPY --chmod=0644 --from=tent-assets php/tent.ini /usr/local/etc/php/conf.d/tent.ini

# Apache access log: TENT_ACCESS_LOG_FORMAT presets and the tent.d drop-in
# directory. Written at build time (as root), since the image runs as app.
# The tent-assets build context is dockerfiles/tent.
COPY --from=tent-assets apache/00-tent-access-log.conf /etc/apache2/conf-enabled/00-tent-access-log.conf
COPY --from=tent-assets apache/envvars-tent.sh /tmp/envvars-tent.sh
RUN set -eu; \
    cat /tmp/envvars-tent.sh >> /etc/apache2/envvars; \
    rm /tmp/envvars-tent.sh; \
    mkdir -p /etc/apache2/tent.d; \
    apache2ctl -t

USER app

WORKDIR /var/www/html

COPY source/ /var/www/html/

CMD ["apache2-foreground"]
