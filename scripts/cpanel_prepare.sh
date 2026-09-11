#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

echo "Scratchgard cPanel preparation"
php -v

if ! command -v composer >/dev/null 2>&1; then
  echo "ERROR: Composer not found. Use cPanel Terminal/Composer or upload a locally built vendor/ directory."
  exit 1
fi

composer install --no-dev --optimize-autoloader

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache || true

php artisan optimize:clear || true

echo
echo "Open /preflight.php, then /install in your browser."
echo "After installation add the cron lines from docs/QUICK_INSTALL.txt."
