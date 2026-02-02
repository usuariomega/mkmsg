#!/bin/bash

# ==========================================
# MK-MSG - Script de Instalação do Sistema
# ==========================================

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log() { echo -e "${GREEN}[INFO]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# Opções comuns do SSH para ignorar erros de host key
SSH_OPTS="-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=5 -o LogLevel=ERROR"

# Função para validar se um IP é válido e privado
validate_private_ip() {
    local ip=$1
    if ! [[ $ip =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        echo "invalid_format"
        return
    fi
    IFS='.' read -r octet1 octet2 octet3 octet4 <<< "$ip"
    for octet in $octet1 $octet2 $octet3 $octet4; do
        if ! [[ $octet =~ ^[0-9]+$ ]] || [ "$octet" -lt 0 ] || [ "$octet" -gt 255 ]; then
            echo "invalid_format"
            return
        fi
    done
    
    if [[ $ip =~ ^10\. ]] || \
       [[ $ip =~ ^100\.(6[4-9]|7[0-9]|8[0-9]|9[0-9]|1[0-1][0-9]|12[0-7])\. ]] || \
       [[ $ip =~ ^172\.(1[6-9]|2[0-9]|3[0-1])\. ]] || \
       [[ $ip =~ ^192\.168\. ]]; then
            echo "private"
            return
    fi
    echo "public"
}

# 1. Verificações de Segurança e Ambiente
if [ "$EUID" -ne 0 ]; then 
    error "Por favor, execute como root (use sudo)."
fi

if [ ! -f /etc/debian_version ]; then
    error "Este script é exclusivo para sistemas baseados em Debian (Ubuntu, Mint, etc). Instalação abortada."
fi

if grep -qi "devuan" /etc/os-release; then
    error "INSTALAÇÃO CANCELADA: Este sistema não pode ser instalado dentro do MK-Auth. Use o MK-MSG em uma máquina separada."
fi

LOCAL_IP=$(hostname -I | awk '{print $1}')
ip_type=$(validate_private_ip "$LOCAL_IP")
IS_PRIVATE=false

if [[ "$ip_type" == "private" ]]; then
    IS_PRIVATE=true
fi

if [ "$IS_PRIVATE" = false ]; then
    error "FALHA DE SEGURANÇA: O servidor possui um IP público ($LOCAL_IP). Este sistema só permite instalação em rede local (IP Privado). Abortando."
fi

log "🚀 Iniciando instalação do sistema MK-MSG"

# 2. Instalação de Dependências Iniciais
log "📦 Instalando dependências de rede e sistema, aguarde..."
echo "Apt::Cmd::Disable-Script-Warning true;" > /etc/apt/apt.conf.d/90disablescriptwarning
apt-get update -qq
apt-get install -y -qq apache2 apache2-utils sqlite3 php php-mysql php-sqlite3 php-curl git curl sshpass supervisor >/dev/null

# 3. Automação SSH no MK-Auth
echo -e "\n--- Configuração do Servidor MK-Auth (Configurar acesso ao banco de dados) ---"

while true; do
    read -p "IP do Servidor MK-Auth: " MK_IP
    IP_VALIDATION=$(validate_private_ip "$MK_IP")
    if [ "$IP_VALIDATION" = "invalid_format" ]; then
        warn "❌ ERRO: IP inválido ($MK_IP). Digite um IP válido (xxx.xxx.xxx.xxx)"
        continue
    fi
    if [ "$IP_VALIDATION" = "public" ]; then
        warn "❌ ERRO: O IP informado ($MK_IP) é público. Apenas IPs privados são permitidos."
        continue
    fi
    break
done

SSH_SUCCESS=false
for attempt in {1..3}; do
    read -sp "Senha SSH do MK-Auth (tentativa $attempt/3): " MK_SSH_PASS
    echo ""
    if [ -z "$MK_SSH_PASS" ]; then
        warn "❌ ERRO: A senha não pode estar vazia."
        continue
    fi
    if sshpass -p "$MK_SSH_PASS" ssh $SSH_OPTS root@$MK_IP "exit" 2>/dev/null; then
        SSH_SUCCESS=true
        log "✅ Conexão SSH estabelecida com sucesso!"
        break
    else
        warn "❌ Falha ao conectar via SSH. Verifique a senha ou o acesso root no MK-Auth."
    fi
done

if [ "$SSH_SUCCESS" = false ]; then
    error "Falha ao conectar ao MK-Auth após 3 tentativas. Abortando instalação."
fi

# 4. Configuração do Banco de Dados
echo -e "\n--- Configuração do Banco de Dados MK-Auth ---"

# Dados do novo usuário que será criado
read -p "Usuário que deseja criar para ler o banco (ex: mkmsglerdb): " NEW_DB_USER
NEW_DB_USER=${NEW_DB_USER:-mkmsglerdb}

while true; do
    read -sp "Senha para este novo usuário ($NEW_DB_USER): " NEW_DB_PASS
    echo ""
    NEW_DB_PASS=${NEW_DB_PASS:-mkmsgsenhadodb}
    if [ -z "$NEW_DB_PASS" ]; then
        warn "❌ ERRO: A senha não pode estar vazia."
        continue
    fi
    break
done

DB_ROOT_PASS="vertrigo"
DB_SUCCESS=false

log "🔍 Verificando acesso ao MySQL no MK-Auth..."

if sshpass -p "$MK_SSH_PASS" ssh $SSH_OPTS root@$MK_IP "mysql -u root -p$DB_ROOT_PASS -e 'SELECT 1;' >/dev/null 2>&1"; then
    DB_SUCCESS=true
    log "✅ Conexão com MySQL confirmada!"
else
    warn "⚠️ Senha padrão falhou."
    for attempt in {1..3}; do
        read -sp "Digite a senha ROOT do MySQL do MK-Auth (tentativa $attempt/3): " DB_ROOT_PASS
        echo ""
        if [ -z "$DB_ROOT_PASS" ]; then
            warn "❌ ERRO: A senha não pode estar vazia."
            continue
        fi
        if sshpass -p "$MK_SSH_PASS" ssh $SSH_OPTS root@$MK_IP "mysql -u root -p$DB_ROOT_PASS -e 'SELECT 1;' >/dev/null 2>&1"; then
            DB_SUCCESS=true
            log "✅ Senha ROOT do MySQL validada!"
            break
        else
            warn "❌ Senha ROOT do MySQL incorreta."
        fi
    done
fi

if [ "$DB_SUCCESS" = false ]; then
    error "Falha ao validar a senha ROOT do MySQL após 3 tentativas. Abortando."
fi

log "⚙️ Configurando MySQL remotamente no MK-Auth..."

BIND_CONF="
# Configurar bind-address
BIND_FILE='/etc/mysql/conf.d/50-server.cnf'
if [ -f \"\$BIND_FILE\" ]; then
    sed -i 's/bind-address.*/bind-address = 0.0.0.0/' \"\$BIND_FILE\"
else
    sed -i 's/bind-address.*/bind-address = 0.0.0.0/' /etc/mysql/mariadb.conf.d/50-server.cnf 2>/dev/null || \
    sed -i 's/bind-address.*/bind-address = 0.0.0.0/' /etc/mysql/my.cnf 2>/dev/null
fi

# Reiniciar serviços
service mysql restart >/dev/null 2>&1
sleep 2
service freeradius restart >/dev/null 2>&1

# Criar usuário e dar permissões
mysql -u root -p$DB_ROOT_PASS -e \"
    DROP USER IF EXISTS '$NEW_DB_USER'@'%';
    CREATE USER '$NEW_DB_USER'@'%' IDENTIFIED BY '$NEW_DB_PASS';
    GRANT SELECT ON mkradius.* TO '$NEW_DB_USER'@'%';
    FLUSH PRIVILEGES;
\" >/dev/null 2>&1
"

if sshpass -p "$MK_SSH_PASS" ssh $SSH_OPTS root@$MK_IP "$BIND_CONF"; then
    log "✅ MySQL configurado e usuário '$NEW_DB_USER' criado com sucesso!"
else
    error "Erro ao executar a configuração remota no MK-Auth."
fi

# 5. Informações do Provedor
echo -e "\n--- Informações do Provedor ---"
read -p "Nome do Provedor: " PROVEDOR_NOME

while true; do
    read -p "Site do Provedor (ex: www.exemplo.com.br): " PROVEDOR_SITE
    PROVEDOR_SITE=$(echo "$PROVEDOR_SITE" | sed 's|^https\?://||')
    if [[ ! $PROVEDOR_SITE =~ ^www\. ]]; then
        warn "❌ ERRO: O site deve começar com 'www.' (ex: www.exemplo.com.br)"
        continue
    fi
    break
done

# 6. Token da API WhatsApp

# Detectar o usuário que chamou o script (se foi com sudo)
if [ -n "$SUDO_USER" ]; then
    TARGET_USER="$SUDO_USER"
    TARGET_HOME=$(eval echo ~$SUDO_USER)
else
    TARGET_USER=$(whoami)
    TARGET_HOME=$HOME
fi

# Configurações
APP_DIR="$TARGET_HOME/whatsapp-server"

API_TOKEN=""

#Se ainda não tem token, tentar obter do arquivo de configuração do WhatsApp (se já existe)
if [ -z "$API_TOKEN" ]; then
    if [ -f "$APP_DIR/config.js" ]; then
        API_TOKEN=$(grep 'API_TOKEN' "$APP_DIR/config.js" | grep -oP '"\K[^"]+' | head -1)
        if [ -n "$API_TOKEN" ]; then
            log "✅ Token obtido da instalação anterior: $API_TOKEN"
        fi
    fi
fi

#Se ainda não tem token, perguntar ao usuário
if [ -z "$API_TOKEN" ]; then
    echo ""
    echo "Token não encontrado. Escolha uma opção:"
    echo ""
    echo "  1) Gerar um novo token aleatório (20 caracteres)"
    echo "  2) Digitar um token customizado"
    echo ""
    
    read -p "Digite sua escolha (1 ou 2): " TOKEN_CHOICE
    
    if [ "$TOKEN_CHOICE" = "1" ]; then
        log "🔑 Gerando novo token..."
        API_TOKEN=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 20)
        log "✅ Token gerado: $API_TOKEN"
    elif [ "$TOKEN_CHOICE" = "2" ]; then
        read -p "Digite o token (20 caracteres recomendado): " API_TOKEN
        if [ -z "$API_TOKEN" ]; then
            error "Token não pode estar vazio."
        fi
        log "✅ Token fornecido: $API_TOKEN"
    else
        error "Opção inválida."
    fi
fi

echo ""
log "🔐 Token: $API_TOKEN"
echo ""

# 7. Clonar Repositório e Configurar Sistema
log "📥 Clonando repositório do MK-MSG..."
INSTALL_DIR="/var/www/html/mkmsg"

log "📥 Verificando instalações anteriores..."

if [ -d "$INSTALL_DIR" ]; then
    BACKUP_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_DIR="${INSTALL_DIR}_backup_${BACKUP_TIMESTAMP}"
    
    warn "⚠️  Instalação anterior detectada em $INSTALL_DIR"
    log "📦 Realizando backup da instalação anterior..."
    log "   Origem: $INSTALL_DIR"
    log "   Destino: $BACKUP_DIR"
    
    mv "$INSTALL_DIR" "$BACKUP_DIR"
    
    if [ $? -eq 0 ]; then
        log "✅ Backup realizado com sucesso!"
        log "   Você pode restaurar com: sudo mv $BACKUP_DIR $INSTALL_DIR"
    else
        error "Erro ao criar backup. Abortando."
    fi
fi

log "🧹 Limpando diretório de instalação..."
rm -rf "$INSTALL_DIR"

log "📥 Clonando o repositório MK-MSG..."
cd /var/www/html
git clone https://github.com/usuariomega/mkmsg.git >/dev/null

if [ ! -d "$INSTALL_DIR" ]; then
    error "Erro ao clonar o repositório MK-MSG. Verifique sua conexão com a internet."
fi

log "✅ Repositório clonado com sucesso!"

# Configurar usuário e senha do painel web
echo -e "\n--- Configuração de Acesso ao Painel Web MK-MSG---"
while true; do
    read -p "Usuário que deseja criar para acessar o painel web MK-MSG: " WEB_USER
    if [ -z "$WEB_USER" ]; then
        warn "❌ ERRO: O usuário não pode estar vazio."
        continue
    fi
    break
done

while true; do
    read -sp "Senha para este novo usuário do painel web MK-MSG: " PASS1
    echo ""
    if [ -z "$PASS1" ]; then
        warn "❌ ERRO: A senha não pode estar vazia."
        continue
    fi
    read -sp "Confirme a senha: " PASS2
    echo ""
    if [ "$PASS1" != "$PASS2" ]; then
        warn "❌ ERRO: As senhas não coincidem."
    else
        if htpasswd -bc /etc/apache2/.htpasswd "$WEB_USER" "$PASS1"; then
            log "✅ Usuário do painel criado com sucesso!"
            WEB_PASS="$PASS1"
            break
        else
            error "Erro ao criar o arquivo de senhas do Apache."
        fi
    fi
done

# 8. Atualizar config.php
log "📝 Atualizando config.php..."
CONFIG_FILE="$INSTALL_DIR/config.php"
sed -i "s/\$servername = .*/\$servername = \"$MK_IP\";/" "$CONFIG_FILE"
sed -i "s/\$username = .*/\$username = \"$NEW_DB_USER\";/" "$CONFIG_FILE"
sed -i "s/\$password = .*/\$password = \"$NEW_DB_PASS\";/" "$CONFIG_FILE"
sed -i "s/\$provedor = .*/\$provedor = \"$PROVEDOR_NOME\";/" "$CONFIG_FILE"
sed -i "s/\$site = .*/\$site = \"$PROVEDOR_SITE\";/" "$CONFIG_FILE"
sed -i "s/\$token = .*/\$token = \"$API_TOKEN\";/" "$CONFIG_FILE"

# 9. Permissões e Apache
log "🔐 Configurando permissões e Apache..."
chown -R www-data:www-data $INSTALL_DIR
chmod -R 755 "$INSTALL_DIR/db/" "$INSTALL_DIR/logs/"
sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
sed -i 's/ServerTokens OS/ServerTokens Prod/' /etc/apache2/conf-enabled/security.conf
sed -i 's/ServerSignature On/ServerSignature Off/' /etc/apache2/conf-enabled/security.conf
systemctl restart apache2

# 10. Instalar e Configurar Supervisor + Daemon + Rotação de Logs Mensal
log "🤖 Configurando sistema de automação com Supervisor e Rotação Mensal..."

# Criar diretório de logs e ajustar permissões
mkdir -p /var/log/mkmsg
chown www-data:www-data /var/log/mkmsg

# Configuração do Supervisor
cat > /etc/supervisor/conf.d/daemon.conf << 'SUPERVISOR_EOF'
[program:mkmsg]
command=/usr/bin/php /var/www/html/mkmsg/daemon.php
directory=/var/www/html/mkmsg
autostart=true
autorestart=true
startretries=3
stderr_logfile=/var/log/mkmsg/daemon_error.log
stdout_logfile=/var/log/mkmsg/daemon_output.log
user=www-data
environment=HOME="/var/www",USER="www-data"
priority=999
stopwaitsecs=10
SUPERVISOR_EOF

# Configuração do Logrotate para gerar logs mensais
# Isso criará arquivos como daemon_output.log.1.gz todo mês
cat > /etc/logrotate.d/mkmsg << 'LOGROTATE_EOF'
/var/log/mkmsg/*.log {
    monthly
    missingok
    rotate 12
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        # Avisa o Supervisor para reabrir os arquivos de log após a rotação
        /usr/bin/supervisorctl signal SIGUSR2 mkmsg > /dev/null 2>&1 || true
    endscript
}
LOGROTATE_EOF

# Garantir permissões no script PHP
chmod +x "$INSTALL_DIR/daemon.php"

# Recarregar Supervisor de forma silenciosa
supervisorctl reread >/dev/null 2>&1
supervisorctl update >/dev/null 2>&1
supervisorctl start mkmsg >/dev/null 2>&1

log "✅ Daemon de automação configurado e iniciado!"


log ""
log ""
log "✅ INSTALAÇÃO DO SISTEMA MK-MSG CONCLUÍDA!"
log "--------------------------------------------------------"
log ""
log "PROVEDOR:       $PROVEDOR_NOME ($PROVEDOR_SITE)"
log ""
log "SISTEMA MK-MSG: http://$LOCAL_IP/mkmsg"
log "Usuário:        $WEB_USER"
log "Senha:          $WEB_PASS"
log ""
log "--------------------------------------------------------"
log "💡 AUTOMAÇÃO:   O sistema usa um daemon que envia "
log "                mensagens automaticas para os clientes "
log "                no prazo, pagos e vencidos. A conf. "
log "                dos horários e dias ficam no portal web "
log "                no botão Conf. geral "
log ""
log "Para gerenciar o daemon:"
log "Status:         sudo supervisorctl status mkmsg-daemon"
log "--------------------------------------------------------"
log ""
log ""
