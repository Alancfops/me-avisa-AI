#!/bin/sh
# Só o web prepara o app: vários contêineres migrando juntos disputariam o banco.
set -eu

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force

exec "$@"
