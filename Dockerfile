FROM composer:2.9.5 AS composer_base

WORKDIR /opt/repos 

# opentelemetry: sites with the OpenTelemetry Laravel packages require the extension, and Laravel's
# package:discover refuses to run without it. Nothing is traced from here - OTEL_PHP_AUTOLOAD_ENABLED
# isn't set, so the SDK never starts
RUN apk add --no-cache --virtual .build-deps ${PHPIZE_DEPS} \
    && pecl install opentelemetry \
    && docker-php-ext-enable opentelemetry \
    && apk del .build-deps \
    && rm -rf /tmp/pear \
    && addgroup -S composer \
    &&  adduser -S composer -G composer \
    && chown -R composer:composer /opt/repos

USER composer
