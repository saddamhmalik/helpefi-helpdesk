#!/bin/sh
set -e

cd "$(dirname "$0")/.."

docker compose restart app queue scheduler

echo "PHP workers restarted (clears stale opcache in dev)."
