FROM rananoman/gaia:1.0.0

RUN curl --location --output /usr/local/bin/phpunit https://phar.phpunit.de/phpunit-5.7.27.phar

RUN chmod +x /usr/local/bin/phpunit

CMD ["apache2-foreground"]