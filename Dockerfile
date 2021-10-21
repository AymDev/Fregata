FROM php:8.0.11-alpine3.13

WORKDIR /var/www/html

COPY --from=composer:2.0.12 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache bash
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-enable pdo_mysql

ENTRYPOINT ["bash"]
