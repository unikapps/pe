#!/bin/sh
set -e

# Cache the configuration now that the runtime environment (compose or Coolify
# variables) exists. Caching it at build time would bake in empty values.
php artisan config:cache --no-interaction >/dev/null

exec "$@"
