#!/bin/sh
set -e

cd /var/www

# Framework-Verzeichnisse im (ggf. frisch gemounteten) storage-Volume sicherstellen.
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
         storage/logs storage/app/public bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Nur der App-Container (RUN_SETUP=true) initialisiert DB/Key — Worker/Reverb/Scheduler nicht.
# Postgres/Redis sind via `depends_on: condition: service_healthy` bereits bereit;
# `php artisan` bootet Laravel, das die gemountete .env liest (DB_HOST, REDIS_HOST, ...).
if [ "${RUN_SETUP:-false}" = "true" ]; then
    # APP_KEY einmalig erzeugen, falls in der gemounteten .env noch leer.
    if grep -qE '^APP_KEY=$' .env 2>/dev/null; then
        echo "[entrypoint] Erzeuge APP_KEY ..."
        php artisan key:generate --force
    fi

    echo "[entrypoint] Migrationen ..."
    php artisan migrate --force

    # Demo-Daten nur bei FRISCHER DB (DemoSeeder ist nicht idempotent: Tenant::create).
    TENANTS="$(php artisan tinker --execute='echo \App\Domains\Identity\Models\Tenant::count();' 2>/dev/null | tail -n1 | tr -dc '0-9')"
    if [ "${TENANTS:-0}" = "0" ]; then
        echo "[entrypoint] Frische DB → Seeding (Demo-Mandant + admin@opcare.local / password) ..."
        php artisan db:seed --force
    else
        echo "[entrypoint] DB bereits befüllt (${TENANTS} Mandanten) → kein Seeding."
    fi

    php artisan storage:link 2>/dev/null || true
fi

exec "$@"
