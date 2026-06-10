#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

DOMAIN="${DOMAIN:-}"
EMAIL="${EMAIL:-}"
WILDCARD="${WILDCARD:-false}"
REALTIME_DOMAIN="${REALTIME_DOMAIN:-}"
ENV_FILE="${ENV_FILE:-.env}"
NGINX_SITE="${NGINX_SITE:-helpdesk}"
PHP_FPM_SOCK="${PHP_FPM_SOCK:-/run/php/php8.4-fpm.sock}"

usage() {
    cat <<'EOF'
Install Let's Encrypt SSL for helpefi (native / non-Docker).

Usage:
  DOMAIN=helpdesk.example.com EMAIL=admin@example.com ./scripts/install-ssl-native.sh

Optional:
  WILDCARD=true
  REALTIME_DOMAIN=rt.example.com
  ENV_FILE=.env
  NGINX_SITE=helpdesk
  PHP_FPM_SOCK=/run/php/php8.4-fpm.sock

Requires: Ubuntu/Debian with nginx, PHP 8.4-FPM, certbot installed.
Run ./scripts/install-native.sh first if the stack is not set up.

EOF
}

if [[ -z "$DOMAIN" || -z "$EMAIL" ]]; then
    usage
    exit 1
fi

if [[ $EUID -ne 0 ]]; then
    echo "Run as root (sudo)."
    exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Missing $ENV_FILE"
    exit 1
fi

if ! command -v certbot >/dev/null 2>&1; then
    apt-get update
    if ! apt-cache show certbot >/dev/null 2>&1; then
        apt-get install -y software-properties-common
    fi
    apt-get install -y certbot python3-certbot-nginx
fi

if [[ ! -S "$PHP_FPM_SOCK" ]]; then
    for candidate in /run/php/php8.5-fpm.sock /run/php/php8.4-fpm.sock /run/php/php8.3-fpm.sock; do
        if [[ -S "$candidate" ]]; then
            PHP_FPM_SOCK="$candidate"
            break
        fi
    done
fi

if [[ ! -S "$PHP_FPM_SOCK" ]]; then
    echo "ERROR: PHP-FPM socket not found at $PHP_FPM_SOCK"
    exit 1
fi

SITE_AVAILABLE="/etc/nginx/sites-available/$NGINX_SITE"
SITE_ENABLED="/etc/nginx/sites-enabled/$NGINX_SITE"

cat > "$SITE_AVAILABLE" <<NGINX
server {
    listen 80;
    server_name $DOMAIN${WILDCARD:+ *.$DOMAIN};

    root $ROOT/public;
    index index.php;

    client_max_body_size 32M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_pass unix:$PHP_FPM_SOCK;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

ln -sf "$SITE_AVAILABLE" "$SITE_ENABLED"
nginx -t
systemctl reload nginx

CERTBOT_DOMAINS=(-d "$DOMAIN")
if [[ "$WILDCARD" == "true" ]]; then
    CERTBOT_DOMAINS+=(-d "*.$DOMAIN")
    certbot certonly --manual --preferred-challenges dns \
        --email "$EMAIL" --agree-tos --no-eff-email \
        "${CERTBOT_DOMAINS[@]}"
else
    certbot --nginx --email "$EMAIL" --agree-tos --no-eff-email \
        --redirect "${CERTBOT_DOMAINS[@]}"
fi

if [[ -n "$REALTIME_DOMAIN" ]]; then
    REALTIME_SITE="/etc/nginx/sites-available/${NGINX_SITE}-realtime"
    cat > "$REALTIME_SITE" <<REALTIME
server {
    listen 80;
    server_name $REALTIME_DOMAIN;
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 86400;
    }
}
REALTIME
    ln -sf "$REALTIME_SITE" "/etc/nginx/sites-enabled/${NGINX_SITE}-realtime"
    certbot --nginx --email "$EMAIL" --agree-tos --no-eff-email \
        --redirect -d "$REALTIME_DOMAIN"
fi

REALTIME_WS_URL="wss://${REALTIME_DOMAIN:-$DOMAIN}"

update_env() {
    local key="$1"
    local value="$2"
    if grep -q "^${key}=" "$ENV_FILE"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
    else
        echo "${key}=${value}" >> "$ENV_FILE"
    fi
}

update_env "APP_URL" "https://$DOMAIN"
update_env "CENTRAL_APP_DOMAIN" "$DOMAIN"
update_env "SESSION_SECURE_COOKIE" "true"
update_env "REALTIME_WS_URL" "$REALTIME_WS_URL"
update_env "APP_ENV" "production"
update_env "APP_DEBUG" "false"

sudo -u "${SUDO_USER:-www-data}" php "$ROOT/artisan" config:clear
sudo -u "${SUDO_USER:-www-data}" php "$ROOT/artisan" config:cache

systemctl reload nginx

cat <<EOF

SSL installed (native).

App:      https://$DOMAIN
Realtime: $REALTIME_WS_URL
Renewal:  certbot renew (systemd timer)

Ensure queue + scheduler + realtime services are running:
  sudo systemctl status helpdesk-queue helpdesk-scheduler helpdesk-realtime

EOF
