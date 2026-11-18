#!/usr/bin/env bash
# Setup the B2B Transportation ERP for local demo use.
# Usage: git clone <repo> && cd <repo> && ./setup.sh && php artisan serve

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

echo "==> Checking requirements"
for cmd in php composer node npm; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "ERROR: '$cmd' is not installed or not on PATH." >&2
        exit 1
    fi
done
if ! php -m | grep -qi 'pdo_sqlite'; then
    echo "ERROR: PHP PDO SQLite driver is missing (php -m | grep pdo_sqlite)." >&2
    echo "Install it, e.g. 'sudo apt install php-sqlite3', then re-run." >&2
    exit 1
fi
PHP_VERSION_ID=$(php -r 'echo PHP_VERSION_ID;')
if [ "$PHP_VERSION_ID" -lt 80200 ]; then
    echo "ERROR: PHP 8.2 or newer is required." >&2
    exit 1
fi

echo "==> Installing Composer dependencies"
composer install --no-interaction --prefer-dist

echo "==> Preparing .env"
SQLITE_DB="$ROOT/database/database.sqlite"

if [ -f .env ]; then
    echo "    .env already exists — leaving it untouched"
else
    cp .env.example .env
    sed -i 's|^APP_URL=.*|APP_URL=http://127.0.0.1:8000|' .env
    sed -i 's|^DB_CONNECTION=.*|DB_CONNECTION=sqlite|' .env
    sed -i 's|^DB_HOST=.*|DB_HOST=|' .env
    sed -i 's|^DB_PORT=.*|DB_PORT=|' .env
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=\"$SQLITE_DB\"|" .env
    sed -i 's|^DB_USERNAME=.*|DB_USERNAME=|' .env
    sed -i 's|^DB_PASSWORD=.*|DB_PASSWORD=|' .env
    sed -i "s|^APP_TIMEZONE=.*|APP_TIMEZONE=UTC|" .env
    php artisan key:generate --force
fi

if ! grep -qE '^APP_KEY=base64:.+' .env; then
    echo "==> APP_KEY missing — generating"
    php artisan key:generate --force
fi

DB_CONNECTION=$(grep -E '^DB_CONNECTION=' .env | head -1 | cut -d= -f2- | tr -d '[:space:]"')

if [ "$DB_CONNECTION" = "sqlite" ]; then
    echo "==> Preparing SQLite database at $SQLITE_DB"
    mkdir -p database
    if [ ! -f "$SQLITE_DB" ]; then
        touch "$SQLITE_DB"
        echo "    created database.sqlite"
    else
        echo "    database.sqlite already exists — leaving it untouched"
    fi

    echo "==> Running migrations"
    php artisan migrate --force

    echo "==> Seeding demo data"
    php artisan db:seed --force
else
    echo "==> Non-SQLite DB_CONNECTION='$DB_CONNECTION' in .env — skipping"
    echo "    migrate/seed for safety. Run 'php artisan migrate --force && php artisan db:seed --force'"
    echo "    yourself once the database at that connection exists."
fi

echo "==> Installing npm dependencies"
npm install --no-audit --no-fund

echo "==> Building frontend assets (vite build)"
npm run build

echo ""
echo "======================================================="
echo " Setup complete. Start the app with:"
echo ""
echo "   php artisan serve"
echo ""
echo " Then open: http://127.0.0.1:8000"
echo ""
echo " Demo logins (created by the seeder):"
echo "   Admin portal   http://127.0.0.1:8000/admin"
echo "     superadmin@example.com / password"
echo "   Company portal http://127.0.0.1:8000/company"
echo "     manager@acme.example  / password"
echo "======================================================="
