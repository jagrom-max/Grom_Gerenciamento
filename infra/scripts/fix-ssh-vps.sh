#!/usr/bin/env bash
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; CYAN='\033[0;36m'; YELLOW='\033[1;33m'; NC='\033[0m'
log()  { echo -e "${CYAN}[SSH-FIX]${NC} $*"; }
ok()   { echo -e "${GREEN}[OK]${NC} $*"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $*"; }
die()  { echo -e "${RED}[ERRO]${NC} $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Execute como root (sudo -i)."

install_openssh_if_needed() {
  if command -v sshd >/dev/null 2>&1; then
    ok "OpenSSH já instalado."
    return
  fi

  log "OpenSSH não encontrado; instalando..."
  if command -v apt-get >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq openssh-server
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y openssh-server
  elif command -v yum >/dev/null 2>&1; then
    yum install -y openssh-server
  else
    die "Gerenciador de pacotes não suportado."
  fi

  command -v sshd >/dev/null 2>&1 || die "Falha ao instalar OpenSSH."
  ok "OpenSSH instalado."
}

enable_ssh_service() {
  local svc=''
  if systemctl list-unit-files ssh.service >/dev/null 2>&1; then
    svc='ssh'
  elif systemctl list-unit-files sshd.service >/dev/null 2>&1; then
    svc='sshd'
  else
    svc='sshd'
  fi

  log "Habilitando serviço ${svc}..."
  systemctl enable --now "$svc"
  systemctl restart "$svc"
  systemctl is-active --quiet "$svc" || die "Serviço ${svc} não está ativo."
  ok "Serviço ${svc} ativo."
}

configure_sshd_port_22() {
  local cfg='/etc/ssh/sshd_config'
  [[ -f "$cfg" ]] || die "Arquivo $cfg não encontrado."

  cp "$cfg" "${cfg}.bak.$(date +%s)"

  if grep -Eq '^\s*#?\s*Port\s+' "$cfg"; then
    sed -i -E 's/^\s*#?\s*Port\s+.*/Port 22/' "$cfg"
  else
    printf '\nPort 22\n' >> "$cfg"
  fi

  sshd -t || die "Configuração sshd inválida após ajuste."
  ok "sshd configurado para porta 22."
}

open_firewall_22() {
  log "Ajustando firewall local..."

  if command -v ufw >/dev/null 2>&1; then
    ufw allow 22/tcp || true
    ok "Regra UFW para 22/tcp aplicada."
  fi

  if command -v firewall-cmd >/dev/null 2>&1; then
    firewall-cmd --permanent --add-service=ssh || true
    firewall-cmd --reload || true
    ok "Regra firewalld para SSH aplicada."
  fi

  if command -v iptables >/dev/null 2>&1; then
    iptables -C INPUT -p tcp --dport 22 -j ACCEPT 2>/dev/null || iptables -I INPUT -p tcp --dport 22 -j ACCEPT
    ok "Regra iptables para 22/tcp aplicada em memória."
  fi
}

show_status() {
  echo
  log "Status final"
  ss -ltnp | grep ':22' || warn "Nenhum processo escutando em :22"

  if systemctl is-active ssh >/dev/null 2>&1; then
    ok "ssh service: active"
  elif systemctl is-active sshd >/dev/null 2>&1; then
    ok "sshd service: active"
  else
    warn "serviço SSH não ativo"
  fi

  echo
  warn "Se ainda estiver fechado externamente, falta ajustar NSG/Security List na Oracle (Ingress TCP 22, origem seu IP público/32)."
}

log "Iniciando correção automática de SSH..."
install_openssh_if_needed
configure_sshd_port_22
enable_ssh_service
open_firewall_22
show_status
ok "Concluído."
