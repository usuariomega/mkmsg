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

# Função para validar se um IP é válido (formato dos octetos)
validate_ip_format() {
    local ip=$1
    if ! [[ $ip =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        return 1
    fi
    IFS='.' read -r octet1 octet2 octet3 octet4 <<< "$ip"
    for octet in $octet1 $octet2 $octet3 $octet4; do
        if ! [[ $octet =~ ^[0-9]+$ ]] || [ "$octet" -lt 0 ] || [ "$octet" -gt 255 ]; then
            return 1
        fi
    done
    return 0
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

echo -e "\n"

log "🚀 Iniciando instalação do sistema MK-MSG"

# 2. Instalação de Dependências Iniciais
log "📦 Instalando dependências de rede e sistema, aguarde..."
echo "Apt::Cmd::Disable-Script-Warning true;" > /etc/apt/apt.conf.d/90disablescriptwarning
apt-get update -qq
apt-get install -y -qq apache2 apache2-utils php php-mysql php-curl git curl sshpass autossh supervisor >/dev/null

# 3. Automação SSH no MK-Auth
echo -e "\n--- Configuração do Servidor MK-Auth (Configurar acesso ao banco de dados) ---"

while true; do
    read -p "IP do Servidor MK-Auth: " MK_IP
    if ! validate_ip_format "$MK_IP"; then
        warn "❌ ERRO: IP inválido ($MK_IP). Digite um IP válido (xxx.xxx.xxx.xxx)"
        continue
    fi
    break
done

read -p "Porta SSH do Servidor MK-Auth (22): " MK_PORT
MK_PORT=${MK_PORT:-22}

# Opções comuns do SSH
SSH_OPTS="-p $MK_PORT -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=5 -o LogLevel=ERROR"

# Gerar chave SSH se não existir
if [ ! -f /root/.ssh/id_rsa ]; then
    ssh-keygen -t ed25519 -f /root/.ssh/id_rsa -N "" >/dev/null
fi

SSH_SUCCESS=false
for attempt in {1..3}; do
    read -sp "Senha SSH do MK-Auth (tentativa $attempt/3): " MK_SSH_PASS
    echo -e "\n"
    if [ -z "$MK_SSH_PASS" ]; then
        warn "❌ ERRO: A senha não pode estar vazia."
        continue
    fi
    
    log "⏳ Tentando configurar acesso SSH (tentativa $attempt/3)..."
    if sshpass -p "$MK_SSH_PASS" ssh-copy-id $SSH_OPTS root@$MK_IP >/dev/null 2>&1; then
        if ssh $SSH_OPTS root@$MK_IP "exit" >/dev/null 2>&1; then
            SSH_SUCCESS=true
            log "✅ Conexão SSH estabelecida com sucesso!"
            break
        fi
    else
        warn "❌ Falha ao conectar via SSH na tentativa $attempt."
    fi
done

if [ "$SSH_SUCCESS" = false ]; then
    error "Falha ao conectar ao MK-Auth após 3 tentativas. Abortando instalação."
fi

# 4. Configuração do Banco de Dados
log "⚙️ Verificando e ajustando configurações no servidor remoto..."

REMOTE_DB_CONFIG="
# Verificar bind-address no servidor remoto
BIND_FILE='/etc/mysql/conf.d/50-server.cnf'
if [ ! -f \"\$BIND_FILE\" ]; then
    BIND_FILE='/etc/mysql/mariadb.conf.d/50-server.cnf'
fi
if [ ! -f \"\$BIND_FILE\" ]; then
    BIND_FILE='/etc/mysql/my.cnf'
fi

CURRENT_BIND=\$(grep '^bind-address' \"\$BIND_FILE\" | awk '{print \$3}')
if [ \"\$CURRENT_BIND\" != \"127.0.0.1\" ]; then
    sed -i 's/bind-address.*/bind-address = 127.0.0.1/' \"\$BIND_FILE\"
    service mysql restart >/dev/null 2>&1
    echo 'RESTORED'
else
    echo 'OK'
fi

# Garantir permissões de túnel SSH
sed -i 's/^#\?AllowTcpForwarding.*/AllowTcpForwarding yes/' /etc/ssh/sshd_config
sed -i 's/^#\?PermitTunnel.*/PermitTunnel yes/' /etc/ssh/sshd_config
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication yes/' /etc/ssh/sshd_config
sed -i 's/^#\?PubkeyAuthentication.*/PubkeyAuthentication yes/' /etc/ssh/sshd_config
service ssh restart
"

REMOTE_RESULT=$(ssh $SSH_OPTS root@$MK_IP "$REMOTE_DB_CONFIG")

if [[ "$REMOTE_RESULT" == *"RESTORED"* ]]; then
    echo -e "\n"
    warn "⚠️  O IP do banco de dados foi restaurado para o original do Mk-Auth (127.0.0.1)."
    warn "⚠️  Se você tem outra integração, ela poderá parar de funcionar."
    warn "⚠️  Diga a seu consultor para usar tunel SSH!"
    echo -e "\n"
fi

log "🔗 Configurando túnel SSH persistente com autossh..."

# Configuração do Supervisor para o autossh (Lado Cliente)
# Criar diretório de logs e ajustar permissões
rm -rf /var/log/mkmsg
mkdir -p /var/log/mkmsg
chown www-data:www-data /var/log/mkmsg

cat > /etc/supervisor/conf.d/ssh_tunnel.conf << EOF
[program:mkmsgtun]
command=/usr/bin/autossh -M 0 -N -o "StrictHostKeyChecking=no" -o "ServerAliveInterval 30" -o "ServerAliveCountMax 3" -o "ExitOnForwardFailure yes" -p $MK_PORT -L 3306:127.0.0.1:3306 root@$MK_IP
user=root
autostart=true
autorestart=true
stderr_logfile=/var/log/mkmsg/mkmsgtun_error.log
stdout_logfile=/var/log/mkmsg/mkmsgtun_output.log
EOF

supervisorctl reread >/dev/null 2>&1
supervisorctl update >/dev/null 2>&1
supervisorctl restart mkmsgtun >/dev/null 2>&1

log "✅ Túnel SSH configurado (Porta Local 3306 -> MK-Auth:3306)"

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
echo ""

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

# Tentar obter o token do config.php
if [ -z "$API_TOKEN" ]; then
    if [ -f "/var/www/html/mkmsg/config.php" ]; then
        API_TOKEN=$(grep '\$token' /var/www/html/mkmsg/config.php | grep -oP '"\K[^"]+' | head -1)
        if [ -n "$API_TOKEN" ]; then
            log "✅ Token obtido do config.php: $API_TOKEN"
        fi
    fi
fi

#Se ainda não tem token, perguntar ao usuário
if [ -z "$API_TOKEN" ]; then
    while true; do
        echo ""
        echo "Token não encontrado. Escolha uma opção:"
        echo ""
        echo "  1) Gerar um novo token aleatório (20 caracteres)"
        echo "  2) Digitar um token customizado"
        echo ""
        
        read -p "Digite sua escolha (1 ou 2): " TOKEN_CHOICE
        echo ""
        
        if [ "$TOKEN_CHOICE" = "1" ]; then
            log "🔑 Gerando novo token..."
            API_TOKEN=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 20)
            log "✅ Token gerado: $API_TOKEN"
            break
        elif [ "$TOKEN_CHOICE" = "2" ]; then
            read -p "Digite o token (20 caracteres recomendado): " API_TOKEN
            if [ -z "$API_TOKEN" ]; then
                error "Token não pode estar vazio."
                continue
            fi
            log "✅ Token fornecido: $API_TOKEN"
            break
        else
            warn "❌ Opção inválida. Por favor, escolha 1 ou 2."
        fi
    done
fi

# 7. Clonar Repositório e Configurar Sistema
log "📥 Clonando repositório do MK-MSG..."
INSTALL_DIR="/var/www/html/mkmsg"

log "📥 Verificando instalações anteriores..."

if [ -d "$INSTALL_DIR" ]; then

    BACKUP_DIR="${INSTALL_DIR}_backup"
    BACKUP_REC="${INSTALL_DIR}_backup/db"
    
    warn "⚠️  Instalação anterior detectada em $INSTALL_DIR"
    log "📦 Realizando backup da instalação anterior..."
    log "📦 Origem: $INSTALL_DIR"
    log "📦 Destino: $BACKUP_DIR"
    
    rm -Rf "$BACKUP_DIR"
    mv "$INSTALL_DIR" "$BACKUP_DIR"
    
    if [ $? -eq 0 ]; then
        log "✅ Backup realizado com sucesso!"
    else
        error "Erro ao criar backup. Abortando."
    fi
fi

log "🧹 Limpando diretório de instalação..."
rm -rf "$INSTALL_DIR"

log "📥 Clonando o repositório MK-MSG..."
cd /var/www/html
git clone https://github.com/usuariomega/mkmsg.git >/dev/null 2>&1

if [ ! -d "$INSTALL_DIR" ]; then
    error "Erro ao clonar o repositório MK-MSG. Verifique sua conexão com a internet."
fi

#Recuperar backup de Conf. msg
if [ -d "$BACKUP_REC" ]; then
    cp -Rf "$BACKUP_REC" "$INSTALL_DIR/"
    log "✅ Backup de API e Conf. msg recuperados com sucesso!"
fi

log "✅ Repositório clonado com sucesso!"

#Configurar usuário e senha do painel web
echo -e "\n--- Configuração de Acesso ao Painel Web MK-MSG---"
read -p "Usuário que deseja criar para acessar o painel web MK-MSG (ex: admin): " WEB_USER
WEB_USER=${WEB_USER:-admin}

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
        if htpasswd -bc /etc/apache2/.htpasswd "$WEB_USER" "$PASS1" >/dev/null 2>&1; then
            echo ""
            log "✅ Usuário do painel criado com sucesso!"
            WEB_PASS="$PASS1"
            break
        else
            error "Erro ao criar o arquivo de senhas do Apache."
        fi
    fi
done
echo ""

# 8. Atualizar config.php
log "📝 Atualizando config.php..."
CONFIG_FILE="$INSTALL_DIR/config.php"
sed -i "s/\$servername = .*/\$servername = \"127.0.0.1\";/" "$CONFIG_FILE"
sed -i "s/\$username = .*/\$username = \"root\";/" "$CONFIG_FILE"
sed -i "s/\$password = .*/\$password = \"vertrigo\";/" "$CONFIG_FILE"
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

a2enmod rewrite >/dev/null 2>&1
systemctl restart apache2 >/dev/null 2>&1

# 10. Instalar e Configurar Supervisor + Daemon + Rotação de Logs Mensal
log "🤖 Configurando sistema de automação com Supervisor e Rotação Mensal..."

# Criar diretório de logs e ajustar permissões
chown www-data:www-data /var/log/mkmsg

# Configuração do Supervisor
cat > /etc/supervisor/conf.d/daemon.conf << 'SUPERVISOR_EOF'
[program:mkmsg]
command=/usr/bin/php /var/www/html/mkmsg/daemon.php
directory=/var/www/html/mkmsg
autostart=true
autorestart=true
stderr_logfile=/var/log/mkmsg/mkmsg_error.log
stdout_logfile=/var/log/mkmsg/mkmsg_output.log
user=www-data
environment=HOME="/var/www",USER="www-data"
priority=999
stopwaitsecs=10
SUPERVISOR_EOF

# Configuração do Logrotate para gerar logs mensais
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
supervisorctl restart mkmsg >/dev/null 2>&1

log "✅ Daemon de automação configurado e iniciado!"


log ""
log ""
log "✅ INSTALAÇÃO DO SISTEMA MK-MSG CONCLUÍDA!"
log "--------------------------------------------------------"
log ""
log "PROVEDOR:       $PROVEDOR_NOME ($PROVEDOR_SITE)"
log ""
log "MK-MSG:         http://$LOCAL_IP/mkmsg"
log "Usuário:        $WEB_USER"
log "Senha:          $WEB_PASS"
log ""
log "Token:          $API_TOKEN"
log ""
log "--------------------------------------------------------"
log "💡 AUTOMAÇÃO:   O sistema usa um daemon que envia "
log "                mensagens automaticas para os clientes "
log "                no prazo, pagos e vencidos. A conf. "
log "                dos horários e dias ficam no portal web "
log "                no botão Conf. geral "
log ""
log "AGENDADOR:"
log "Status:         sudo supervisorctl status  mkmsg"
log "Reiniciar:      sudo supervisorctl restart mkmsg"
log ""
log "TUNEL SSH:"
log "Status:         sudo supervisorctl status  mkmsgtun"
log "Reiniciar:      sudo supervisorctl restart mkmsgtun"
log ""
log "Logs:           sudo tail -n 10 /var/log/mkmsg/* "
log "--------------------------------------------------------"
log ""
log ""
