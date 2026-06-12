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
    RAZORPAY_VARS=(
        RAZORPAY_ENABLED
        RAZORPAY_KEY
        RAZORPAY_SECRET
        RAZORPAY_WEBHOOK_SECRET
        RAZORPAY_CURRENCY
        RAZORPAY_PLAN_STARTER
        RAZORPAY_PLAN_PROFESSIONAL
        RAZORPAY_PLAN_ENTERPRISE
        RAZORPAY_PLAN_STARTER_YEARLY
        RAZORPAY_PLAN_PROFESSIONAL_YEARLY
        RAZORPAY_PLAN_ENTERPRISE_YEARLY
        BILLING_DEFAULT_PLAN
        BILLING_TRIAL_DAYS
        BILLING_CURRENCY
    )

    TELESCOPE_VARS=(
        TELESCOPE_ENABLED
        TELESCOPE_DB_CONNECTION
        TELESCOPE_PATH
    )

    for var in "${RAZORPAY_VARS[@]}" "${TELESCOPE_VARS[@]}"; do
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

    if grep -q '^RAZORPAY_SECRET=.' "$ENV_FILE" || grep -q '^TELESCOPE_ENABLED=true' "$ENV_FILE"; then
        echo "Synced Razorpay and Telescope settings from .env into .env.docker"
    fi
fi
