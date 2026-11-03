# darthjee/scripts:0.9.0 is published for linux/amd64 only. Only the plain
# shell script deploy_frontend.sh is copied out of this stage (nothing runs in
# it), so pinning it to amd64 is safe for both the amd64 and arm64 variants.
# #301 replaces this line with the multi-arch 0.12.0 and drops the pin.
FROM --platform=linux/amd64 darthjee/scripts:0.9.0 AS scripts
FROM php:8.4-apache as base

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

# PHP runtime limits for Tent (uploads)
RUN printf 'upload_max_filesize=20M\npost_max_size=25M\n' > /usr/local/etc/php/conf.d/tent.ini

USER app

WORKDIR /var/www/html

COPY source/ /var/www/html/

CMD ["apache2-foreground"]
