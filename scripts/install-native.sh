#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

DOMAIN="${DOMAIN:-helpdesk.example.com}"
DB_NAME="${DB_NAME:-helpdesk_central}"
DB_USER="${DB_USER:-helpdesk}"
DB_PASS="${DB_PASS:-}"
APP_USER="${APP_USER:-www-data}"
PHP_VERSION="${PHP_VERSION:-}"

usage() {
    cat <<'EOF'
Install helpefi on Ubuntu/Debian without Docker.

Usage:
  sudo DOMAIN=helpdesk.example.com DB_PASS='strong-secret' ./scripts/install-native.sh

Optional:
  DB_NAME=helpdesk_central
  DB_USER=helpdesk
  APP_USER=www-data
  PHP_VERSION=8.4   (auto-detected: 8.5, 8.4, or 8.3)

Recommended OS: Ubuntu 24.04 LTS. Ubuntu Resolute uses distro PHP 8.5.

After install:
  1. Copy/edit .env (APP_KEY is generated)
  2. Run ./scripts/install-ssl-native.sh for HTTPS
  3. Point DNS A record to this server

EOF
}

if [[ $EUID -ne 0 ]]; then
    echo "Run as root (sudo)."
    exit 1
fi

if [[ -z "$DB_PASS" ]]; then
    DB_PASS="$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)"
    echo "Generated DB_PASS=$DB_PASS"
fi

export DEBIAN_FRONTEND=noninteractive

php_packages_available() {
    apt-cache show "php${PHP_VERSION}-fpm" >/dev/null 2>&1
}

resolve_php_version() {
    local version
    local candidates=()

    if [[ -n "${PHP_VERSION}" ]]; then
        candidates+=("${PHP_VERSION}")
    fi

    candidates+=(8.5 8.4 8.3)

    for version in "${candidates[@]}"; do
        if apt-cache show "php${version}-fpm" >/dev/null 2>&1; then
            PHP_VERSION="$version"
            echo "Using PHP ${PHP_VERSION}"
            return 0
        fi
    done

    return 1
}

ensure_php_packages() {
    if resolve_php_version; then
        return
    fi

    CODENAME="$(. /etc/os-release && echo "${VERSION_CODENAME}")"

    case "$CODENAME" in
        jammy|noble|focal)
            echo "PHP not found in default repos — adding ppa:ondrej/php for ${CODENAME}..."
            apt-get install -y software-properties-common ca-certificates lsb-release apt-transport-https
            add-apt-repository -y ppa:ondrej/php
            apt-get update
            ;;
        *)
            echo "Non-LTS Ubuntu (${CODENAME}): ppa:ondrej/php is not supported."
            echo "Install distro PHP packages manually, e.g. php8.5-fpm php8.5-cli ..."
            ;;
    esac

    if ! resolve_php_version; then
        echo "ERROR: No supported PHP-FPM package found (need 8.3+)."
        echo "On Ubuntu Resolute+, try: sudo apt install php8.5-fpm php8.5-cli ..."
        echo "Or use Ubuntu 24.04 LTS for production."
        exit 1
    fi
}

ensure_php_cli_symlink() {
    if command -v php >/dev/null 2>&1; then
        return
    fi

    if [[ -x "/usr/bin/php${PHP_VERSION}" ]]; then
        update-alternatives --install /usr/bin/php "php" "/usr/bin/php${PHP_VERSION}" 100 >/dev/null 2>&1 || \
            ln -sf "/usr/bin/php${PHP_VERSION}" /usr/bin/php
    fi
}

apt-get update
ensure_php_packages

apt-get install -y \
    nginx \
    mysql-server \
    redis-server \
    git \
    unzip \
    curl \
    supervisor \
    "php${PHP_VERSION}-fpm" \
    "php${PHP_VERSION}-cli" \
    "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-redis" \
    "php${PHP_VERSION}-zip" \
    "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-intl" \
    "php${PHP_VERSION}-bcmath" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-opcache" \
    "php${PHP_VERSION}-pcntl"

ensure_php_cli_symlink

if ! command -v php >/dev/null 2>&1; then
    echo "ERROR: php CLI not available after installing php${PHP_VERSION}-cli"
    exit 1
fi

php -v

if ! command -v composer >/dev/null 2>&1; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

if ! command -v node >/dev/null 2>&1; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y nodejs
fi

mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
mysql -e "GRANT CREATE ON *.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

DEPLOY_USER="${SUDO_USER:-$APP_USER}"
chown -R "$DEPLOY_USER:$DEPLOY_USER" "$ROOT"

