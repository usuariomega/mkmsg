#!/bin/bash

# ==========================================
# MK-MSG - Script de Instalação Automatizada 
# ==========================================

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log() { echo -e "${GREEN}[INFO]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# 1. Verificações de Segurança e Ambiente
if [ "$EUID" -ne 0 ]; then error "Por favor, execute como root (use sudo)."; fi

if [ ! -f /etc/debian_version ]; then
    error "Este script é exclusivo para sistemas baseados em Debian (Ubuntu, Mint, etc). Instalação abortada."
fi

LOCAL_IP=$(hostname -I | awk '{print $1}')
IS_PRIVATE=false
if [[ $LOCAL_IP =~ ^10\. ]] || [[ $LOCAL_IP =~ ^172\.(1[6-9]|2[0-9]|3[0-1])\. ]] || [[ $LOCAL_IP =~ ^192\.168\. ]]; then
    IS_PRIVATE=true
fi

if [ "$IS_PRIVATE" = false ]; then
    error "FALHA DE SEGURANÇA: O servidor possui um IP público ($LOCAL_IP). Este sistema só permite instalação em rede local (IP Privado). Abortando."
fi

log "🚀 Iniciando instalação em sistema Debian-like ($LOCAL_IP)..."

# 2. Instalação de Dependências Iniciais
log "📦 Instalando dependências de rede e sistema, aguarde..."
echo "Apt::Cmd::Disable-Script-Warning true;" > /etc/apt/apt.conf.d/90disablescriptwarning
apt update -qq
apt install -y -qq apache2 apache2-utils sqlite3 php php-mysql php-sqlite3 php-curl git curl mysql-client sshpass >/dev/null

# 3. Automação SSH no MK-Auth
echo -e "\n--- Configuração do Servidor MK-Auth (Configurar acesso ao banco de dados) ---"
read -p "IP do Servidor MK-Auth: " MK_IP
read -p "Usuário SSH do MK-Auth (padrão: root): " MK_SSH_USER
MK_SSH_USER=${MK_SSH_USER:-root}

# Lógica de 3 tentativas de SSH
MAX_ATTEMPTS=3
ATTEMPT=1
SSH_SUCCESS=false

while [ $ATTEMPT -le $MAX_ATTEMPTS ]; do
    read -s -p "Senha SSH do $MK_SSH_USER no MK-Auth (Tentativa $ATTEMPT/$MAX_ATTEMPTS): " MK_SSH_PASS
    echo ""
    
    log "📡 Testando conexão SSH..."
    if sshpass -p "$MK_SSH_PASS" ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 "$MK_SSH_USER@$MK_IP" "exit" 2>/dev/null; then
        log "✅ Conexão SSH estabelecida com sucesso!"
        SSH_SUCCESS=true
        break
    else
        warn "Falha ao logar no SSH. Verifique a senha."
        ATTEMPT=$((ATTEMPT+1))
    fi
done

if [ "$SSH_SUCCESS" = false ]; then
    error "Não foi possível logar no SSH após $MAX_ATTEMPTS tentativas. Instalação abortada."
fi

# Dados do Banco
read -p "Usuário que deseja criar no Banco (ex: mkmsg_user): " MK_USER
read -p "Senha para este novo usuário: " MK_PASS
read -p "Senha ROOT do MySQL do MK-Auth (padrão: vertrigo): " MK_ROOT_PASS
MK_ROOT_PASS=${MK_ROOT_PASS:-vertrigo}

log "⚙️ Configurando MySQL remotamente no MK-Auth..."
REMOTE_SCRIPT="
sed -i 's/bind-address.*/bind-address = $MK_IP/' /etc/mysql/conf.d/50-server.cnf
service mysql restart
mysql -u root -p$MK_ROOT_PASS -e \"CREATE USER IF NOT EXISTS '$MK_USER'@'$LOCAL_IP' IDENTIFIED BY '$MK_PASS'; GRANT SELECT ON mkradius.* TO '$MK_USER'@'$LOCAL_IP'; FLUSH PRIVILEGES;\"
"

sshpass -p "$MK_SSH_PASS" ssh -o StrictHostKeyChecking=no "$MK_SSH_USER@$MK_IP" "$REMOTE_SCRIPT"

# 4. Personalização do Provedor
echo -e "\n--- Personalização do Provedor ---"
read -p "Nome do seu Provedor: " PROVEDOR_NOME
read -p "Site do seu Provedor (ex: www.provedor.com.br): " PROVEDOR_SITE

# Tratamento da URL do Provedor (remove http:// ou https://)
PROVEDOR_SITE=$(echo "$PROVEDOR_SITE" | sed -e 's|^[^/]*//||' -e 's|^www\.||')
PROVEDOR_SITE="www.$PROVEDOR_SITE"

# 5. Geração de Token (20 caracteres)
log "🔑 Gerando Token de Segurança (20 caracteres)..."
API_TOKEN=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 20)

# 6. Clonar e Configurar MK-MSG
INSTALL_DIR="/var/www/html/mkmsg"
log "📥 Clonando o repositório MK-MSG..."
if [ -d "$INSTALL_DIR" ]; then
    mv "$INSTALL_DIR" "${INSTALL_DIR}_backup_$(date +%Y%m%d_%H%M%S)"
fi
cd /var/www/html
git clone https://github.com/usuariomega/mkmsg.git >/dev/null

# Valores padrão de dias
DIAS_NOPRAZO=1
DIAS_VENCIDO=1
DIAS_PAGO=1

