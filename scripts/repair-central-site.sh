#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DOMAIN="${DOMAIN:-helpefi.com}"
NGINX_SITE="${NGINX_SITE:-helpdesk}"
PHP_FPM_SOCK="${PHP_FPM_SOCK:-}"
DEPLOY_USER="${SUDO_USER:-ubuntu}"

if [[ $EUID -ne 0 ]]; then
    echo "Run as root (sudo)."
    exit 1
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
    echo "ERROR: PHP-FPM socket not found. Start FPM first."
    exit 1
fi

CERT_DIR="/etc/letsencrypt/live/${DOMAIN}"
HAS_CERT=false
if [[ -f "${CERT_DIR}/fullchain.pem" && -f "${CERT_DIR}/privkey.pem" ]]; then
    HAS_CERT=true
fi

SITE_AVAILABLE="/etc/nginx/sites-available/${NGINX_SITE}"

if [[ "$HAS_CERT" == "true" ]]; then
    cat > "$SITE_AVAILABLE" <<NGINX
server {
    listen 80;
    listen 443 ssl;
    server_name ${DOMAIN} *.${DOMAIN};

    ssl_certificate ${CERT_DIR}/fullchain.pem;
    ssl_certificate_key ${CERT_DIR}/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    root ${ROOT}/public;
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
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
else
    cat > "$SITE_AVAILABLE" <<NGINX
server {
    listen 80;
    server_name ${DOMAIN} *.${DOMAIN};

    root ${ROOT}/public;
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
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
fi

WWW_SITE="/etc/nginx/sites-available/${NGINX_SITE}-www-redirect"
if [[ "$HAS_CERT" == "true" ]]; then
    cat > "$WWW_SITE" <<NGINX
server {
    listen 80;
    listen 443 ssl;
    server_name www.${DOMAIN};

    ssl_certificate ${CERT_DIR}/fullchain.pem;
    ssl_certificate_key ${CERT_DIR}/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    return 301 https://${DOMAIN}\$request_uri;
}
NGINX
else
    cat > "$WWW_SITE" <<NGINX
server {
    listen 80;
    server_name www.${DOMAIN};
    return 301 http://${DOMAIN}\$request_uri;
}
NGINX
fi

ln -sf "$WWW_SITE" "/etc/nginx/sites-enabled/${NGINX_SITE}-www-redirect"

ln -sf "$SITE_AVAILABLE" "/etc/nginx/sites-enabled/${NGINX_SITE}"
nginx -t
systemctl reload nginx

cd "$ROOT"

ENV_FILE="$ROOT/.env"
ENV_OK=true

if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: Missing .env at ${ENV_FILE}"
    ENV_OK=false
fi

for required in "CENTRAL_APP_DOMAIN=${DOMAIN}" "DB_CONNECTION=central" "DB_DRIVER=mysql" "CENTRAL_DB_DRIVER=mysql"; do
    if ! grep -q "^${required}\$" "$ENV_FILE" 2>/dev/null; then
        echo "WARNING: .env should contain ${required}"
        ENV_OK=false
    fi
done

if [[ "$ENV_OK" != "true" ]]; then
    echo "Fix .env before caching config or central ${DOMAIN} will return 500."
fi

sudo -u "$DEPLOY_USER" php artisan config:clear
sudo -u "$DEPLOY_USER" php artisan route:clear
sudo -u "$DEPLOY_USER" php artisan view:clear

if [[ "$ENV_OK" == "true" ]]; then
    sudo -u "$DEPLOY_USER" php artisan config:cache
    sudo -u "$DEPLOY_USER" php artisan route:cache
else
    echo "Skipped config:cache and route:cache until .env is fixed."
fi

if [[ -f "$ROOT/bootstrap/cache/config.php" ]]; then
    if grep -q "'driver' => 'sqlite'" "$ROOT/bootstrap/cache/config.php" 2>/dev/null; then
        echo "ERROR: Cached config still uses sqlite for central DB."
        echo "       Run: php artisan config:clear"
    fi
fi

if [[ -f "$ROOT/artisan" ]]; then
    sudo -u "$DEPLOY_USER" php artisan platform:diagnose-central "$DOMAIN" 2>/dev/null || true
fi

echo ""
echo "Repaired nginx site: ${NGINX_SITE}"
echo "PHP-FPM socket: ${PHP_FPM_SOCK}"
echo "SSL cert: $([[ "$HAS_CERT" == "true" ]] && echo yes || echo no — run install-ssl-native.sh)"
echo ""
echo "Check app:"
echo "  curl -I http://127.0.0.1 -H 'Host: ${DOMAIN}'"
echo "  tail -30 ${ROOT}/storage/logs/laravel.log"
