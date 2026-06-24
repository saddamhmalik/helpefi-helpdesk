# SSL and deployment

helpefi runs over HTTP in local Docker (`:8081`). Production needs HTTPS for sessions, OAuth redirects, Stripe webhooks, and secure cookies.

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
| `A` or `CNAME` `*.helpdesk.example.com` | Tenant workspaces (`acme.helpefi.com`) |
| `A` or `CNAME` `rt.helpdesk.example.com` | WebSocket proxy (optional but recommended) |
| `TXT` `_acme-challenge...` | Only when using wildcard cert (`WILDCARD=true`) |

Tenant **custom domains** (e.g. `help.customer.com`) need their own Let's Encrypt certificate on your server. A wildcard cert for `*.helpefi.com` does **not** cover customer-owned domains.

### Custom domain SSL (native / EC2)

Prerequisites:

1. Custom domain verified in **Settings → Custom domain**
2. CNAME `help` → `tenants.helpefi.com` (or your `TENANCY_CUSTOM_DOMAIN_CNAME`)
3. `tenants.helpefi.com` resolves to the same server as the main app
4. Ports **80** and **443** open on the server

**Option A — helper script**

```bash
cd ~/helpefi-helpdesk
chmod +x scripts/issue-custom-domain-ssl.sh

sudo CUSTOM_DOMAIN=help.codikal.com EMAIL=hello@helpefi.com \
  ./scripts/issue-custom-domain-ssl.sh
```

**Option B — certbot only**

```bash
sudo certbot --nginx -d help.codikal.com \
  --email hello@helpefi.com --agree-tos --no-eff-email --redirect
```

If certbot cannot find an nginx `server_name` for the domain, add one first (HTTP on port 80 pointing at `.../public`), reload nginx, then run certbot again.

**Verify**

```bash
curl -I https://help.codikal.com/login
```

Browser should show a valid padlock (no “Not Secure”).

Renewal is automatic via `certbot renew` (systemd timer). Each custom domain gets its own certificate file under `/etc/letsencrypt/live/`.

**Cloudflare alternative:** If the customer puts their domain behind Cloudflare proxy (orange cloud), Cloudflare can terminate SSL at the edge. Your origin server still needs HTTPS or Cloudflare “Full” SSL mode with a valid origin cert.

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
| **502 Bad Gateway** | PHP-FPM not running, wrong socket, or `www-data` cannot read the app under `/home/ubuntu`. See [502 fix](#502-bad-gateway) below. |
| **File not found.** (plain text, not Laravel) | nginx `root` must end in `/public`, or PHP-FPM socket/path is wrong. See below. |
| Redirect loop | `APP_URL` must match the hostname users visit. |
| Session lost after login | Set `SESSION_SECURE_COOKIE=true` only when using HTTPS. |
| Mixed content / WS fails | `REALTIME_WS_URL` must be `wss://`, not `ws://`. |
| Tenant subdomain SSL error | Use `WILDCARD=true` or add each subdomain to the cert. |
| OAuth redirect mismatch | Update provider console to `https://` callback URLs. |
| `config:cache` stale | Run `php artisan config:clear && php artisan config:cache` after `.env` changes. |

### 502 Bad Gateway

nginx is up but cannot talk to PHP-FPM. On the server:

```bash
cd ~/helpefi-helpdesk   # or your app path

# 1. Find the PHP-FPM socket
ls -la /run/php/

# 2. Start PHP-FPM (use the version you installed)
sudo systemctl enable --now php8.5-fpm
sudo systemctl status php8.5-fpm

# 3. Check nginx points at the same socket
sudo grep fastcgi_pass /etc/nginx/sites-enabled/helpdesk

# 4. If socket is php8.5 but nginx says php8.4, fix and reload:
sudo sed -i 's|php8.4-fpm.sock|php8.5-fpm.sock|g' /etc/nginx/sites-available/helpdesk
sudo nginx -t && sudo systemctl reload nginx

# 5. Or re-run the SSL script (auto-detects socket):
sudo DOMAIN=helpefi.com EMAIL=you@helpefi.com WILDCARD=true \
  REALTIME_DOMAIN=rt.helpefi.com ./scripts/install-ssl-native.sh

# 6. Laravel writable dirs
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache

# 7. Read the real error
sudo tail -30 /var/log/nginx/error.log
sudo tail -30 /var/log/php8.5-fpm.log
```

Typical nginx error log lines:

- `connect() to unix:/run/php/php8.4-fpm.sock failed` → wrong PHP version in nginx config
- `No such file or directory` → PHP-FPM service not started
- `Primary script unknown` → wrong `root` path in nginx (must be `.../public`)

### App permissions (home directory)

If the app lives in `/home/ubuntu/...`, PHP-FPM (`www-data`) cannot enter that folder by default. On the server:

```bash
cd ~/helpefi-helpdesk

sudo chmod o+x /home/ubuntu
sudo chown -R ubuntu:www-data .
sudo find . -type d -exec chmod 775 {} \;
sudo find . -type f -exec chmod 664 {} \;
sudo chmod -R ug+rwx storage bootstrap/cache
sudo usermod -aG www-data ubuntu

php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo systemctl reload php8.5-fpm nginx
```

Run `php artisan` as **ubuntu**, not `www-data`. PHP-FPM only needs read access to the code plus write access to `storage/` and `bootstrap/cache/`.

For production long-term, prefer `/var/www/helpefi-helpdesk` instead of a home directory.

### Registration 500 / `SELECT command denied` on tenant tables

The app DB user needs access to central **and** all tenant databases (`tenant_*`):

```bash
sudo mysql <<'SQL'
GRANT ALL PRIVILEGES ON `helpdesk_central`.* TO 'helpdesk'@'localhost';
GRANT ALL PRIVILEGES ON `tenant_%`.* TO 'helpdesk'@'localhost';
GRANT CREATE ON *.* TO 'helpdesk'@'localhost';
FLUSH PRIVILEGES;
SQL
```

Replace `helpdesk` / `helpdesk_central` if your `.env` uses different names. Then retry registration.

### "File not found." (plain text)

PHP-FPM is running but cannot find `index.php`. On the server:

```bash
# Must exist:
ls -la ~/helpefi-helpdesk/public/index.php

# Check nginx root (must end in /public):
sudo grep -E 'root |fastcgi_pass|server_name' /etc/nginx/sites-enabled/helpdesk

# Fix if root is wrong (replace path if needed):
APP_ROOT=/home/ubuntu/helpefi-helpdesk
PHP_SOCK=/run/php/php8.5-fpm.sock

sudo tee /etc/nginx/sites-available/helpdesk >/dev/null <<EOF
server {
    listen 80;
    listen 443 ssl;
    server_name helpefi.com *.helpefi.com;

    root $APP_ROOT/public;
    index index.php;

    client_max_body_size 32M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        try_files \$uri =404;
        include fastcgi_params;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_pass unix:$PHP_SOCK;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

sudo nginx -t && sudo systemctl reload nginx
curl -I http://127.0.0.1 -H "Host: helpefi.com"
```

If you already have SSL certs from certbot, run `sudo certbot --nginx -d helpefi.com` after fixing the root path instead of overwriting the whole vhost.
