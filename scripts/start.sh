#!/bin/sh
# Build and start the whole stack (see compose.yaml). Used by `make start`
# and `composer start`. Needs only Docker and openssl.
set -eu

cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created .env from .env.example"
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    key="base64:$(openssl rand -base64 32)"
    sed -i.bak "s#^APP_KEY=.*#APP_KEY=${key}#" .env && rm -f .env.bak
    echo "Generated APP_KEY in .env"
fi

# --pull: rebuild on the newest base images, so security fixes get in.
docker compose build --pull
docker compose up -d

port="${APP_PORT:-$(grep -E '^APP_PORT=' .env | cut -d= -f2 || true)}"
url="http://localhost:${port:-8000}"

printf 'Waiting for %s ' "$url"
i=0
until curl -fsS "$url/up" >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -ge 90 ]; then
        echo
        echo "The app did not come up. Check: docker compose logs migrate web"
        exit 1
    fi
    printf '.'
    sleep 2
done

echo
echo "Ready: $url  (register any account to start)"
grep -q '^IMAGE_PROVIDER_API_KEY=.' .env || echo "Note: set IMAGE_PROVIDER_API_KEY in .env, then run make start again, to generate images."
