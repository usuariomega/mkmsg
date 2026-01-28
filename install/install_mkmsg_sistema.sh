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

# Função para validar se um IP é privado
validate_private_ip() {
    local ip=$1
    local is_private=false
    
    # Validar formato básico do IP
    if ! [[ $ip =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; then
        echo "invalid_format"
        return
    fi
    
    # Verificar se é IP privado (10.x.x.x, 172.16-31.x.x, 192.168.x.x)
    if [[ $ip =~ ^10\. ]] || [[ $ip =~ ^172\.(1[6-9]|2[0-9]|3[0-1])\. ]] || [[ $ip =~ ^192\.168\. ]]; then
        is_private=true
    fi
    
    if [ "$is_private" = true ]; then
        echo "private"
    else
        echo "public"
    fi
}

# 1. Verificações de Segurança e Ambiente
if [ "$EUID" -ne 0 ]; then 
    error "Por favor, execute como root (use sudo)."
fi

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

log "🚀 Iniciando instalação do sistema MK-MSG"

# 2. Instalação de Dependências Iniciais
log "📦 Instalando dependências de rede e sistema, aguarde..."
echo "Apt::Cmd::Disable-Script-Warning true;" > /etc/apt/apt.conf.d/90disablescriptwarning
apt-get update -qq
apt-get install -y -qq apache2 apache2-utils sqlite3 php php-mysql php-sqlite3 php-curl git curl mysql-client sshpass >/dev/null

# 3. Automação SSH no MK-Auth
echo -e "\n--- Configuração do Servidor MK-Auth (Configurar acesso ao banco de dados) ---"

# Loop para validar IP do MK-Auth
while true; do
    read -p "IP do Servidor MK-Auth: " MK_IP
    
    # Validar se o IP do MK-Auth é privado
    IP_VALIDATION=$(validate_private_ip "$MK_IP")
    if [ "$IP_VALIDATION" = "invalid_format" ]; then
        warn "❌ ERRO: IP inválido ($MK_IP). Por favor, digite um IP válido no formato xxx.xxx.xxx.xxx"
        continue
    fi
    
    if [ "$IP_VALIDATION" = "public" ]; then
        warn "❌ FALHA DE SEGURANÇA: O servidor MK-Auth possui um IP público ($MK_IP). Este sistema só permite conexão com servidores em rede local (IP Privado)."
        continue
    fi
    
    log "✅ IP do MK-Auth validado como privado ($MK_IP)"
    break
done

read -p "Usuário SSH do MK-Auth (padrão: root): " MK_SSH_USER
MK_SSH_USER=${MK_SSH_USER:-root}

# Lógica de 3 tentativas de SSH
MAX_ATTEMPTS=3
ATTEMPT=1
SSH_SUCCESS=false

while [ $ATTEMPT -le $MAX_ATTEMPTS ]; do
    # Validação de senha não vazia
    while true; do
        read -s -p "Senha SSH do $MK_SSH_USER no MK-Auth (Tentativa $ATTEMPT/$MAX_ATTEMPTS): " MK_SSH_PASS
        echo ""
        if [ -z "$MK_SSH_PASS" ]; then
            warn "A senha não pode estar em branco. Por favor, digite a senha."
        else
            break
        fi
    done
    
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
read -p "Usuário que deseja criar para ler o banco de dados do Mk-Auth (ex: mkmsglerdb): " MK_USER
MK_USER=${MK_USER:-mkmsglerdb}

# Validação de senha do novo usuário do banco (não pode ser vazia)
while true; do
    read -p "Senha para este novo usuário ($MK_USER): " MK_PASS
    if [ -z "$MK_PASS" ]; then
        warn "A senha não pode estar em branco."
    else
        break
    fi
done

# Senha ROOT do MySQL (com valor padrão vertrigo se estiver em branco)
read -p "Senha ROOT do MySQL do MK-Auth (padrão: vertrigo): " MK_ROOT_PASS
MK_ROOT_PASS=${MK_ROOT_PASS:-vertrigo}

log "⚙️ Configurando MySQL remotamente no MK-Auth..."
REMOTE_SCRIPT="
sed -i 's/bind-address.*/bind-address = $MK_IP/' /etc/mysql/conf.d/50-server.cnf
service mysql restart
mysql -u root -p$MK_ROOT_PASS -e \"DROP USER IF EXISTS '$MK_USER'@'$LOCAL_IP'; CREATE USER '$MK_USER'@'$LOCAL_IP' IDENTIFIED BY '$MK_PASS'; GRANT SELECT ON mkradius.* TO '$MK_USER'@'$LOCAL_IP'; FLUSH PRIVILEGES;\"
"

sshpass -p "$MK_SSH_PASS" ssh -o StrictHostKeyChecking=no "$MK_SSH_USER@$MK_IP" "$REMOTE_SCRIPT"

# 4. Personalização do Provedor
echo -e "\n--- Personalização do Provedor ---"
read -p "Nome do seu Provedor: " PROVEDOR_NOME
read -p "Site do seu Provedor (ex: www.provedor.com.br): " PROVEDOR_SITE

# Tratamento da URL do Provedor (remove http:// ou https:// e garante www.)
PROVEDOR_SITE=$(echo "$PROVEDOR_SITE" | sed -e 's|^[^/]*//||' -e 's|^www\.||')
PROVEDOR_SITE="www.$PROVEDOR_SITE"

# 5. Geração de Token (20 caracteres)
log "🔑 Gerando Token de Segurança (20 caracteres)..."
API_TOKEN=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 20)

# 6. Clonar e Configurar MK-MSG
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

# Valores padrão de dias
DIAS_NOPRAZO=1
DIAS_VENCIDO=1
DIAS_PAGO=1

# 7. Acesso e Cron Opcional
echo -e "\n--- Configuração de segurança para Acesso ao Painel do MK-MSG ---"
read -p "Usuário para poder acessar o Painel do MK-MSG (padrão: admin): " WEB_USER
WEB_USER=${WEB_USER:-admin}

# Loop para garantir que o htpasswd seja criado com sucesso e sem senha vazia
while true; do
    log "Defina a senha de acesso ao painel do MK-MSG:"
    # Usamos o modo não interativo do htpasswd para ter controle total sobre a validação
    read -s -p "Digite a senha: " PASS1
    echo ""
    if [ -z "$PASS1" ]; then
        warn "A senha não pode estar em branco. Tente novamente."
        continue
    fi
    read -s -p "Confirme a senha: " PASS2
    echo ""
    
    if [ "$PASS1" != "$PASS2" ]; then
        warn "As senhas não coincidem. Tente novamente."
    else
        # Cria o arquivo de senhas usando o modo batch (-b) para evitar o prompt do htpasswd
        if htpasswd -bc /etc/apache2/.htpasswd "$WEB_USER" "$PASS1"; then
            log "✅ Usuário do painel criado com sucesso!"
            WEB_PASS="$PASS1" # Guarda para usar no Cron se necessário
            break
        else
            error "Erro ao criar o arquivo de senhas do Apache."
        fi
    fi
done

read -p "Deseja configurar o agendamento automático (Cron)? [S/n]: " CONFIRM_CRON
if [[ "$CONFIRM_CRON" =~ ^[Ss]$ ]] || [ -z "$CONFIRM_CRON" ]; then
    echo -e "\n--- Configuração de Dias para Envio Automático ---"
    read -p "Dias antes de vencer (noprazo) [padrão: 1]: " DIAS_NOPRAZO
    DIAS_NOPRAZO=${DIAS_NOPRAZO:-1}
    read -p "Dias após vencer (vencido) [padrão: 1]: " DIAS_VENCIDO
    DIAS_VENCIDO=${DIAS_VENCIDO:-1}
    read -p "Dias após pago (pago) [padrão: 1]: " DIAS_PAGO
    DIAS_PAGO=${DIAS_PAGO:-1}

    # A senha do Cron agora usa a mesma validada acima, mas confirmamos se o usuário quer manter
    log "Usando a senha do painel web para o agendamento automático."

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
sed -i "s/\$username      = .*/\$username      = \"$MK_USER\";/" "$CONFIG_FILE"
sed -i "s/\$password      = .*/\$password      = \"$MK_PASS\";/" "$CONFIG_FILE"
sed -i "s/\$provedor      = .*/\$provedor      = \"$PROVEDOR_NOME\";/" "$CONFIG_FILE"
sed -i "s/\$site          = .*/\$site          = \"$PROVEDOR_SITE\";/" "$CONFIG_FILE"
sed -i "s/\$token         = .*/\$token         = \"$API_TOKEN\";/" "$CONFIG_FILE"
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

log "✅ INSTALAÇÃO DO SISTEMA CONCLUÍDA!"
log "-------------------------------------------------------"
log "PROVEDOR:       $PROVEDOR_NOME ($PROVEDOR_SITE)"
log ""
log "SISTEMA MK-MSG: http://$LOCAL_IP/mkmsg"
log "Usuário:        $WEB_USER"
log "Senha:          $WEB_PASS"
log ""
log "TOKEN DA API:   $API_TOKEN"
log "-------------------------------------------------------"
log "💡 DICA: Se precisar reconfigurar qualquer variável "
log "(IP, Token, etc), basta editar o arquivo com o comando:"
log "sudo nano $CONFIG_FILE"
log "Em seguida reinicie o servidor web:"
log "sudo service apache2 restart"
log "-------------------------------------------------------"
