#!/usr/bin/env bash
# =============================================================================
# bootstrap-vps-http.sh — Setup automatizado da VPS via HTTP (sem SSH necessário)
#
# Uso (dentro da VPS, via console serial ou outro método):
#   curl -fsSL http://SEU_IP_OU_HOST:8888/bootstrap.sh | bash
#
# Ou se pre-instalado localmente:
#   bash bootstrap-vps-http.sh
# =============================================================================
set -euo pipefail

# Cores
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
log()  { echo -e "${CYAN}[GROM]${NC} $*"; }
ok()   { echo -e "${GREEN}[OK]${NC} $*"; }
warn() { echo -e "${YELLOW}[AVISO]${NC} $*"; }
die()  { echo -e "${RED}[ERRO]${NC} $*" >&2; exit 1; }

# Parametros com defaults seguros
DOMAIN="${GROM_DOMAIN:-grom.seg.br}"
CERTBOT_EMAIL="${GROM_CERTBOT_EMAIL:-admin@grom.seg.br}"
ADMIN_PASSWORD="${GROM_ADMIN_PASSWORD:-}"
REPO_DIR="${GROM_REPO_DIR:-/opt/grom/grom_web_php}"
BACKUP_DIR="${GROM_BACKUP_DIR:-/opt/grom/backups}"
SOURCE_BASE_URL="${GROM_SOURCE_BASE_URL:-}"
TAR_NAME="grom_deploy.tar.gz"

[[ $EUID -eq 0 ]] || die "Execute como root: sudo bash bootstrap-vps-http.sh"

INFRA_DIR="${REPO_DIR}/infra"
ENV_FILE="${INFRA_DIR}/.env.production"

log "======================================================="
log " GROM Web — Bootstrap VPS (HTTP)"
log " Dominio : ${DOMAIN}"
log " Repo    : ${REPO_DIR}"
log "======================================================="

# ---------------------------------------------------------------------------
# 0) Obter codigo-fonte via HTTP
# ---------------------------------------------------------------------------
[[ -n "${SOURCE_BASE_URL}" ]] || die "Defina GROM_SOURCE_BASE_URL (ex: http://X.X.X.X:8888)."

log "[0/11] Baixando codigo-fonte de ${SOURCE_BASE_URL}/${TAR_NAME}..."
mkdir -p "$(dirname "${REPO_DIR}")"
TMP_TAR="/tmp/${TAR_NAME}"
curl -fsSL "${SOURCE_BASE_URL}/${TAR_NAME}" -o "${TMP_TAR}"

rm -rf "${REPO_DIR}"
mkdir -p "${REPO_DIR}"
tar -xzf "${TMP_TAR}" -C "${REPO_DIR}"
rm -f "${TMP_TAR}"
ok "Codigo-fonte extraido em ${REPO_DIR}."

# ---------------------------------------------------------------------------
# 1) Pacotes base
# ---------------------------------------------------------------------------
log "[1/11] Instalando pacotes base..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq \
    ca-certificates curl gnupg lsb-release \
    nginx certbot python3-certbot-nginx \
    git ufw openssl cron

ok "Pacotes base instalados."

# ---------------------------------------------------------------------------
# 2) Docker Engine
# ---------------------------------------------------------------------------
if ! command -v docker &>/dev/null; then
    log "[2/11] Instalando Docker Engine..."
    curl -fsSL https://get.docker.com | sh
    ok "Docker instalado."
else
    ok "[2/11] Docker ja presente: $(docker --version)"
fi

if ! command -v docker &>/dev/null; then
    die "Docker nao foi instalado corretamente."
fi

# ---------------------------------------------------------------------------
# 3) Gerar segredos
# ---------------------------------------------------------------------------
log "[3/11] Gerando segredos de producao..."

APP_KEY="base64:$(openssl rand -base64 32)"
DB_PASSWORD="$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9!@#%^&' | head -c 28)"
REDIS_PASSWORD="$(openssl rand -base64 20 | tr -dc 'A-Za-z0-9' | head -c 20)"

if [[ -z "${ADMIN_PASSWORD}" ]]; then
    ADMIN_PASSWORD="$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9!@#%' | head -c 20)"
fi

ok "Segredos gerados."

# ---------------------------------------------------------------------------
# 4) Criar .env.production
# ---------------------------------------------------------------------------
log "[4/11] Criando ${ENV_FILE}..."

if [[ -f "${ENV_FILE}" ]]; then
    BACKUP_ENV="${ENV_FILE}.bak.$(date +%s)"
    warn ".env.production ja existe — backup em ${BACKUP_ENV}"
    cp "${ENV_FILE}" "${BACKUP_ENV}"
fi

sed \
    -e "s|APP_URL=\"https://grom.seg.br\"|APP_URL=\"https://${DOMAIN}\"|g" \
    -e "s|SESSION_DOMAIN=\"grom.seg.br\"|SESSION_DOMAIN=\"${DOMAIN}\"|g" \
    -e "s|MAIL_FROM_ADDRESS=\"nao-responder@grom.seg.br\"|MAIL_FROM_ADDRESS=\"nao-responder@${DOMAIN}\"|g" \
    -e "s|GROM_BOOTSTRAP_ADMIN_EMAIL=\"admin@grom.seg.br\"|GROM_BOOTSTRAP_ADMIN_EMAIL=\"admin@${DOMAIN}\"|g" \
    -e "s|APP_KEY=\"base64:GERAR_COM_PHP_ARTISAN_KEY_GENERATE_SHOW\"|APP_KEY=\"${APP_KEY}\"|g" \
    -e "s|DB_PASSWORD=\"ALTERAR_AQUI\"|DB_PASSWORD=\"${DB_PASSWORD}\"|g" \
    -e "s|REDIS_PASSWORD=\"\"|REDIS_PASSWORD=\"${REDIS_PASSWORD}\"|g" \
    -e "s|GROM_BOOTSTRAP_ADMIN_PASSWORD=\"ALTERAR_AQUI\"|GROM_BOOTSTRAP_ADMIN_PASSWORD=\"${ADMIN_PASSWORD}\"|g" \
    "${INFRA_DIR}/.env.production.example" > "${ENV_FILE}"

