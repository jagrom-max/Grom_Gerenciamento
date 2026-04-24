#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INFRA_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
COMPOSE_FILE="${INFRA_DIR}/docker-compose.prod.yml"
ENV_FILE="${INFRA_DIR}/.env.production"
BACKUP_DIR="${BACKUP_DIR:-/opt/grom/backups}"
TIMESTAMP="$(date +%F-%H%M%S)"
TARGET_FILE="${BACKUP_DIR}/grom_web_${TIMESTAMP}.sql"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "Arquivo .env.production nao encontrado em ${INFRA_DIR}" >&2
  exit 1
fi

mkdir -p "${BACKUP_DIR}"
cd "${INFRA_DIR}"

docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" exec -T postgres sh -lc 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB"' > "${TARGET_FILE}"

echo "Backup salvo em ${TARGET_FILE}"