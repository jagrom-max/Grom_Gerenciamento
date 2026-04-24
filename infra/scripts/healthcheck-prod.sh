#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INFRA_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
COMPOSE_FILE="${INFRA_DIR}/docker-compose.prod.yml"
ENV_FILE="${INFRA_DIR}/.env.production"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "Arquivo .env.production nao encontrado em ${INFRA_DIR}" >&2
  exit 1
fi

cd "${INFRA_DIR}"

echo "== docker compose ps =="
docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" ps

echo
echo "== HTTP interno =="
curl -fsSI http://127.0.0.1:8080/login

echo
echo "== HTTPS publico =="
curl -fsSI https://grom.seg.br/login

echo
echo "== logs app =="
docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" logs --tail=40 app

echo
echo "== logs worker =="
docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" logs --tail=20 worker

echo
echo "== logs scheduler =="
docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" logs --tail=20 scheduler