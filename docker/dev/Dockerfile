FROM rananoman/gaia:env-dev

COPY ./ /var/www/html

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