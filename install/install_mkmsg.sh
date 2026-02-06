#!/bin/bash

# ==========================================
# MK-MSG - Instalador Principal Interativo
# ==========================================

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

log() { echo -e "${GREEN}[INFO]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }
info() { echo -e "${BLUE}[MENU]${NC} $1"; }

# URLs dos scripts no GitHub
GITHUB_REPO="https://raw.githubusercontent.com/usuariomega/mkmsg/main/install"
SCRIPT_SISTEMA="$GITHUB_REPO/install_mkmsg_sistema.sh"
SCRIPT_WHATSAPP="$GITHUB_REPO/install_whatsapp_api_local.sh"

# Diretório temporário para os scripts
TEMP_DIR=$(mktemp -d)
trap "rm -rf $TEMP_DIR" EXIT

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

echo ""
log "🚀 Bem-vindo ao Instalador MK-MSG!"
echo ""

# 2. Menu de Seleção com Loop de Validação
while true; do
    info "Escolha o que deseja instalar:"
    echo ""
    echo "  1) Sistema MK-MSG + API WhatsApp"
    echo "  2) Apenas Sistema MK-MSG"
    echo "  3) Apenas API WhatsApp"
    echo "  0) Sair"
    echo ""
    
    read -p "Digite sua escolha (0, 1, 2 ou 3): " CHOICE
    
    # Validar entrada
    if [ "$CHOICE" = "0" ]; then
        log "Instalador cancelado pelo usuário."
        exit 0
    elif [[ "$CHOICE" =~ ^[1-3]$ ]]; then
        break
    else
        warn "❌ Opção inválida. Por favor, escolha 0, 1, 2 ou 3."
        echo ""
    fi
done

echo ""

# 3. Função para baixar scripts do GitHub
download_script() {
    local url=$1
    local output=$2
    local script_name=$(basename "$url")
    
    log "📥 Baixando $script_name do GitHub..."
    
    if ! curl -fsSL "$url" -o "$output" 2>/dev/null; then
        error "Falha ao baixar $script_name. Verifique sua conexão com a internet e se a URL está correta."
    fi
    
    chmod +x "$output"
    log "✅ $script_name baixado com sucesso"
}

# 4. Executar scripts conforme a escolha
case $CHOICE in
    1)
        log "Você escolheu: Sistema MK-MSG + API WhatsApp"
        log "Iniciando instalação completa..."
        echo ""
        
        # Baixar script do sistema
        SISTEMA_SCRIPT="$TEMP_DIR/install_mkmsg_sistema.sh"
        download_script "$SCRIPT_SISTEMA" "$SISTEMA_SCRIPT"
        
        echo ""
        
        # Instalar sistema
        bash "$SISTEMA_SCRIPT"
        SISTEMA_OK=$?
        
        if [ $SISTEMA_OK -eq 0 ]; then
            echo ""
            log "✅ Sistema MK-MSG instalado com sucesso!"
            echo ""
            
            # Tentar obter o token do config.php
            # Detectar o usuário que chamou o script (se foi com sudo)
            if [ -n "$SUDO_USER" ]; then
                TARGET_USER="$SUDO_USER"
                TARGET_HOME=$(getent passwd "$SUDO_USER" | cut -d: -f6)
            else
                TARGET_USER=$(whoami)
                TARGET_HOME=$HOME
            fi
            
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
            
            if [ -z "$API_TOKEN" ]; then
                if [ -f "/var/www/html/mkmsg/config.php" ]; then
                    API_TOKEN=$(grep '\$token' /var/www/html/mkmsg/config.php | grep -oP '"\K[^"]+' | head -1)
                    if [ -n "$API_TOKEN" ]; then
                        log "✅ Token obtido do config.php: $API_TOKEN"
                    fi
                fi
            fi
            
            echo ""
            read -p "Deseja instalar a API WhatsApp agora? [S/n]: " INSTALL_WA
            if [[ "$INSTALL_WA" =~ ^[Ss]$ ]] || [ -z "$INSTALL_WA" ]; then
                echo ""
                log "Iniciando instalação da API WhatsApp..."
                echo ""
                
                # Baixar script do WhatsApp
                WHATSAPP_SCRIPT="$TEMP_DIR/install_whatsapp_api_local.sh"
                download_script "$SCRIPT_WHATSAPP" "$WHATSAPP_SCRIPT"
                
                echo ""
                
                # Passar o token para o instalador do WhatsApp
                if [ -n "$API_TOKEN" ]; then
                    bash "$WHATSAPP_SCRIPT" "$API_TOKEN"
                else
                    bash "$WHATSAPP_SCRIPT"
                fi
            else
                warn "Instalação do WhatsApp ignorada."
                if [ -n "$API_TOKEN" ]; then
                    log "Você pode instalar depois com o token: $API_TOKEN"
                    log "Comando: curl -fsSL $SCRIPT_WHATSAPP | bash -s \"$API_TOKEN\""
                else
                    log "Você pode instalar depois executando: "
                    log "curl -fsSL $SCRIPT_WHATSAPP | bash"
                fi
            fi
        else
            error "Falha na instalação do sistema MK-MSG."
        fi
        ;;
        
    2)
        log "Você escolheu: Apenas Sistema MK-MSG"
        log "Iniciando instalação do sistema..."
        echo ""
        
        # Baixar script do sistema
        SISTEMA_SCRIPT="$TEMP_DIR/install_mkmsg_sistema.sh"
        download_script "$SCRIPT_SISTEMA" "$SISTEMA_SCRIPT"
        
        echo ""
        
        # Instalar sistema
        bash "$SISTEMA_SCRIPT"
        SISTEMA_OK=$?
        
        if [ $SISTEMA_OK -eq 0 ]; then
            echo ""
            log "✅ Sistema MK-MSG instalado com sucesso!"
            
            # Obter e exibir o token gerado
            if [ -f "/var/www/html/mkmsg/config.php" ]; then
                API_TOKEN=$(grep '\$token' /var/www/html/mkmsg/config.php | grep -oP '"\K[^"]+' | head -1)
                if [ -n "$API_TOKEN" ]; then
                    echo ""
                    log "📌 Token gerado: $API_TOKEN"
                    log "   Use este token ao instalar a API WhatsApp em outro servidor"
                    log "   Comando: curl -fsSL $SCRIPT_WHATSAPP | bash -s \"$API_TOKEN\""
                fi
            fi
        fi
        ;;
        
    3)
        log "Você escolheu: Apenas API WhatsApp"
        log "Iniciando instalação da API WhatsApp..."
        echo ""

            # Tentar obter o token do config.php
            # Detectar o usuário que chamou o script (se foi com sudo)
            if [ -n "$SUDO_USER" ]; then
                TARGET_USER="$SUDO_USER"
                TARGET_HOME=$(getent passwd "$SUDO_USER" | cut -d: -f6)
            else
                TARGET_USER=$(whoami)
                TARGET_HOME=$HOME
            fi
            
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
            
            if [ -z "$API_TOKEN" ]; then
                if [ -f "/var/www/html/mkmsg/config.php" ]; then
                    API_TOKEN=$(grep '\$token' /var/www/html/mkmsg/config.php | grep -oP '"\K[^"]+' | head -1)
                    if [ -n "$API_TOKEN" ]; then
                        log "✅ Token obtido do config.php: $API_TOKEN"
                    fi
                fi
            fi
        
        # Se não encontrou token local, perguntar ao usuário
        if [ -z "$API_TOKEN" ]; then
            echo ""
            info "Token não encontrado no sistema local."
            echo ""
            read -p "Digite o token da API (ou deixe em branco para gerar um novo): " USER_TOKEN
            
            if [ -n "$USER_TOKEN" ]; then
                API_TOKEN="$USER_TOKEN"
                log "✅ Token fornecido: $API_TOKEN"
            else
                log "Será gerado um novo token durante a instalação"
            fi
        fi
        
        echo ""
        
        # Baixar script do WhatsApp
        WHATSAPP_SCRIPT="$TEMP_DIR/install_whatsapp_api_local.sh"
        download_script "$SCRIPT_WHATSAPP" "$WHATSAPP_SCRIPT"
        
        echo ""
        
        # Executar instalador do WhatsApp
        if [ -n "$API_TOKEN" ]; then
            bash "$WHATSAPP_SCRIPT" "$API_TOKEN"
        else
            bash "$WHATSAPP_SCRIPT"
        fi
        ;;
esac

log "✅ Instalação finalizada!"
