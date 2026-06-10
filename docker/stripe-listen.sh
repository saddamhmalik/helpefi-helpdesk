#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$ROOT/.env.docker"

if [ ! -f "$ENV_FILE" ]; then
    echo "Missing .env.docker. Run ./docker/init-env.sh first."
    exit 1
fi

if ! command -v stripe >/dev/null 2>&1; then
    echo "Install the Stripe CLI: https://stripe.com/docs/stripe-cli"
    exit 1
fi

WEBHOOK_URL="${STRIPE_WEBHOOK_URL:-http://helpdesk.test:8081/stripe/webhook}"
STRIPE_SECRET="$(grep '^STRIPE_SECRET=' "$ENV_FILE" | tail -n 1 | cut -d= -f2- || true)"

if [ -z "$STRIPE_SECRET" ]; then
    echo "STRIPE_SECRET is missing from .env.docker. Run ./docker/init-env.sh first."
    exit 1
fi

if [ "${1:-}" = "--setup" ]; then
    SECRET="$(stripe listen --print-secret --api-key "$STRIPE_SECRET")"

    if grep -q '^STRIPE_WEBHOOK_SECRET=' "$ENV_FILE"; then
        if [[ "$OSTYPE" == darwin* ]]; then
            sed -i '' "s|^STRIPE_WEBHOOK_SECRET=.*|STRIPE_WEBHOOK_SECRET=${SECRET}|" "$ENV_FILE"
        else
            sed -i "s|^STRIPE_WEBHOOK_SECRET=.*|STRIPE_WEBHOOK_SECRET=${SECRET}|" "$ENV_FILE"
        fi
    else
        echo "STRIPE_WEBHOOK_SECRET=${SECRET}" >> "$ENV_FILE"
    fi

    echo "Saved STRIPE_WEBHOOK_SECRET to .env.docker"
    echo "Restart Docker: docker compose up -d --force-recreate app queue scheduler nginx"
    exit 0
fi

echo "Forwarding Stripe webhooks to ${WEBHOOK_URL}"
echo "Tip: run './docker/stripe-listen.sh --setup' once to save the signing secret to .env.docker"
echo ""

stripe listen --forward-to "${WEBHOOK_URL}" --api-key "$STRIPE_SECRET"
