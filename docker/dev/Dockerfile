# CI builds this on gaia-env:current / gaia-env:next to check the effective PHP config
ARG ENV_IMAGE=ghcr.io/projects4me/gaia-env:7.4.21-phalcon4.1.2
FROM ${ENV_IMAGE}

COPY ./ /var/www/html

# Deployed image only: OPcache on (built into PHP 8.5, so only enabled when not already loaded), Xdebug and xhprof not loaded (the env-dev base loads them)
RUN (php -m | grep -qi 'zend opcache' || docker-php-ext-enable opcache) \
    && rm -f /usr/local/etc/php/conf.d/*xdebug*.ini /usr/local/etc/php/conf.d/*xhprof*.ini
COPY ./docker/dev/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY ./docker/php/conf.d/custom.ini /usr/local/etc/php/conf.d/zz-custom.ini
# display_errors Off and log_errors On, set explicitly so the base image cannot turn them on
COPY ./docker/php/conf.d/production.ini /usr/local/etc/php/conf.d/zzz-production.ini

ENV HERMES_URL=http://hermes.projects4.me

# Set ownership and permissions for the web root directory
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# install dependencies
RUN composer install

# Make the start script executable
RUN chmod +x /var/www/html/docker/dev/start.sh

# Use the start script as the default command
CMD ["/bin/bash", "/var/www/html/docker/dev/start.sh"]