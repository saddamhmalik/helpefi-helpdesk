#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CUSTOM_DOMAIN="${CUSTOM_DOMAIN:-}"
EMAIL="${EMAIL:-}"
NGINX_SITE="${NGINX_SITE:-helpdesk}"
PHP_FPM_SOCK="${PHP_FPM_SOCK:-}"

usage() {
    cat <<'EOF'
Issue Let's Encrypt SSL for a tenant custom domain (native nginx).

The custom domain must already CNAME to tenants.YOUR_PLATFORM_DOMAIN and
resolve to this server. DNS verification in helpefi must be complete first.

Usage:
  sudo CUSTOM_DOMAIN=help.customer.com EMAIL=admin@example.com ./scripts/issue-custom-domain-ssl.sh

Optional:
  NGINX_SITE=helpdesk
  PHP_FPM_SOCK=/run/php/php8.5-fpm.sock

EOF
}

if [[ -z "$CUSTOM_DOMAIN" || -z "$EMAIL" ]]; then
    usage
    exit 1
fi

if [[ $EUID -ne 0 ]]; then
    echo "Run as root (sudo)."
    exit 1
fi

if ! command -v certbot >/dev/null 2>&1; then
    apt-get update
    apt-get install -y certbot python3-certbot-nginx
fi

if [[ -z "$PHP_FPM_SOCK" ]]; then
    for candidate in /run/php/php8.5-fpm.sock /run/php/php8.4-fpm.sock /run/php/php8.3-fpm.sock; do
        if [[ -S "$candidate" ]]; then
            PHP_FPM_SOCK="$candidate"
            break
        fi
    done
fi

if [[ -z "$PHP_FPM_SOCK" || ! -S "$PHP_FPM_SOCK" ]]; then
    echo "ERROR: PHP-FPM socket not found."
    exit 1
fi

SITE_AVAILABLE="/etc/nginx/sites-available/${NGINX_SITE}-custom-${CUSTOM_DOMAIN//./-}"
SITE_ENABLED="/etc/nginx/sites-enabled/${NGINX_SITE}-custom-${CUSTOM_DOMAIN//./-}"

cat > "$SITE_AVAILABLE" <<NGINX
server {
    listen 80;
    server_name $CUSTOM_DOMAIN;

    root $ROOT/public;
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

certbot --nginx --email "$EMAIL" --agree-tos --no-eff-email \
    --redirect -d "$CUSTOM_DOMAIN"

systemctl reload nginx

cat <<EOF

SSL installed for custom domain: https://$CUSTOM_DOMAIN

Certificate renews automatically via: certbot renew

Add more custom domains by running this script again with a different CUSTOM_DOMAIN.

EOF
