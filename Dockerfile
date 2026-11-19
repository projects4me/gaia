FROM ghcr.io/projects4me/gaia-env:8.5.12-phalcon5.23.0

COPY ./ /var/www/html
COPY ./docker/php/conf.d/custom.ini /usr/local/etc/php/conf.d/zz-custom.ini

# Set ownership and permissions for the web root directory
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# install dependencies
RUN composer install