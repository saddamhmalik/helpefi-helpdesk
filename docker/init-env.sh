#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$ROOT/.env.docker"

if [ ! -f "$ENV_FILE" ]; then
    cp "$ROOT/.env.docker.example" "$ENV_FILE"
    echo "Created .env.docker"
fi

if ! grep -q '^APP_KEY=base64:' "$ENV_FILE"; then
    KEY="$(openssl rand -base64 32)"
    if grep -q '^APP_KEY=' "$ENV_FILE"; then
        if [[ "$OSTYPE" == darwin* ]]; then
            sed -i '' "s|^APP_KEY=.*|APP_KEY=base64:${KEY}|" "$ENV_FILE"
        else
            sed -i "s|^APP_KEY=.*|APP_KEY=base64:${KEY}|" "$ENV_FILE"
        fi
    else
        echo "APP_KEY=base64:${KEY}" >> "$ENV_FILE"
    fi
    echo "Generated APP_KEY in .env.docker"
fi

LOCAL_ENV="$ROOT/.env"

if [ -f "$LOCAL_ENV" ]; then
    STRIPE_VARS=(
        STRIPE_ENABLED
        STRIPE_KEY
        STRIPE_SECRET
        STRIPE_WEBHOOK_SECRET
        STRIPE_CURRENCY
        STRIPE_PRICE_STARTER
        STRIPE_PRICE_PROFESSIONAL
        STRIPE_PRICE_ENTERPRISE
        BILLING_DEFAULT_PLAN
        BILLING_TRIAL_DAYS
        BILLING_CURRENCY
    )

    TELESCOPE_VARS=(
        TELESCOPE_ENABLED
        TELESCOPE_DB_CONNECTION
        TELESCOPE_PATH
    )

    for var in "${STRIPE_VARS[@]}" "${TELESCOPE_VARS[@]}"; do
        value="$(grep -E "^${var}=" "$LOCAL_ENV" | tail -n 1 | cut -d= -f2- || true)"

        if [ -z "$value" ]; then
            continue
        fi

        if grep -q "^${var}=" "$ENV_FILE"; then
            if [[ "$OSTYPE" == darwin* ]]; then
                sed -i '' "s|^${var}=.*|${var}=${value}|" "$ENV_FILE"
            else
                sed -i "s|^${var}=.*|${var}=${value}|" "$ENV_FILE"
            fi
        else
            echo "${var}=${value}" >> "$ENV_FILE"
        fi
    done

    if grep -q '^STRIPE_SECRET=.' "$ENV_FILE" || grep -q '^TELESCOPE_ENABLED=true' "$ENV_FILE"; then
        echo "Synced Stripe and Telescope settings from .env into .env.docker"
    fi
fi
