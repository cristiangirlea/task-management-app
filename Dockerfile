# syntax=docker/dockerfile:1
#
# Production image: php-fpm serving the API and the MCP endpoint on :9000.
# A front proxy (Caddy in task-management-docker) speaks FastCGI to it.
# Configuration comes from the environment; see .env.example.

FROM php:8.4-fpm-alpine AS base

# fcgi: cgi-fcgi, used by the health check to ping php-fpm directly.
RUN apk add --no-cache fcgi
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql redis opcache zip bcmath

WORKDIR /var/www/html

# Dependencies are resolved in a throwaway stage with the same extensions,
# so Composer's platform check is real and Composer never ships.
FROM base AS build
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
    && composer check-platform-reqs --no-dev

FROM base
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY --from=build /var/www/html /var/www/html
# Only what Laravel writes at runtime belongs to the worker user.
RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

ENTRYPOINT ["sh", "docker/entrypoint.prod.sh"]
CMD ["php-fpm"]
