#!/usr/bin/env sh
set -e

if [ ! -f .env ] && [ -f .env.example ] && [ -z "${APP_ENV:-}" ]; then
    cp .env.example .env
fi

if [ "${WAIT_FOR_DB:-true}" = "true" ] && [ -n "${DB_HOST:-}" ]; then
    php -r '
        $host = getenv("DB_HOST") ?: "database";
        $port = getenv("DB_PORT") ?: "3306";
        $user = getenv("DB_USERNAME") ?: "root";
        $pass = getenv("DB_PASSWORD") ?: "";
        $timeout = (int) (getenv("DB_WAIT_TIMEOUT") ?: 90);
        $deadline = time() + $timeout;

        do {
            try {
                new PDO("mysql:host={$host};port={$port}", $user, $pass);
                exit(0);
            } catch (Throwable $exception) {
                if (time() >= $deadline) {
                    fwrite(STDERR, "Database is not reachable: {$exception->getMessage()}\n");
                    exit(1);
                }

                sleep(2);
            }
        } while (true);
    '
fi

php artisan config:clear --ansi >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force
fi

exec "$@"
