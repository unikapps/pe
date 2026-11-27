# Development

## The Docker stack

`make start` runs everything from `compose.yaml`:

| Service     | What it does                                                     |
| ----------- | ---------------------------------------------------------------- |
| `pgsql`     | Postgres 18, also on `localhost:5433`, with a testing database   |
| `migrate`   | One-shot: runs the migrations, then exits                        |
| `php`       | PHP-FPM, the app                                                 |
| `web`       | nginx: static files, PHP to `php`, `/app` to Reverb              |
| `worker`    | Queue worker: submissions, webhooks, image copies, broadcasts    |
| `scheduler` | Runs `generations:sync-stale` every minute (the fallback poller) |
| `reverb`    | WebSocket server for live updates                                |

Images: PHP 8.6 (official `php:8.6-fpm`), nginx stable (official image, run as non-root), Postgres 18. Node 26 LTS only builds the assets. OS security updates are applied at build time, and `make start` rebuilds on the newest base images.

| Command      | What it does                                       |
| ------------ | -------------------------------------------------- |
| `make start` | Build and start (creates `.env` with an `APP_KEY`) |
| `make stop`  | Stop; data is kept                                 |
| `make logs`  | Follow the app's logs                              |
| `make fresh` | Delete all data and start again                    |
| `make test`  | Pint, Larastan and the test suite, in Docker       |

Set `APP_PORT` in `.env` if port 8000 is taken.

## Configuration

The image provider is configured in `.env` (there are no defaults in the code):

| Variable                          | What it is                                  |
| --------------------------------- | ------------------------------------------- |
| `IMAGE_PROVIDER_BASE_URL`         | The API's base URL, ending in `/v1`         |
| `IMAGE_PROVIDER_API_KEY`          | API key; use a separate key per environment |
| `IMAGE_PROVIDER_WEBHOOK_SECRET`   | That key's webhook signing secret           |
| `IMAGE_PROVIDER_SIGNATURE_HEADER` | The header the provider signs webhooks with |

Webhooks need a public URL. Expose the app with a tunnel (Expose, ngrok, Cloudflare Tunnel) and set the key's webhook URL to `https://<tunnel>/webhooks/provider`. Without webhooks, the scheduler still picks up results by polling after 3 minutes.

## Developing on the host

With PHP 8.6, Composer and Node 26 installed, run only the database in Docker:

```bash
docker compose up -d pgsql    # Postgres on port 5433 (plus a testing database)
composer setup                # install, key, migrate, build
php artisan dev               # web server, queue worker, Reverb, Vite, logs
php artisan schedule:work     # in a second terminal: the fallback poller
```

## Tests

```bash
make test                               # Pint, Larastan and Pest in Docker, on the production PHP
npm run check && npm run types:check    # frontend lint, format and types
```

Tests run against Postgres (row locks and `jsonb` behave differently on SQLite).

## CI

`.github/workflows/ci.yml` runs two jobs on every push and pull request:

- **verify**: workflow audit (zizmor), secret scan (gitleaks), dependency audits (Composer and npm: vulnerable production packages fail the build, dev ones warn), Pint, Larastan, frontend lint and types, the test suite on PHP 8.6 against Postgres 18, and a migrate, roll back, migrate round trip.
- **docker** (after verify): builds the production images with `make start`, smoke-tests the running stack (pages, assets, blocked paths, the WebSocket handshake through nginx) and scans both images with Trivy (fixable high and critical vulnerabilities fail the build).

Actions are pinned to commit SHAs. Dependabot proposes action and base-image updates weekly, and security updates for Composer and npm packages.