sudo -u "$DEPLOY_USER" composer install --no-dev --optimize-autoloader --no-interaction -d "$ROOT"
sudo -u "$DEPLOY_USER" npm ci --prefix "$ROOT"
sudo -u "$DEPLOY_USER" npm run build --prefix "$ROOT"

if [[ ! -f "$ROOT/.env" ]]; then
    cp "$ROOT/.env.example" "$ROOT/.env"
fi

APP_KEY="$(sudo -u "$DEPLOY_USER" php "$ROOT/artisan" key:generate --show)"

set_env() {
    local key="$1"
    local value="$2"
    if grep -q "^${key}=" "$ROOT/.env"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$ROOT/.env"
    else
        echo "${key}=${value}" >> "$ROOT/.env"
    fi
}

set_env "APP_ENV" "production"
set_env "APP_DEBUG" "false"
set_env "APP_URL" "http://$DOMAIN"
set_env "APP_KEY" "$APP_KEY"
set_env "DB_CONNECTION" "central"
set_env "DB_HOST" "127.0.0.1"
set_env "DB_PORT" "3306"
set_env "DB_DATABASE" "$DB_NAME"
set_env "DB_USERNAME" "$DB_USER"
set_env "DB_PASSWORD" "$DB_PASS"
set_env "CENTRAL_APP_DOMAIN" "$DOMAIN"
set_env "REDIS_HOST" "127.0.0.1"
set_env "QUEUE_CONNECTION" "redis"
set_env "CACHE_STORE" "redis"
set_env "REALTIME_WS_HOST" "127.0.0.1"
set_env "REALTIME_WS_PORT" "8080"
set_env "REALTIME_WS_URL" "ws://127.0.0.1:8080"

sudo -u "$DEPLOY_USER" php "$ROOT/artisan" migrate --force
sudo -u "$DEPLOY_USER" php "$ROOT/artisan" db:seed --class=Database\\Seeders\\CentralDatabaseSeeder --force
sudo -u "$DEPLOY_USER" php "$ROOT/artisan" tenants:migrate --force || true
sudo -u "$DEPLOY_USER" php "$ROOT/artisan" storage:link --force
sudo -u "$DEPLOY_USER" php "$ROOT/artisan" config:cache
sudo -u "$DEPLOY_USER" php "$ROOT/artisan" route:cache
sudo -u "$DEPLOY_USER" php "$ROOT/artisan" view:cache

chown -R "$APP_USER:$APP_USER" "$ROOT/storage" "$ROOT/bootstrap/cache"

cat > /etc/nginx/sites-available/helpdesk <<NGINX
server {
    listen 80;
    server_name $DOMAIN;

    root $ROOT/public;
    index index.php;

    client_max_body_size 32M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

ln -sf /etc/nginx/sites-available/helpdesk /etc/nginx/sites-enabled/helpdesk
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now nginx "php${PHP_VERSION}-fpm" redis-server mysql

cat > /etc/supervisor/conf.d/helpdesk.conf <<SUP
[program:helpdesk-queue]
process_name=%(program_name)s
command=php $ROOT/artisan queue:work redis --sleep=3 --tries=3 --timeout=900
autostart=true
autorestart=true
user=$APP_USER
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/helpdesk-queue.log

[program:helpdesk-scheduler]
process_name=%(program_name)s
command=/bin/bash -c "while true; do php $ROOT/artisan schedule:run --no-interaction; sleep 30; done"
autostart=true
autorestart=true
user=$APP_USER
redirect_stderr=true
stdout_logfile=/var/log/helpdesk-scheduler.log

[program:helpdesk-realtime]
process_name=%(program_name)s
directory=$ROOT
command=/usr/bin/node realtime/server.mjs
environment=REALTIME_WS_HOST="127.0.0.1",REALTIME_WS_PORT="8080",REDIS_HOST="127.0.0.1"
autostart=true
autorestart=true
user=$APP_USER
redirect_stderr=true
stdout_logfile=/var/log/helpdesk-realtime.log
SUP

supervisorctl reread
supervisorctl update

systemctl reload nginx

cat <<EOF

Native install complete.

PHP:   ${PHP_VERSION}
HTTP:  http://$DOMAIN  (add DNS A record first)
DB:    $DB_NAME / user $DB_USER

Next:
  1. DOMAIN=$DOMAIN EMAIL=you@example.com ./scripts/install-ssl-native.sh
  2. Set mail, Stripe, OAuth keys in .env
  3. For tenant subdomains (acme.$DOMAIN), use a wildcard SSL cert

Logs:
  tail -f /var/log/helpdesk-queue.log
  tail -f /var/log/helpdesk-realtime.log

EOF
