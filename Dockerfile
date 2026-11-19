FROM rananoman/gaia:env-dev

COPY ./ /var/www/html
COPY ./docker/php/conf.d/custom.ini /usr/local/etc/php/conf.d/zz-custom.ini

# Set ownership and permissions for the web root directory
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# install dependencies
RUN composer install

# Scheduler for the daily tasks in docker/cron/crontab; -test fails the build on a crontab line that does not parse
RUN /var/www/html/docker/cron/install-supercronic.sh && supercronic -test /var/www/html/docker/cron/crontab

# Apache in the foreground, the scheduler beside it (GAIA_SCHEDULER=off skips it)
CMD ["/bin/bash", "/var/www/html/docker/cron/scheduler.sh", "apache2-foreground"]
