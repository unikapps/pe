# syntax=docker/dockerfile:1
# =============================================================================
# Images for every process of the app, from one Dockerfile:
#
#   app   PHP-FPM. Compose also runs it as the queue worker, the scheduler,
#         Reverb and the one-shot migration job, with a different command.
#   web   nginx: static files, PHP requests to app (FastCGI), /app to Reverb.
#   test  app plus dev dependencies, to run the suite on the production PHP.
#
#   Local:    make start  (or composer start)  -> compose.yaml
#   Coolify:  docker-compose.coolify.yml
#
# No environment-specific values are baked in: configuration is cached when a
# container starts (docker/php/entrypoint.sh), and the browser reaches Reverb on
# the page's own origin through nginx.
# =============================================================================

ARG PHP_VERSION=8.6
ARG NODE_VERSION=26

# -----------------------------------------------------------------------------
# Stage: base - PHP and the extensions the app needs
# -----------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-trixie AS base

# Debian security fixes released after the base image was built (CI's Trivy
# scan fails on fixable HIGH/CRITICAL vulnerabilities).
RUN apt-get update \
    && apt-get upgrade -y --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
# mbstring: prompt length is counted in Unicode characters (mb_strlen).
RUN install-php-extensions pdo_pgsql pcntl bcmath intl mbstring zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# -----------------------------------------------------------------------------
# Stage: build - Composer and npm dependencies, frontend assets
# -----------------------------------------------------------------------------
FROM node:${NODE_VERSION}-trixie-slim AS node

FROM base AS build

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Dependency layers are keyed on lockfiles only, so code changes reuse them.
# --no-scripts/--no-autoloader: package:discover needs the full source tree.
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache,sharing=locked \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY package.json package-lock.json .npmrc ./
# sharing=locked: compose builds the app target once per service, in parallel;
# without the lock those installs race on the shared cache and can leave out
# optional native packages (vite-plus's Linux binary).
RUN --mount=type=cache,target=/root/.npm,sharing=locked npm ci

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && php artisan route:list >/dev/null \
    || (echo "ERROR: Laravel failed to boot." && exit 1)

# Wayfinder's Vite plugin runs artisan to generate the typed routes, which is
# why this stage has PHP and the vendor directory.
ARG VITE_APP_NAME="GenStudio"
RUN VITE_APP_NAME="$VITE_APP_NAME" npm run build \
    && rm -rf node_modules

# -----------------------------------------------------------------------------
# Stage: test - the production PHP plus dev dependencies (make test)
# -----------------------------------------------------------------------------
FROM build AS test

# Larastan's workers need more than PHP's default 128M. The empty .env stops
# phpdotenv warning about a missing file: compose and phpunit.xml set everything.
RUN echo "memory_limit = 1G" > "$PHP_INI_DIR/conf.d/99-test.ini" \
    && touch .env

RUN --mount=type=cache,target=/tmp/composer-cache,sharing=locked \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --no-interaction --prefer-dist

CMD ["composer", "test"]

# -----------------------------------------------------------------------------
# Stage: app - PHP-FPM runtime, no Node, runs as www-data
# -----------------------------------------------------------------------------
FROM base AS app

COPY docker/php/php.ini "$PHP_INI_DIR/conf.d/99-app.ini"
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zzz-app.conf
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && chmod +x /usr/local/bin/app-entrypoint

COPY --from=build --chown=www-data:www-data /app /app
RUN rm -rf /app/tests

USER www-data

# Routes, views and events don't depend on the environment, so they're cached
# here. Config is cached at start, once the runtime variables exist.
RUN php artisan route:cache && php artisan view:cache && php artisan event:cache

EXPOSE 9000

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

# -----------------------------------------------------------------------------
# Stage: web - nginx in front of app; runs as non-root on port 8080
# -----------------------------------------------------------------------------
# The official image: it's rebuilt as soon as nginx or Alpine ship a fix
# (nginxinc/nginx-unprivileged lagged weeks behind). It's made non-root here
# the same way that image does it: PID file in /tmp, writable cache, port 8080.
FROM nginx:stable-alpine AS web

RUN apk upgrade --no-cache \
    && sed -i 's,^pid .*,pid /tmp/nginx.pid;,; /^user /d' /etc/nginx/nginx.conf \
    && chown -R nginx:nginx /var/cache/nginx /etc/nginx/conf.d

COPY --chown=nginx:nginx docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/public /app/public

USER nginx

EXPOSE 8080
