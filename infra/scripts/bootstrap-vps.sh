#!/usr/bin/env bash
# =============================================================================
# bootstrap-vps.sh — Setup completo e automatizado do GROM Web na VPS
#
# Uso:
#   bash bootstrap-vps.sh [DOMINIO] [EMAIL_CERTBOT] [ADMIN_PASSWORD]
#
# Se omitir os parametros, os valores padroes sao usados.
# Senhas de banco e APP_KEY sao SEMPRE geradas automaticamente.
# =============================================================================
set -euo pipefail

# ---------------------------------------------------------------------------
# Parametros (podem ser passados como variaveis de ambiente ou argumentos)
# ---------------------------------------------------------------------------
DOMAIN="${1:-${GROM_DOMAIN:-grom.seg.br}}"
CERTBOT_EMAIL="${2:-${GROM_CERTBOT_EMAIL:-admin@grom.seg.br}}"
ADMIN_PASSWORD="${3:-${GROM_ADMIN_PASSWORD:-}}"
REPO_DIR="${GROM_REPO_DIR:-/opt/grom/grom_web_php}"
BACKUP_DIR="${GROM_BACKUP_DIR:-/opt/grom/backups}"

# ---------------------------------------------------------------------------
# Cores
# ---------------------------------------------------------------------------
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
log()  { echo -e "${CYAN}[GROM]${NC} $*"; }
ok()   { echo -e "${GREEN}[OK]${NC} $*"; }
warn() { echo -e "${YELLOW}[AVISO]${NC} $*"; }
die()  { echo -e "${RED}[ERRO]${NC} $*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# Verificacoes iniciais
# ---------------------------------------------------------------------------
[[ $EUID -eq 0 ]] || die "Execute como root: sudo bash bootstrap-vps.sh"
[[ -d "${REPO_DIR}" ]] || die "Repositorio nao encontrado em ${REPO_DIR}. Execute o Deploy-ToVPS.ps1 primeiro."

INFRA_DIR="${REPO_DIR}/infra"
RUNTIME_DIR="${REPO_DIR}/runtime"
ENV_FILE="${INFRA_DIR}/.env.production"

log "======================================================="
log " GROM Web — Bootstrap VPS"
log " Dominio : ${DOMAIN}"
log " Repo    : ${REPO_DIR}"
log "======================================================="

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
    log "[2/10] Instalando Docker Engine..."
    curl -fsSL https://get.docker.com | sh
    ok "Docker instalado."
else
    ok "[2/10] Docker ja presente: $(docker --version)"
fi

if ! command -v docker &>/dev/null; then
    die "Docker nao foi instalado corretamente."
fi

# ---------------------------------------------------------------------------
# 3) Gerar segredos
# ---------------------------------------------------------------------------
log "[3/10] Gerando segredos de producao..."

# APP_KEY no formato que Laravel espera: base64:<32 bytes aleatorios em base64>
APP_KEY="base64:$(openssl rand -base64 32)"

DB_PASSWORD="$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9!@#%^&' | head -c 28)"
REDIS_PASSWORD="$(openssl rand -base64 20 | tr -dc 'A-Za-z0-9' | head -c 20)"

if [[ -z "${ADMIN_PASSWORD}" ]]; then
    ADMIN_PASSWORD="$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9!@#%' | head -c 20)"
    ADMIN_PASSWORD_GENERATED=true
else
    ADMIN_PASSWORD_GENERATED=false
fi

ok "Segredos gerados."

# ---------------------------------------------------------------------------
# 4) Criar .env.production
# ---------------------------------------------------------------------------
log "[4/10] Criando ${ENV_FILE}..."

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
ok ".env.production criado com permissoes restritas (600)."

# Atualizar redis no compose para usar senha
# (redis-server --requirepass foi adicionado no step de compose abaixo)

# ---------------------------------------------------------------------------
# 5) Firewall
# ---------------------------------------------------------------------------
log "[5/10] Configurando UFW..."
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
ok "UFW configurado: SSH + 80 + 443."

# ---------------------------------------------------------------------------
# 6) Diretorio de backup + cron
# ---------------------------------------------------------------------------
log "[6/10] Configurando backup automatico..."
mkdir -p "${BACKUP_DIR}"
chmod 750 "${BACKUP_DIR}"

CRON_LINE="0 3 * * * root bash ${INFRA_DIR}/scripts/backup-prod.sh >> /var/log/grom-backup.log 2>&1"
echo "${CRON_LINE}" > /etc/cron.d/grom-backup
chmod 644 /etc/cron.d/grom-backup
ok "Cron de backup diario (03h00) configurado."

# ---------------------------------------------------------------------------
# 7) Nginx do host: vhost temporario HTTP (para Certbot funcionar)
# ---------------------------------------------------------------------------
log "[7/10] Configurando Nginx do host..."

NGINX_CONF="/etc/nginx/sites-available/grom"
NGINX_ENABLED="/etc/nginx/sites-enabled/grom"

# Remover default se existir
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

# Instalar vhost baseado no exemplo do repositorio
cp "${INFRA_DIR}/nginx/grom.seg.br.conf.example" "${NGINX_CONF}"
# Substituir dominio se diferente de grom.seg.br
sed -i "s|grom.seg.br|${DOMAIN}|g" "${NGINX_CONF}"

ln -sf "${NGINX_CONF}" "${NGINX_ENABLED}"

# Testar configuracao
nginx -t
systemctl reload nginx
ok "Nginx do host configurado para ${DOMAIN}."

# ---------------------------------------------------------------------------
# 8) Certificado TLS (Let's Encrypt)
# ---------------------------------------------------------------------------
log "[8/11] Emitindo certificado TLS com Certbot..."

# Verificar se o DNS ja aponta para esta maquina (aviso, nao fatal)
MY_IP="$(curl -fsSL https://api.ipify.org 2>/dev/null || echo 'desconhecido')"
DOMAIN_IP="$(dig +short "${DOMAIN}" 2>/dev/null | tail -1 || echo 'nao resolvido')"

if [[ "${MY_IP}" != "${DOMAIN_IP}" ]]; then
    warn "IP desta maquina (${MY_IP}) diferente do DNS do dominio (${DOMAIN_IP})."
    warn "Se o DNS ainda nao propagou, o Certbot pode falhar."
    warn "Tentando mesmo assim..."
fi

certbot --nginx \
    --non-interactive \
    --agree-tos \
    --email "${CERTBOT_EMAIL}" \
    --domains "${DOMAIN}" \
    --redirect

ok "Certificado TLS emitido e HTTPS ativo."

# Renovacao automatica (certbot ja instala timer systemd, mas garantir)
systemctl enable --now certbot.timer 2>/dev/null || true

# ---------------------------------------------------------------------------
# 9) Instalar dependencias PHP (Composer)
# ---------------------------------------------------------------------------
log "[9/11] Instalando dependencias PHP (Composer)..."
docker run --rm -v "${RUNTIME_DIR}:/app" -w /app composer:2 \
    composer install --no-dev --optimize-autoloader --no-interaction
ok "Dependencias PHP instaladas."

# ---------------------------------------------------------------------------
# 10) Subir stack Docker
# ---------------------------------------------------------------------------
log "[10/11] Construindo e subindo stack Docker..."
cd "${INFRA_DIR}"

docker compose --env-file "${ENV_FILE}" -f docker-compose.prod.yml up -d --build

log "Aguardando PostgreSQL ficar pronto..."
for attempt in $(seq 1 40); do
    if docker compose --env-file "${ENV_FILE}" -f docker-compose.prod.yml exec -T postgres \
        sh -lc 'pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB"' >/dev/null 2>&1; then
        ok "PostgreSQL pronto (tentativa ${attempt})."
        break
    fi
    if [[ ${attempt} -eq 40 ]]; then
        die "PostgreSQL nao ficou pronto. Veja: docker compose logs postgres"
    fi
    sleep 3
done

log "Aplicando migrations..."
docker compose --env-file "${ENV_FILE}" -f docker-compose.prod.yml exec -T app php artisan migrate --force

log "Rodando seed estrutural..."
docker compose --env-file "${ENV_FILE}" -f docker-compose.prod.yml exec -T app php artisan db:seed --force

log "Limpando caches..."
docker compose --env-file "${ENV_FILE}" -f docker-compose.prod.yml exec -T app php artisan optimize:clear

ok "Stack Docker no ar."

# ---------------------------------------------------------------------------
# 11) Validacao final
# ---------------------------------------------------------------------------
log "[11/11] Validando endpoints..."

sleep 3  # dar tempo ao nginx + php-fpm

HTTP_STATUS=$(curl -fsSo /dev/null -w '%{http_code}' "https://${DOMAIN}/login" || echo "000")
if [[ "${HTTP_STATUS}" == "200" ]]; then
    ok "https://${DOMAIN}/login respondeu 200."
else
    warn "https://${DOMAIN}/login respondeu ${HTTP_STATUS}. Verifique os logs."
fi

# ---------------------------------------------------------------------------
# Sumario final
# ---------------------------------------------------------------------------
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

Arquivo env      : ${ENV_FILE}
Backup dir       : ${BACKUP_DIR}
Cron backup      : /etc/cron.d/grom-backup (03h diario)
Logs app         : docker compose -f ${INFRA_DIR}/docker-compose.prod.yml logs app
Healthcheck      : bash ${INFRA_DIR}/scripts/healthcheck-prod.sh

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
warn "Credenciais salvas em ${CRED_FILE} — copie e exclua o arquivo."
