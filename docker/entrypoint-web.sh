#!/bin/sh
# Só o web prepara o app: vários contêineres migrando juntos disputariam o banco.
set -eu

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# O podman-compose antigo não espera o Postgres ficar saudável: o web espera aqui (até 1 min).
tries=0
until php artisan db:monitor --databases=pgsql --max=100000 >/dev/null 2>&1; do
    tries=$((tries + 1))
    if [ "$tries" -ge 30 ]; then
        echo "O banco não respondeu em 1 minuto." >&2
        php artisan db:monitor --databases=pgsql --max=100000 >&2 || true
        exit 1
    fi
    sleep 2
done

php artisan migrate --force

exec "$@"