chmod 600 "${ENV_FILE}"
ok ".env.production criado."

# ---------------------------------------------------------------------------
# 5) Firewall
# ---------------------------------------------------------------------------
log "[5/11] Configurando UFW..."
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
ok "UFW configurado."

# ---------------------------------------------------------------------------
# 6) Backup + cron
# ---------------------------------------------------------------------------
log "[6/11] Configurando backup automatico..."
mkdir -p "${BACKUP_DIR}"
chmod 750 "${BACKUP_DIR}"

echo "0 3 * * * root bash ${INFRA_DIR}/scripts/backup-prod.sh >> /var/log/grom-backup.log 2>&1" > /etc/cron.d/grom-backup
chmod 644 /etc/cron.d/grom-backup
ok "Cron de backup (03h diario) configurado."

# ---------------------------------------------------------------------------
# 7) Nginx do host
# ---------------------------------------------------------------------------
log "[7/11] Configurando Nginx do host..."
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
cp "${INFRA_DIR}/nginx/grom.seg.br.conf.example" "/etc/nginx/sites-available/grom"
sed -i "s|grom.seg.br|${DOMAIN}|g" "/etc/nginx/sites-available/grom"
ln -sf "/etc/nginx/sites-available/grom" "/etc/nginx/sites-enabled/grom"
nginx -t
systemctl reload nginx
ok "Nginx configurado."

# ---------------------------------------------------------------------------
# 8) Certbot (TLS)
# ---------------------------------------------------------------------------
log "[8/11] Emitindo certificado TLS..."
certbot --nginx \
    --non-interactive \
    --agree-tos \
    --email "${CERTBOT_EMAIL}" \
    --domains "${DOMAIN}" \
    --redirect
systemctl enable --now certbot.timer 2>/dev/null || true
ok "TLS ativo."

# ---------------------------------------------------------------------------
# 9) Composer
# ---------------------------------------------------------------------------
log "[9/11] Instalando dependencias PHP..."
docker run --rm -v "${REPO_DIR}/runtime:/app" -w /app composer:2 \
    composer install --no-dev --optimize-autoloader --no-interaction
ok "Composer concluido."

# ---------------------------------------------------------------------------
# 10) Docker Compose
# ---------------------------------------------------------------------------
log "[10/11] Subindo stack Docker..."
cd "${INFRA_DIR}"
docker compose --env-file "${ENV_FILE}" -f docker-compose.prod.yml up -d --build

log "Aguardando PostgreSQL..."
for attempt in $(seq 1 40); do
    if docker compose --env-file "${ENV_FILE}" -f docker-compose.prod.yml exec -T postgres \
        sh -lc 'pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB"' >/dev/null 2>&1; then
        ok "PostgreSQL pronto."
        break
    fi
    if [[ ${attempt} -eq 40 ]]; then
        die "PostgreSQL nao ficou pronto."
    fi
    sleep 3
done

docker compose --env-file "${ENV_FILE}" -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose --env-file "${ENV_FILE}" -f docker-compose.prod.yml exec -T app php artisan db:seed --force
docker compose --env-file "${ENV_FILE}" -f docker-compose.prod.yml exec -T app php artisan optimize:clear
ok "Stack Docker ativo."

# ---------------------------------------------------------------------------
# 11) Validacao + credenciais
# ---------------------------------------------------------------------------
log "[11/11] Validando endpoints..."
sleep 3
HTTP_STATUS=$(curl -fsSo /dev/null -w '%{http_code}' "https://${DOMAIN}/login" 2>/dev/null || echo "000")
if [[ "${HTTP_STATUS}" == "200" ]]; then
    ok "https://${DOMAIN}/login respondeu 200."
else
    warn "https://${DOMAIN}/login respondeu ${HTTP_STATUS}."
fi

# Credenciais
CRED_FILE="/root/grom-credenciais.txt"
cat > "${CRED_FILE}" <<EOF
========================================================
GROM Web — Credenciais de Producao
Geradas em: $(date '+%Y-%m-%d %H:%M:%S')
========================================================

URL              : https://${DOMAIN}/login
Admin usuario    : admin
Admin senha      : ${ADMIN_PASSWORD}
Banco DB_PASSWORD: ${DB_PASSWORD}
Redis password   : ${REDIS_PASSWORD}
APP_KEY          : ${APP_KEY}

GUARDE ESTE ARQUIVO EM LOCAL SEGURO E EXCLUA DA VPS.
========================================================
EOF
chmod 600 "${CRED_FILE}"

echo ""
echo -e "${GREEN}======================================================="
echo " GROM Web implantado com sucesso!"
echo "=======================================================${NC}"
cat "${CRED_FILE}"
echo ""
warn "Credenciais salvas em ${CRED_FILE}"
