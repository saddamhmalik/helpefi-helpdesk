#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

DOMAIN="${DOMAIN:-}"
EMAIL="${EMAIL:-}"
WILDCARD="${WILDCARD:-false}"
REALTIME_DOMAIN="${REALTIME_DOMAIN:-}"
ENV_FILE="${ENV_FILE:-.env.docker}"
COMPOSE_FILES="-f docker-compose.prod.yml -f docker-compose.ssl.yml"

usage() {
    cat <<'EOF'
Install Let's Encrypt SSL for Helpdesk (Docker production).

Usage:
  DOMAIN=helpdesk.example.com EMAIL=admin@example.com ./scripts/install-ssl-docker.sh

Optional:
  WILDCARD=true                    Request *.DOMAIN (DNS challenge — manual step)
  REALTIME_DOMAIN=rt.example.com   Separate subdomain for WebSocket (wss://)
  ENV_FILE=.env.docker             Env file to update (default: .env.docker)

Prerequisites:
  1. DNS A record: DOMAIN -> server public IP
  2. If WILDCARD=true: DNS TXT record when certbot prompts
  3. Ports 80 and 443 open on the server
  4. Production stack built: docker compose -f docker-compose.prod.yml build

Required .env changes (applied automatically when possible):
  APP_URL=https://DOMAIN
  CENTRAL_APP_DOMAIN=DOMAIN
  SESSION_SECURE_COOKIE=true
  REALTIME_WS_URL=wss://REALTIME_DOMAIN  (or wss://DOMAIN if no separate subdomain)

EOF
}

if [[ -z "$DOMAIN" || -z "$EMAIL" ]]; then
    usage
    exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Missing $ENV_FILE — copy .env.docker.example first."
    exit 1
fi

GENERATED_DIR="$ROOT/docker/nginx/generated"
mkdir -p "$GENERATED_DIR"
CERT_NAME="$DOMAIN"
WILDCARD_DOMAIN=""

if [[ "$WILDCARD" == "true" ]]; then
    CERT_NAME="$DOMAIN"
    WILDCARD_DOMAIN="*.$DOMAIN"
fi

render_template() {
    local template="$1"
    local output="$2"
    sed \
        -e "s|__DOMAIN__|$DOMAIN|g" \
        -e "s|__WILDCARD_DOMAIN__|$WILDCARD_DOMAIN|g" \
        -e "s|__CERT_NAME__|$CERT_NAME|g" \
        -e "s|__REALTIME_DOMAIN__|${REALTIME_DOMAIN:-}|g" \
        -e "s|__REALTIME_CERT_NAME__|${REALTIME_DOMAIN:-$DOMAIN}|g" \
        "$template" > "$output"
}

echo "==> Starting HTTP-only nginx for ACME challenge..."
cp "$ROOT/docker/nginx/acme-http.conf" "$GENERATED_DIR/default.conf"
docker compose $COMPOSE_FILES up -d nginx

echo "==> Requesting certificate..."
CERTBOT_ARGS=(certonly --webroot -w /var/www/certbot --email "$EMAIL" --agree-tos --no-eff-email)

if [[ "$WILDCARD" == "true" ]]; then
    echo "Wildcard certificates require DNS validation."
    docker compose $COMPOSE_FILES --profile ssl run --rm certbot \
        certonly --manual --preferred-challenges dns \
        --email "$EMAIL" --agree-tos --no-eff-email \
        -d "$DOMAIN" -d "*.$DOMAIN"
else
    docker compose $COMPOSE_FILES --profile ssl run --rm certbot \
        "${CERTBOT_ARGS[@]}" -d "$DOMAIN"
fi

if [[ -n "$REALTIME_DOMAIN" && "$REALTIME_DOMAIN" != "$DOMAIN" ]]; then
    echo "==> Requesting realtime certificate for $REALTIME_DOMAIN..."
    docker compose $COMPOSE_FILES --profile ssl run --rm certbot \
        "${CERTBOT_ARGS[@]}" -d "$REALTIME_DOMAIN"
fi

echo "==> Rendering HTTPS nginx config..."
render_template "$ROOT/docker/nginx/ssl.conf.template" "$GENERATED_DIR/default.conf"

if [[ -n "$REALTIME_DOMAIN" ]]; then
    render_template "$ROOT/docker/nginx/realtime-ssl.conf.template" "$GENERATED_DIR/realtime.conf"
fi

docker compose $COMPOSE_FILES restart nginx

REALTIME_WS_URL="wss://${REALTIME_DOMAIN:-$DOMAIN}"
if [[ -n "$REALTIME_DOMAIN" ]]; then
    REALTIME_WS_URL="wss://$REALTIME_DOMAIN"
fi

update_env() {
    local key="$1"
    local value="$2"
    if grep -q "^${key}=" "$ENV_FILE"; then
        sed -i.bak "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
    else
        echo "${key}=${value}" >> "$ENV_FILE"
    fi
}

echo "==> Updating $ENV_FILE..."
update_env "APP_URL" "https://$DOMAIN"
update_env "CENTRAL_APP_DOMAIN" "$DOMAIN"
update_env "SESSION_SECURE_COOKIE" "true"
update_env "REALTIME_WS_URL" "$REALTIME_WS_URL"
update_env "APP_ENV" "production"
update_env "APP_DEBUG" "false"

docker compose $COMPOSE_FILES up -d --profile ssl
docker compose $COMPOSE_FILES exec app php artisan config:clear
docker compose $COMPOSE_FILES exec app php artisan config:cache

cat <<EOF

SSL installed.

App:      https://$DOMAIN
Realtime: $REALTIME_WS_URL
Certs:    docker volume certbot_certs (renewed every 12h by certbot service)

Tenant workspaces use subdomains like acme.$DOMAIN — use WILDCARD=true for a single cert.

Next steps:
  1. Point tenant DNS: CNAME *.tenants.$DOMAIN or per-workspace records
  2. Update Stripe/OAuth redirect URLs to https://
  3. Rebuild if you change REALTIME_WS_URL after npm build is baked in image

EOF
