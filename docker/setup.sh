#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

chmod +x docker/init-env.sh
./docker/init-env.sh

echo "Building images..."
docker compose build

echo "Installing PHP dependencies..."
docker compose run --rm --no-deps app composer install --no-interaction

echo "Installing Node dependencies and building assets..."
docker run --rm -v "$ROOT:/app" -w /app node:22-bookworm-slim sh -c "npm ci && npm run build"

echo "Starting stack..."
docker compose up -d

echo ""
echo "Done. Add to /etc/hosts:"
echo "  127.0.0.1 helpdesk.test"
echo ""
echo "Open: http://helpdesk.test:8081"
echo "Realtime WebSocket: ws://localhost:8080"
echo ""
echo "Run migrations manually if needed:"
echo "  docker compose run --rm migrate"
