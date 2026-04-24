#!/usr/bin/env bash
# =============================================================================
# deploy-prod.sh — Atualiza a aplicacao em producao (git pull + rebuild)
#
# Use este script para ATUALIZACOES apos a hospedagem inicial.
# Para o primeiro deploy em uma VPS zerada, use: bootstrap-vps.sh
#
# Uso:
#   cd /opt/grom/grom_web_php && git pull
#   bash infra/scripts/deploy-prod.sh
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INFRA_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
COMPOSE_FILE="${INFRA_DIR}/docker-compose.prod.yml"
ENV_FILE="${INFRA_DIR}/.env.production"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "Arquivo .env.production nao encontrado em ${INFRA_DIR}" >&2
  exit 1
fi

command -v docker >/dev/null 2>&1 || { echo "docker nao encontrado" >&2; exit 1; }

cd "${INFRA_DIR}"

echo "[1/5] Subindo stack de producao"
docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" up -d --build

echo "[2/5] Aguardando PostgreSQL responder"
for attempt in {1..30}; do
  if docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" exec -T postgres sh -lc 'pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB"' >/dev/null 2>&1; then
    break
  fi

  if [[ "${attempt}" == "30" ]]; then
    echo "PostgreSQL nao ficou pronto no tempo esperado" >&2
    exit 1
  fi

  sleep 2
done

echo "[3/5] Aplicando migrations"
docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" exec -T app php artisan migrate --force

echo "[4/5] Aplicando seed estrutural"
docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" exec -T app php artisan db:seed --force

echo "[5/5] Limpando caches e validando login"
docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" exec -T app php artisan optimize:clear
curl -fsSI http://127.0.0.1:8080/login >/dev/null

echo "Deploy concluido com sucesso"