# 7. Acesso e Cron Opcional
echo -e "\n--- Configuração de Acesso ao Painel ---"
read -p "Usuário para o Painel Web (padrão: admin): " WEB_USER
WEB_USER=${WEB_USER:-admin}
htpasswd -c /etc/apache2/.htpasswd "$WEB_USER"

read -p "Deseja configurar o agendamento automático (Cron)? [S/n]: " CONFIRM_CRON
if [[ "$CONFIRM_CRON" =~ ^[Ss]$ ]] || [ -z "$CONFIRM_CRON" ]; then
    echo -e "\n--- Configuração de Dias para Envio Automático ---"
    read -p "Dias antes de vencer (noprazo) [padrão: 1]: " DIAS_NOPRAZO
    DIAS_NOPRAZO=${DIAS_NOPRAZO:-1}
    read -p "Dias após vencer (vencido) [padrão: 1]: " DIAS_VENCIDO
    DIAS_VENCIDO=${DIAS_VENCIDO:-1}
    read -p "Dias após pago (pago) [padrão: 1]: " DIAS_PAGO
    DIAS_PAGO=${DIAS_PAGO:-1}

    read -p "Confirme a senha do Painel Web para o Cron: " WEB_PASS
    (crontab -l 2>/dev/null | grep -v "mkmsg/cron") | crontab -
    (crontab -l 2>/dev/null; echo "0 9  * * * curl -X POST -F 'posttodos=1' http://$WEB_USER:$WEB_PASS@127.0.0.1/mkmsg/cronnoprazo.php > /dev/null 2>&1") | crontab -
    (crontab -l 2>/dev/null; echo "0 10 * * * curl -X POST -F 'posttodos=1' http://$WEB_USER:$WEB_PASS@127.0.0.1/mkmsg/cronvencido.php > /dev/null 2>&1") | crontab -
    (crontab -l 2>/dev/null; echo "0 11 * * * curl -X POST -F 'posttodos=1' http://$WEB_USER:$WEB_PASS@127.0.0.1/mkmsg/cronpago.php > /dev/null 2>&1") | crontab -
    log "✅ Agendamento Cron configurado!"
else
    warn "Agendamento Cron ignorado."
fi

# 8. Atualizar config.php
log "📝 Atualizando config.php..."
CONFIG_FILE="$INSTALL_DIR/config.php"
sed -i "s/\$servername    = .*/\$servername    = \"$MK_IP\";/" "$CONFIG_FILE"
sed -i "s/\$username      = .*/\$username 	   = \"$MK_USER\";/" "$CONFIG_FILE"
sed -i "s/\$password      = .*/\$password 	   = \"$MK_PASS\";/" "$CONFIG_FILE"
sed -i "s/\$provedor      = .*/\$provedor	   = \"$PROVEDOR_NOME\";/" "$CONFIG_FILE"
sed -i "s/\$site          = .*/\$site		   = \"$PROVEDOR_SITE\";/" "$CONFIG_FILE"
sed -i "s/\$wsip          = .*/\$wsip 	   = \"http:\/\/127.0.0.1:8000\";/" "$CONFIG_FILE"
sed -i "s/\$token         = .*/\$token 	   = \"$API_TOKEN\";/" "$CONFIG_FILE"
sed -i "s/\$diasnoprazo    = .*/\$diasnoprazo    = $DIAS_NOPRAZO;/" "$CONFIG_FILE"
sed -i "s/\$diasvencido    = .*/\$diasvencido    = $DIAS_VENCIDO;/" "$CONFIG_FILE"
sed -i "s/\$diaspago       = .*/\$diaspago       = $DIAS_PAGO;/" "$CONFIG_FILE"


# 9. Permissões e Apache
log "🔐 Configurando permissões e Apache..."
chown -R www-data:www-data "$INSTALL_DIR/db/" "$INSTALL_DIR/logs/"
chmod -R 755 "$INSTALL_DIR/db/" "$INSTALL_DIR/logs/"
sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
sed -i 's/ServerTokens OS/ServerTokens Prod/' /etc/apache2/conf-enabled/security.conf
sed -i 's/ServerSignature On/ServerSignature Off/' /etc/apache2/conf-enabled/security.conf
systemctl restart apache2

# 10. Instalação da API WhatsApp (Local)
log "📱 Instalando API WhatsApp Local..."
if [ -f "$INSTALL_DIR/install/install_whatsapp_api_local.sh" ]; then
    sed -i "s/FIXED_TOKEN=.*/FIXED_TOKEN=\"$API_TOKEN\"/" "$INSTALL_DIR/install/install_whatsapp_api_local.sh"
    chmod +x "$INSTALL_DIR/install/install_whatsapp_api_local.sh"
    bash "$INSTALL_DIR/install/install_whatsapp_api_local.sh"
fi

log "✅ INSTALAÇÃO CONCLUÍDA!"
log "-------------------------------------------------------"
log "PROVEDOR:       $PROVEDOR_NOME ($PROVEDOR_SITE)"
log "SISTEMA MK-MSG: http://$LOCAL_IP/mkmsg"
log "API WHATSAPP:   http://$LOCAL_IP:8000"
log "TOKEN DA API:   $API_TOKEN"
log "-------------------------------------------------------"
log "💡 DICA: Se precisar reconfigurar qualquer variável (IP, Token, etc),"
log "basta editar o arquivo: $CONFIG_FILE"
log "-------------------------------------------------------"
