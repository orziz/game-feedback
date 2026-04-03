FROM php:8.2-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev gettext-base \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/app
COPY docker/php-fpm/php.ini /usr/local/etc/php/zz-game-feedback.ini.template
COPY docker/php-fpm/docker-entrypoint.sh /usr/local/bin/game-feedback-php-entrypoint
COPY server/ /var/www/app/server/
RUN chmod +x /usr/local/bin/game-feedback-php-entrypoint \
    && mkdir -p /var/www/app/runtime /var/www/app/server/storage /var/www/app/server/config \
    && chown -R www-data:www-data /var/www/app/runtime /var/www/app/server/storage /var/www/app/server/config

ENTRYPOINT ["game-feedback-php-entrypoint"]
CMD ["php-fpm"]
