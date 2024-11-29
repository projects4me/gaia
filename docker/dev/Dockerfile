FROM rananoman/gaia:env-dev

COPY ./ /var/www/html

RUN chown www-data:www-data logs/

# install dependencies
RUN composer install