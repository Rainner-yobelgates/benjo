#!/bin/sh
set -e

# storage/ and data/ are bind-mounted from the host (docker-compose.yaml) and
# may not exist yet, or come in root-owned (Docker auto-creates missing bind
# mount sources as root on first `up`). Either way that breaks writes as
# www-data, so fix ownership on every start rather than relying on the host
# directory being pre-chowned, then drop to www-data for everything else —
# the app itself must never run as root.
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs data/caddy/config data/caddy/data
chown -R www-data:www-data storage data

run() {
    su-exec www-data "$@"
}

# Cache config/routes at startup — env is available here, not at build time.
# Deliberately NOT `artisan optimize`/`view:cache`: eagerly compiling every
# view crashes on some Filament v5 panel components (dynamically resolved,
# not statically analyzable — "Unable to locate a class or view for
# component [filament-panels::form.actions]"), which under `set -e` took
# the whole container down in a restart loop. filament:optimize covers
# Filament's own (compatible) view/component caching instead.
run php artisan optimize:clear
run php artisan migrate --force
run php artisan config:cache
run php artisan event:cache
run php artisan route:cache
run php artisan filament:optimize

run php artisan storage:link --quiet 2>/dev/null || true

exec su-exec www-data "$@"
