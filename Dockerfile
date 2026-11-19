FROM ghcr.io/projects4me/gaia-env:7.4.21-phalcon4.1.2

COPY ./ /var/www/html
COPY ./docker/php/conf.d/custom.ini /usr/local/etc/php/conf.d/zz-custom.ini

# Set ownership and permissions for the web root directory
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# install dependencies
RUN composer install