FROM php:8.4-apache as base

# Create app user and directory structure
RUN useradd -u 1000 app; \
    mkdir -p /home/app/app; \
    chown app:app -R /home/app

# Install necessary dependencies for Composer and PHP extensions
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    libzip-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    telnet \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install zip pdo pdo_mysql gd exif \
    && pecl install pcov \
    && docker-php-ext-enable pcov; \
    a2enmod rewrite; \
    rm -rf /var/lib/apt/lists/*

# PHP runtime limits for Tent (uploads)
RUN printf 'upload_max_filesize=20M\npost_max_size=25M\n' > /usr/local/etc/php/conf.d/tent.ini

# Install composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Create symbolic link from source to Apache's web root
RUN rm -rf /var/www/html; \
    ln -s /home/app/app/source /var/www/html

USER app

ENV PATH="$PATH:/home/app/app/vendor/bin"

WORKDIR /home/app/app

FROM base as builder

COPY --chown=app:app ./composer.* /home/app/app/
COPY --chown=app:app ./source/ /home/app/app/source/

RUN composer install

FROM base

COPY --from=builder /home/app/app/vendor/ /home/app/app/vendor/

CMD ["apache2-foreground"]
