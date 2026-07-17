# ─── Stage 1: Composer – install PHP deps ────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-plugins \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs


# ─── Stage 2: Final FrankenPHP image ─────────────────────────────────────────
FROM ronaregen/php:frankenphp-latest AS app

# Lets the entrypoint drop from root to www-data after fixing permissions on
# the bind-mounted storage/data dirs (see entrypoint.sh) — the base image only
# ships busybox's setpriv, which doesn't support --reuid/--regid.
RUN apk add --no-cache su-exec

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /app

COPY --chown=www-data:www-data . .
COPY --from=vendor  --chown=www-data:www-data /app/vendor       ./vendor

# composer.json's post-autoload-dump (package:discover + filament:upgrade)
# never runs — the vendor stage installs with --no-scripts because it only
# has composer.json/lock, not the full app, so it couldn't run them anyway.
# Without package:discover, Filament's (and every other package's) service
# providers never register, which is why Blade can't resolve components
# like filament-panels::form.actions. Run them here instead, now that both
# vendor/ and the full app are actually present.
RUN php artisan package:discover --ansi \
    && php artisan filament:upgrade

# Caddy (inside FrankenPHP) writes its config/data here
ENV XDG_CONFIG_HOME=/app/data/caddy/config
ENV XDG_DATA_HOME=/app/data/caddy/data

RUN mkdir -p \
        storage/framework/{cache,sessions,views} \
        storage/logs \
        bootstrap/cache \
        data/caddy/config \
        data/caddy/data \
    && chown -R www-data:www-data storage bootstrap/cache data \
    && chmod -R 775 storage bootstrap/cache data

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Stays root here on purpose: docker-compose.yaml bind-mounts storage/ and
# data/ from the host, which can come in root-owned (e.g. Docker
# auto-creating the dir on first `up`). entrypoint.sh re-chowns them and
# drops to www-data via su-exec before running anything app-level.

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php", "artisan", "octane:frankenphp", \
     "--workers=auto", \
     "--max-requests=500", \
     "--host=0.0.0.0", \
     "--port=8000"]
