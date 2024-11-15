FROM rananoman/gaia:env-dev

COPY ./ /var/www/html

# install dependencies
RUN composer install