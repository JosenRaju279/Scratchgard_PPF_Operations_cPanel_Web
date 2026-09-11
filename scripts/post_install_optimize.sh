#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan scratchgard:status
