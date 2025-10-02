# Single image per service. FrankenPHP (built on Caddy) serves the Symfony app
# over plain HTTP on :8080, which the central Caddy gateway proxies as
# /api/<svc>/* -> <svc>:8080. The same image also runs the background workers
# (outbox relay, consumers) via `php bin/console ...`, one container per worker.
FROM dunglas/frankenphp:1-php8.3-alpine

# PHP extensions: pdo_pgsql (DBAL), pcntl (worker signal handling),
# sockets (php-amqplib), opcache, mbstring (OTLP exporter).
RUN install-php-extensions pdo_pgsql pcntl sockets opcache mbstring

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dependencies first for better layer caching.
COPY composer.json ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# Application source.
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
 && mkdir -p var \
 && chown -R www-data:www-data var

ENV APP_ENV=prod
# Serve plain HTTP on :8080 (no auto-HTTPS — the gateway terminates TLS).
ENV SERVER_NAME=":8080"

EXPOSE 8080
