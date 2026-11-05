FROM rananoman/gaia:env-dev

COPY ./ /var/www/html
COPY ./docker/php/conf.d/custom.ini /usr/local/etc/php/conf.d/zz-custom.ini

# Set ownership and permissions for the web root directory
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# install dependencies
RUN composer install