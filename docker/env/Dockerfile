# Gaia runtime image: PHP + Apache + Phalcon and the extensions Gaia needs.
# One recipe for both stacks; see build.sh and README.md for the two targets.
ARG BASE_IMAGE=php:7.4.21-apache-buster
FROM ${BASE_IMAGE}

ARG PHALCON_VERSION=4.1.2
# psr is only needed by Phalcon 4; leave empty for Phalcon 5.
ARG PSR_VERSION=1.1.0
ARG XDEBUG_VERSION=3.1.5
ARG XHPROF_VERSION=2.3.7
# Empty skips the extension.
ARG MONGODB_VERSION=1.15.3
ARG IMAGICK_VERSION=
ARG PHPUNIT_VERSION=9.6.21
# Debian 10 is end of life; set to 1 to point apt at archive.debian.org.
ARG DEBIAN_ARCHIVE=0

RUN if [ "$DEBIAN_ARCHIVE" = "1" ]; then \
        sed -i 's|deb.debian.org|archive.debian.org|g; s|security.debian.org/debian-security|archive.debian.org/debian-security|g; /buster-updates/d' /etc/apt/sources.list; \
    fi \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS git unzip zip postgresql-client iputils-ping \
        libpq-dev libzip-dev libmagickwand-dev libcurl4-openssl-dev libxml2-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql zip exif mbstring curl xml

RUN pecl install imagick${IMAGICK_VERSION:+-$IMAGICK_VERSION} < /dev/null && docker-php-ext-enable imagick

RUN if [ -n "$PSR_VERSION" ]; then pecl install psr-${PSR_VERSION} && docker-php-ext-enable psr; fi \
    && MAKEFLAGS="-j$(nproc)" pecl install phalcon-${PHALCON_VERSION} && docker-php-ext-enable phalcon

RUN pecl install xdebug-${XDEBUG_VERSION} && docker-php-ext-enable xdebug \
    && pecl install xhprof-${XHPROF_VERSION} && docker-php-ext-enable xhprof \
    && if [ -n "$MONGODB_VERSION" ]; then pecl install mongodb-${MONGODB_VERSION} && docker-php-ext-enable mongodb; fi

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN curl -fsSL -o /usr/local/bin/phpunit https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar \
    && chmod +x /usr/local/bin/phpunit

RUN a2enmod rewrite

WORKDIR /var/www/html
EXPOSE 80
CMD ["apache2-foreground"]
