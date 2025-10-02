#!/bin/sh
# Run every backend service's PHPUnit suite inside a PHP 8.3 container.
#
# From the repo root:
#   make test
# or directly:
#   docker run --rm -v "$PWD":/app -w /app php:8.3-cli-alpine sh scripts/run-tests.sh
#
# Pass service names as arguments to run a subset:
#   docker run --rm -v "$PWD":/app -w /app php:8.3-cli-alpine sh scripts/run-tests.sh orders crm
set -u

apk add --no-cache --quiet unzip git >/dev/null 2>&1
if [ ! -f /usr/local/bin/composer ]; then
  wget -qO /usr/local/bin/composer https://getcomposer.org/download/latest-stable/composer.phar
  chmod +x /usr/local/bin/composer
fi
export COMPOSER_CACHE_DIR=/app/.composer-cache
export COMPOSER_ALLOW_SUPERUSER=1

SERVICES="${*:-identity catalog orders inventory production crm notifications reporting tg-bot}"
fail=""
for s in $SERVICES; do
  echo "================ $s ================"
  cd "/app/services/$s" || { fail="$fail $s(cd)"; continue; }
  # Ignore only the missing native extensions — NOT the php version, so composer
  # resolves dependency versions against the container's PHP (matching prod).
  composer install --no-interaction --no-progress -q \
    --ignore-platform-req=ext-pcntl \
    --ignore-platform-req=ext-pdo \
    --ignore-platform-req=ext-sockets 2>&1 | tail -3
  if php vendor/bin/phpunit --no-coverage --display-warnings; then
    echo "PASS: $s"
  else
    echo "FAIL: $s"
    fail="$fail $s"
  fi
done
echo "============================================"
if [ -n "$fail" ]; then echo "FAILED SERVICES:$fail"; exit 1; else echo "ALL SERVICES GREEN"; fi
