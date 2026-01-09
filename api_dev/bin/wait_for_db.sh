#!/usr/bin/env bash

# Tempo máximo de espera (em segundos)
MAX_RETRIES=${MAX_RETRIES:-30}
RETRY_INTERVAL=${RETRY_INTERVAL:-2}

HOST="${API_DEV_MYSQL_HOST:-mysql}"
USER="${API_DEV_MYSQL_USER:-root}"
PASSWORD="${API_DEV_MYSQL_PASSWORD:-tent}"
PORT="${API_DEV_MYSQL_PORT:-3306}"

echo "Waiting for MySQL at $HOST:$PORT ..."

for ((i=1; i<=MAX_RETRIES; i++)); do
  if mysql -h "$HOST" -P "$PORT" -u "$USER" -p"$PASSWORD" -e "SELECT 1;" 2>/dev/null; then
    echo "MySQL is up!"
    exit 0
  fi
  sleep "$RETRY_INTERVAL"
done

echo "Database inaccessible after $MAX_RETRIES attempts."
exit 1
