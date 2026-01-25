FROM cimg/php:8.4

USER root
# Install necessary dependencies for Composer and PHP extensions
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    libzip-dev \
    telnet \
    $PHPIZE_DEPS \
    && docker-php-ext-install zip \
    && pecl install pcov \
    && docker-php-ext-enable pcov; \
    rm -rf /var/lib/apt/lists/*

RUN mkdir -p /home/circleci/project; \
    chown circleci.circleci -R /home/circleci

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN ln -s /home/circleci/project/vendor/phpunit/phpunit/phpunit /usr/local/sbin/

USER circleci

ENV PATH="$PATH://home/circleci/project/vendor/bin"

COPY --chown=circleci:circleci ./source/composer.* /home/circleci/

WORKDIR /home/circleci/
RUN composer install
WORKDIR /home/circleci/project
