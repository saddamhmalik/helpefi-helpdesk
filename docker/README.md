# Docker

Run the full Helpdesk stack (Laravel, MySQL, Redis, queue, scheduler, realtime WebSocket).

## Quick start (local development)

```bash
chmod +x docker/setup.sh docker/init-env.sh
./docker/setup.sh
```

Or prepare env only:

```bash
./docker/init-env.sh
cp .env.docker.example .env.docker   # if init-env did not run
```

Fresh database (resets MySQL data):

```bash
docker compose down -v
```

Add to `/etc/hosts`:

```
127.0.0.1 helpdesk.test
```

Open **http://helpdesk.test:8081** (nginx maps host port `8081` → container `80`).

## Services

| Service | Purpose |
|---|---|
| `nginx` | Web server |
| `app` | PHP-FPM (Laravel) |
| `mysql` | Central + tenant databases |
| `redis` | Cache, queue, realtime pub/sub |
| `queue` | Background jobs (discovery scans, email, …) |
| `scheduler` | Scheduled tasks every 30 seconds (`schedule:run`) |
| `realtime` | WebSocket server on port **8080** |
| `migrate` | One-shot migrations on startup |

## Environment

Copy the Docker env template:

```bash
cp .env.docker.example .env
```

Important variables:

```env
DB_HOST=mysql
REDIS_HOST=redis
APP_URL=http://helpdesk.test:8081
CENTRAL_APP_DOMAIN=helpdesk.test
REALTIME_WS_URL=ws://localhost:8080
REALTIME_WS_HOST=0.0.0.0
```

Tenant workspaces use subdomains, e.g. `demo.helpdesk.test:8081` — add each to `/etc/hosts` or use a wildcard DNS tool.

## Common commands

```bash
docker compose up -d
docker compose down
docker compose logs -f app queue realtime

docker compose exec app php artisan tenants:migrate
docker compose exec app php artisan test

docker compose run --rm migrate
```

## Production

1. Copy `.env.docker.example` to `.env` and set production values (`APP_ENV=production`, `APP_DEBUG=false`, strong passwords, real `APP_URL`, mail, Stripe, etc.).

2. Set `REALTIME_WS_URL` to your public WebSocket URL (e.g. `wss://realtime.example.com` behind an SSL-terminating proxy).

3. Build and start:

```bash
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d
```

4. First deploy — generate app key and seed if needed:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan key:generate --force
docker compose -f docker-compose.prod.yml run --rm migrate
```

Production compose bakes assets into the image (no bind mounts). Set `HTTP_PORT` and `REALTIME_PORT` if the defaults (`80`, `8080`) conflict.

### HTTPS / SSL

See [docs/ssl-and-deployment.md](../docs/ssl-and-deployment.md) for full details.

```bash
chmod +x scripts/install-ssl-docker.sh
DOMAIN=helpdesk.example.com EMAIL=admin@example.com ./scripts/install-ssl-docker.sh
```

Uses Let's Encrypt + `docker-compose.ssl.yml`. Required `.env` changes: `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true`, `REALTIME_WS_URL=wss://…`.

## Notes

- After changing PHP routes or service providers in Docker dev, run `./docker/reload-php.sh` if you still see stale 404s (PHP-FPM opcache).
- **Live chat** requires `realtime` + `redis` + `queue` running.
- **Asset network discovery** cannot scan your LAN from inside Docker unless you use `network_mode: host` (Linux only). Use discovery on the host/VPS instead.
- **MySQL** grants the app user permission to create `helpdesk_tenant_*` databases for multi-tenancy.
