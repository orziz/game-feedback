#!/bin/sh
set -eu

envsubst '${PHP_POST_MAX_SIZE} ${PHP_UPLOAD_MAX_FILESIZE} ${PHP_MAX_FILE_UPLOADS}' \
  < /usr/local/etc/php/zz-game-feedback.ini.template \
  > /usr/local/etc/php/conf.d/zz-game-feedback.ini

exec "$@"
