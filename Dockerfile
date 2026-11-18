FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.3-apache

# Módulos do Apache: headers/expires/deflate para cache e compressão
RUN a2enmod headers expires deflate \
    && sed -ri 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf

COPY docker/apache.conf /etc/apache2/conf-available/site.conf
RUN a2enconf site

WORKDIR /var/www/html
COPY . /var/www/html/
COPY --from=vendor /app/vendor /var/www/html/vendor

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/robots.txt") === false ? 1 : 0);'
