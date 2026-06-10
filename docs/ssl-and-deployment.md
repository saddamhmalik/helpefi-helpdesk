# SSL and deployment

Helpdesk runs over HTTP in local Docker (`:8081`). Production needs HTTPS for sessions, OAuth redirects, Stripe webhooks, and secure cookies.

## Quick reference

| Method | Install stack | Enable SSL |
|--------|---------------|------------|
| Docker (dev) | `./docker/setup.sh` | Not required locally |
| Docker (prod) | `docker compose -f docker-compose.prod.yml up -d` | `./scripts/install-ssl-docker.sh` |
| Native (VPS) | `sudo ./scripts/install-native.sh` | `sudo ./scripts/install-ssl-native.sh` |
| Native (Valet) | `composer install && npm run build && valet link` | `valet secure helpdesk` |

---

## Required configuration changes for HTTPS

Update `.env` (or `.env.docker` for Docker prod):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://helpdesk.example.com

CENTRAL_APP_DOMAIN=helpdesk.example.com
TENANCY_CUSTOM_DOMAIN_CNAME=tenants.helpdesk.example.com

SESSION_SECURE_COOKIE=true

REALTIME_WS_URL=wss://rt.helpdesk.example.com
```

### Why each variable matters

| Variable | Change |
|----------|--------|
| `APP_URL` | Must use `https://`. Laravel generates links, emails, and webhook URLs from this. |
| `CENTRAL_APP_DOMAIN` | Central admin host (no `https://`, no port). |
| `SESSION_SECURE_COOKIE` | Set `true` so session cookies are only sent over HTTPS. |
| `REALTIME_WS_URL` | Change `ws://` → `wss://`. Browsers block mixed content on HTTPS pages. |
| `APP_DEBUG` | Must be `false` in production. |

### DNS records

| Record | Purpose |
|--------|---------|
| `A` `helpdesk.example.com` → server IP | Central app |
| `A` or `CNAME` `*.helpdesk.example.com` | Tenant workspaces (`acme.helpdesk.example.com`) |
| `A` or `CNAME` `rt.helpdesk.example.com` | WebSocket proxy (optional but recommended) |
| `TXT` `_acme-challenge...` | Only when using wildcard cert (`WILDCARD=true`) |

Tenant **custom domains** (e.g. `support.customer.com`) need their own certificate or a reverse proxy that terminates SSL per host.

### Third-party services to update

After switching to HTTPS, update redirect/callback URLs in:

- Stripe dashboard (checkout + webhooks)
- Google / Microsoft / Zoho mail OAuth apps
- Slack / integration webhooks you configured manually
- Inbound email webhook URLs shown in **Settings → Email**

### Firewall

Open ports **80** and **443** on the server. Port 8080 (realtime) should stay internal; expose it via nginx `wss://` proxy instead.

---

## Docker production + SSL

### 1. Prepare environment

```bash
cp .env.docker.example .env.docker
# Edit: DB passwords, mail, Stripe, CENTRAL_APP_DOMAIN, etc.
./docker/init-env.sh
```

### 2. Build and start (HTTP first)

```bash
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d
```

### 3. Issue certificate and enable HTTPS

```bash
chmod +x scripts/install-ssl-docker.sh
DOMAIN=helpdesk.example.com EMAIL=admin@example.com ./scripts/install-ssl-docker.sh
```

With tenant subdomains on the same domain:

```bash
DOMAIN=helpdesk.example.com EMAIL=admin@example.com WILDCARD=true ./scripts/install-ssl-docker.sh
```

With a dedicated WebSocket host:

```bash
DOMAIN=helpdesk.example.com \
EMAIL=admin@example.com \
REALTIME_DOMAIN=rt.helpdesk.example.com \
./scripts/install-ssl-docker.sh
```

### 4. Certificate renewal

The `certbot` service in `docker-compose.ssl.yml` renews every 12 hours. Start it with:

```bash
docker compose -f docker-compose.prod.yml -f docker-compose.ssl.yml --profile ssl up -d certbot
```

---

## Native (non-Docker) production

### 1. Install stack

```bash
chmod +x scripts/install-native.sh scripts/install-ssl-native.sh
sudo DOMAIN=helpdesk.example.com DB_PASS='your-db-password' ./scripts/install-native.sh
```

Installs: nginx, PHP 8.4-FPM, MySQL, Redis, Node 22, Supervisor (queue, scheduler, realtime).

### 2. Enable SSL

```bash
sudo DOMAIN=helpdesk.example.com EMAIL=admin@example.com ./scripts/install-ssl-native.sh
```

### 3. Laravel Valet (macOS local only)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
valet link helpdesk
valet secure helpdesk
```

Update `.env`:

```env
APP_URL=https://helpdesk.test
SESSION_SECURE_COOKIE=true
```

---

## Realtime WebSocket behind SSL

The Node realtime server speaks plain WebSocket. Terminate TLS at nginx:

- **Docker:** `REALTIME_DOMAIN` creates `docker/nginx/generated/realtime.conf` (proxy to `realtime:8080`).
- **Native:** `install-ssl-native.sh` creates a separate nginx vhost proxying to `127.0.0.1:8080`.

Set `REALTIME_WS_URL=wss://rt.helpdesk.example.com` (no path, no port).

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| Redirect loop | `APP_URL` must match the hostname users visit. |
| Session lost after login | Set `SESSION_SECURE_COOKIE=true` only when using HTTPS. |
| Mixed content / WS fails | `REALTIME_WS_URL` must be `wss://`, not `ws://`. |
| Tenant subdomain SSL error | Use `WILDCARD=true` or add each subdomain to the cert. |
| OAuth redirect mismatch | Update provider console to `https://` callback URLs. |
| `config:cache` stale | Run `php artisan config:clear && php artisan config:cache` after `.env` changes. |
