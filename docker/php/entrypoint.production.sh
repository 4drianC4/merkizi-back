#!/bin/sh
set -eu

if [ "${1:-}" = "web" ]; then
    case "${PORT}" in
        ''|*[!0-9]*) echo 'PORT must be a number.' >&2; exit 1 ;;
    esac
    if [ "$PORT" -lt 1024 ] || [ "$PORT" -gt 65535 ]; then
        echo 'PORT must be between 1024 and 65535.' >&2
        exit 1
    fi

    php artisan config:cache --no-interaction
    if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
        php artisan migrate --force --no-interaction
    fi
    if [ "${RUN_SEEDERS:-false}" = "true" ]; then
        php artisan db:seed --force --no-interaction
    fi
    php artisan route:cache --no-interaction
    php artisan view:cache --no-interaction
    envsubst '${PORT}' < docker/nginx/production.conf.template > /tmp/nginx.conf
    nginx -t -c /tmp/nginx.conf
    exec supervisord -c /etc/supervisor/supervisord.conf
fi

exec "$@"
