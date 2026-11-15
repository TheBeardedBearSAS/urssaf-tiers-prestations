ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# The Debian release of the base image follows PHP_VERSION (8.2 to 8.5), so apt package versions cannot be pinned
# without breaking the other bases; the security updates of the release are taken at build time instead.
# hadolint ignore=DL3008
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev libsqlite3-dev \
    && docker-php-ext-install -j"$(nproc)" intl pdo_sqlite zip \
    && pecl install pcov xdebug \
    && docker-php-ext-enable pcov \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Xdebug is installed but only loaded when XDEBUG_MODE is set (see entrypoint / `make coverage-paths`).
RUN mkdir -p /usr/local/etc/php/xdebug.d \
    && echo "zend_extension=xdebug" > /usr/local/etc/php/xdebug.d/xdebug.ini \
    && echo "pcov.enabled=0" > /usr/local/etc/php/xdebug.d/zz-pcov-off.ini \
    && { \
        echo "memory_limit=-1"; \
        echo "pcov.directory=/app/src"; \
        echo "date.timezone=UTC"; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /app
# Never run as root: docker-compose.yml sets the host UID/GID, this is the default for a plain `docker run`.
USER 1000:1000
ENTRYPOINT ["entrypoint"]
CMD ["php", "-a"]
