FROM php:7.4.19-alpine

WORKDIR /var/www/html

COPY --from=composer:2.0.12 /usr/bin/composer /usr/bin/composer
COPY --from=mlocati/php-extension-installer:1.2.24 /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache bash && \
    install-php-extensions pdo_mysql

ENTRYPOINT ["bash"]