#!/bin/bash
# ====================================
# WhatsApp API - Script de Instalação 
# ====================================

# Cores para logs
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

log() { echo -e "${GREEN}[INFO]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }
info() { echo -e "${BLUE}[MENU]${NC} $1"; }

# 1. Verificar permissão elevada no início
if [ "$EUID" -ne 0 ]; then 
    error "Por favor, execute como root (use sudo)."
fi

# Verificar se está sendo instalado em Devuan (MK-Auth)
if grep -qi "devuan" /etc/os-release; then
    error "INSTALAÇÃO CANCELADA: Este sistema não pode ser instalado dentro do MK-Auth. Use o MK-MSG em uma máquina separada."
fi

# Detectar o usuário que chamou o script (se foi com sudo)
if [ -n "$SUDO_USER" ]; then
    TARGET_USER="$SUDO_USER"
    TARGET_HOME=$(getent passwd "$SUDO_USER" | cut -d: -f6)
else
    TARGET_USER=$(whoami)
    TARGET_HOME=$HOME
fi

# Configurações
APP_NAME="whatsapp-api"
APP_DIR="$TARGET_HOME/whatsapp-server"
NODE_VERSION=22
PORT=8000

log "🚀 Iniciando instalação da API WhatsApp..."
log "Usuário de instalação: $TARGET_USER"
log "Diretório: $APP_DIR"
echo ""

# ============================================
# GERENCIAMENTO DE TOKEN
# ============================================

FIXED_TOKEN=""

# 1. Verificar se token foi passado como argumento
if [ -n "$1" ]; then
    FIXED_TOKEN="$1"
    log "📌 Token recebido como argumento: $FIXED_TOKEN"
fi

# 2. Se não recebeu token, tentar obter do config.php local
if [ -z "$FIXED_TOKEN" ]; then
    if [ -f "/var/www/html/mkmsg/config.php" ]; then
        FIXED_TOKEN=$(grep '\$token' /var/www/html/mkmsg/config.php | grep -oP '"\K[^"]+' | head -1)
        if [ -n "$FIXED_TOKEN" ]; then
            log "✅ Token obtido do config.php local: $FIXED_TOKEN"
        fi
    fi
fi

# 3. Se ainda não tem token, tentar obter do arquivo de configuração do WhatsApp (se já existe)
if [ -z "$FIXED_TOKEN" ]; then
    if [ -f "$APP_DIR/config.js" ]; then
        FIXED_TOKEN=$(grep 'API_TOKEN' "$APP_DIR/config.js" | grep -oP '"\K[^"]+' | head -1)
        if [ -n "$FIXED_TOKEN" ]; then
            log "✅ Token obtido da instalação anterior: $FIXED_TOKEN"
        fi
    fi
fi

# 4. Se ainda não tem token, perguntar ao usuário
if [ -z "$FIXED_TOKEN" ]; then
    echo ""
    info "Token não encontrado. Escolha uma opção:"
    echo ""
    echo "  1) Gerar um novo token aleatório (20 caracteres)"
    echo "  2) Digitar um token customizado"
    echo "  3) Obter token do config.php de outra máquina"
    echo ""
    
    read -p "Digite sua escolha (1, 2 ou 3): " TOKEN_CHOICE
    echo ""
    
    if [ "$TOKEN_CHOICE" = "1" ]; then
        log "🔑 Gerando novo token..."
        FIXED_TOKEN=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 20)
        log "✅ Token gerado: $FIXED_TOKEN"
    elif [ "$TOKEN_CHOICE" = "2" ]; then
        read -p "Digite o token (20 caracteres recomendado): " FIXED_TOKEN
        if [ -z "$FIXED_TOKEN" ]; then
            error "Token não pode estar vazio."
        fi
        log "✅ Token fornecido: $FIXED_TOKEN"
    elif [ "$TOKEN_CHOICE" = "3" ]; then
        echo ""
        log "📋 Como obter o token da outra máquina:"
        echo ""
        echo "  1. Acesse a máquina onde o sistema MK-MSG está instalado"
        echo ""
        echo "  2. Execute um dos comandos abaixo:"
        echo ""
        echo "     Opção A (recomendado):"
        echo "     cat /var/www/html/mkmsg/config.php | grep token"
        echo ""
        echo "     Opção B:"
        echo "     grep token /var/www/html/mkmsg/config.php"
        echo ""
        echo "  3. O token aparecerá assim:"
        echo "     \$token         = \"ABCDEF1234567890GHIJ\";"
        echo ""
        echo "  4. Copie apenas os 20 caracteres: ABCDEF1234567890GHIJ"
        echo ""
        read -p "Digite o token copiado: " FIXED_TOKEN
        if [ -z "$FIXED_TOKEN" ]; then
            error "Token não pode estar vazio."
        fi
        log "✅ Token fornecido: $FIXED_TOKEN"
    else
        error "Opção inválida."
    fi
fi

echo ""
log "🔐 Token final: $FIXED_TOKEN"
echo ""

# ============================================
# INSTALAÇÃO
# ============================================

# 1. Limpeza
log "🧹 Removendo instalações anteriores..."
su - "$TARGET_USER" -c "pm2 delete $APP_NAME >/dev/null 2>&1 || true"
rm -rf "$APP_DIR"

# 2. Sistema - Instalar dependências globais
log "🚀 Instalando dependências do sistema..."
apt-get update -qq
apt-get install -y -qq curl git ca-certificates build-essential >/dev/null
echo "Apt::Cmd::Disable-Script-Warning true;" > /etc/apt/apt.conf.d/90disablescriptwarning


# 3. Node.js & PM2
if ! command -v node >/dev/null; then
    log "🌐 Instalando Node.js $NODE_VERSION..."
    curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - >/dev/null 
    apt-get install -y -qq nodejs >/dev/null 2>&1
fi

if ! command -v pm2 >/dev/null; then
    log "💾 Instalando PM2 globalmente..."
    npm install -g pm2 -s
fi

# 4. Estrutura - Criar diretórios locais do usuário
log "📁 Criando estrutura de diretórios..."
mkdir -p "$APP_DIR"/{auth,logs,public}
chown -R "$TARGET_USER":"$TARGET_USER" "$APP_DIR"

# 5. package.json
log "📝 Configurando dependências do projeto..."
cat <<EOF > "$APP_DIR/package.json"
{
  "name": "whatsapp-api",
  "version": "1.0.0",
  "type": "module",
  "dependencies": {
    "@whiskeysockets/baileys": "latest",
    "express": "latest",
    "qrcode": "latest",
    "pino": "latest"
  }
}
EOF
chown "$TARGET_USER":"$TARGET_USER" "$APP_DIR/package.json"

log "💾 Instalando dependências do projeto (npm install)..."
su - "$TARGET_USER" -c "cd $APP_DIR && npm install --quiet --no-fund --no-audit 2>&1 | grep -v 'npm notice' | grep -v 'npm warn' | grep -v 'added' || true"

# 6. Configuração
log "⚙️  Criando arquivo de configuração..."
cat <<EOF > "$APP_DIR/config.js"
export const API_TOKEN = "${FIXED_TOKEN}"
export const MESSAGE_DELAY = 3000
export const PORT = ${PORT}
EOF
chown "$TARGET_USER":"$TARGET_USER" "$APP_DIR/config.js"

# 7. queue.js
log "📝 Criando gerenciador de fila..."
cat <<'EOF' > "$APP_DIR/queue.js"
import fs from 'fs'
import path from 'path'
import { MESSAGE_DELAY } from './config.js'

const queue = []
let processing = false
const sentLogs = []

function writeLogToFile(status, message) {
  const now = new Date()
  const yearMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  const dayMonthYearTime = `${String(now.getDate()).padStart(2, '0')}-${String(now.getMonth() + 1).padStart(2, '0')}-${now.getFullYear()} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  const logDir = path.join(process.cwd(), 'logs')
  if (!fs.existsSync(logDir)) fs.mkdirSync(logDir)
  const logFile = path.join(logDir, `${yearMonth}.log`)
  const logEntry = `[${dayMonthYearTime}] [${status.toUpperCase()}] ${message}\n`
  fs.appendFileSync(logFile, logEntry)
}

export function addToQueue(fn, metadata = {}){
  queue.push({ fn, metadata })
  if (!processing) processQueue()
}

export function getQueueSize(){ return queue.length + (processing ? 1 : 0) }
export function getSentLogs(){ return sentLogs.slice(-50) }

async function processQueue(){
  if(processing || queue.length === 0) return
  processing = true
  const item = queue.shift()
  if (!item) { processing = false; return }
  const { fn, metadata } = item
  const logMsg = metadata.mensagem || 'Mensagem enviada'
  try {
    await fn()
    const dateStr = new Date().toLocaleString('pt-BR', { timeZone:'America/Sao_Paulo' })
    sentLogs.push({ date: dateStr, mensagem: logMsg, status: 'sent' })
    writeLogToFile('sent', logMsg)
  } catch(e) {
    const dateStr = new Date().toLocaleString('pt-BR', { timeZone:'America/Sao_Paulo' })
    const errorMsg = `Erro: ${e.message}`
    sentLogs.push({ date: dateStr, mensagem: errorMsg, status: 'error' })
    writeLogToFile('error', `${logMsg} | ${errorMsg}`)
  }
  setTimeout(() => { processing = false; processQueue() }, MESSAGE_DELAY)
}
EOF
chown "$TARGET_USER":"$TARGET_USER" "$APP_DIR/queue.js"

# 8. index.js
log "📝 Criando servidor Express..."
cat <<'EOF' > "$APP_DIR/index.js"
import express from 'express'
import makeWASocket, { useMultiFileAuthState, DisconnectReason } from '@whiskeysockets/baileys'
import QRCode from 'qrcode'
import fs from 'fs'
import path from 'path'
import pino from 'pino'
import { exec } from 'child_process'
import { API_TOKEN, PORT } from './config.js'
import { addToQueue, getQueueSize, getSentLogs } from './queue.js'

const app = express()
app.use(express.json())
app.use(express.static('public'))

const TELEGRAM_CONFIG_FILE = path.join(process.cwd(), 'telegram_config.json')

app.get('/', (req, res) => res.sendFile(path.join(process.cwd(), 'public/index.html')))

app.get('/logs', (req, res) => {
  res.sendFile(path.join(process.cwd(), 'public/log_viewer.html'))
})

app.get('/logs-data', (req, res) => {
  const logDir = path.join(process.cwd(), 'logs')
  if (!fs.existsSync(logDir)) return res.send('Nenhum diretório de log encontrado.')
  const files = fs.readdirSync(logDir).filter(f => f.endsWith('.log')).sort().reverse()
  if (files.length === 0) return res.send('Nenhum arquivo de log encontrado.')
  const latestLog = path.join(logDir, files[0])
  const content = fs.readFileSync(latestLog, 'utf8')
  res.send(content)
})

app.get('/telegram-config', (req, res) => {
  if (fs.existsSync(TELEGRAM_CONFIG_FILE)) {
    const config = JSON.parse(fs.readFileSync(TELEGRAM_CONFIG_FILE, 'utf8'))
    res.json(config)
  } else {
    res.json({ botToken: '', chatId: '', schedule: '0 7,11,15 * * *' })
  }
})

app.post('/telegram-config', (req, res) => {
  const { botToken, chatId, schedule } = req.body
  fs.writeFileSync(TELEGRAM_CONFIG_FILE, JSON.stringify({ botToken, chatId, schedule }))
  
  if (schedule) {
    const alertScript = path.join(process.cwd(), 'telegram_alert.sh');
    const cronCmd = `(crontab -l 2>/dev/null | grep -v "telegram_alert.sh"; echo "${schedule} /bin/bash ${alertScript} > /dev/null 2>&1") | crontab -`;
    exec(cronCmd, (error) => {
      if (error) console.error(`Erro ao atualizar cron: ${error}`);
      else console.log('Cron atualizado com sucesso');
    });
  }
  
  res.json({ status: 'success' })
})

app.get('/status', (req, res) => {
  res.json({
    status: status,
    qr: qrBase64,
    queue: getQueueSize(),
    sent: getSentLogs()
  })
})

let sock, status = 'disconnected', qrBase64 = null

const auth = (req, res, next) => {
  if (req.headers['x-api-token'] !== API_TOKEN) return res.json({ status: 'token-error' })
  next()
}

function formatBrazilianNumber(n) {
  let num = n.replace(/\D/g, '');
  if (!num.startsWith('55')) num = '55' + num;
  const ddd = parseInt(num.slice(2, 4));
  let rest = num.slice(4);
  if (ddd <= 30) { if (rest.length === 8) rest = '9' + rest; } 
  else { if (rest.length === 9 && rest.startsWith('9')) rest = rest.slice(1); }
  return num.slice(0, 4) + rest + '@s.whatsapp.net';
}

function normalizeMessage(msg) {
  let t = msg.toString();
  try { t = decodeURIComponent(t); } catch {}
  t = t.replace(/\r\n/g, '\n');
  return t.split('##').map(m => m.trim()).filter(Boolean);
}

async function connectToWhatsApp() {
  const { state, saveCreds } = await useMultiFileAuthState('auth')
  sock = makeWASocket({
    auth: state,
    printQRInTerminal: false,
    logger: pino({ level: 'silent' }),
    browser: ['Mac OS', 'Chrome', '146.0.0.0']
  })
  sock.ev.on('creds.update', saveCreds)
  sock.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update
    if (qr) { qrBase64 = await QRCode.toDataURL(qr); status = 'disconnected' }
    if (connection === 'open') { status = 'connected'; qrBase64 = null; console.log('✅ Conectado!') }
    if (connection === 'close') {
      const shouldReconnect = lastDisconnect?.error?.output?.statusCode !== DisconnectReason.loggedOut
      status = 'disconnected'; qrBase64 = null
      if (shouldReconnect) { setTimeout(connectToWhatsApp, 5000) } 
      else { fs.rmSync('auth', { recursive: true, force: true }); setTimeout(connectToWhatsApp, 3000) }
    }
  })
}

app.post('/send', auth, (req, res) => {
  const { numero, mensagem } = req.body
  if (!numero || !mensagem || status !== 'connected') return res.json({ status: 'error' })
  try {
    const jid = formatBrazilianNumber(numero);
    const messages = normalizeMessage(mensagem);
    addToQueue(async () => { for (const m of messages) { await sock.sendMessage(jid, { text: m }); } }, { mensagem });
    res.json({ status: 'sent' });
  } catch (e) { res.json({ status: 'error' }); }
})

// ============================================================
// Evolution API V2 - Compatibilidade de rotas
// Usa o mesmo API_TOKEN com header "apikey"
// Nome da instância na URL é aceito mas ignorado (instância única)
// ============================================================

const authEvolution = (req, res, next) => {
  if (req.headers['apikey'] !== API_TOKEN) return res.status(401).json({ status: 'error', message: 'Unauthorized' })
  next()
}

// POST /message/sendText/:instance
app.post('/message/sendText/:instance', authEvolution, (req, res) => {
  const { number, text } = req.body
  const textContent = typeof text === 'object' ? text.text : text
  if (!number || !textContent || status !== 'connected') return res.status(400).json({ status: 'error', message: 'Parâmetros inválidos ou WhatsApp desconectado' })
  try {
    const jid = formatBrazilianNumber(number)
    const messages = normalizeMessage(textContent)
    addToQueue(async () => { for (const m of messages) { await sock.sendMessage(jid, { text: m }) } }, { mensagem: textContent })
    res.json({ key: { remoteJid: jid }, status: 'PENDING', message: { conversation: textContent } })
  } catch (e) { res.status(500).json({ status: 'error', message: e.message }) }
})

// POST /message/sendMedia/:instance  (image, video e document)
app.post('/message/sendMedia/:instance', authEvolution, async (req, res) => {
  const { number, mediatype, mimetype, caption, media, fileName } = req.body
  if (!number || !media || !mediatype || status !== 'connected') return res.status(400).json({ status: 'error', message: 'Parâmetros inválidos ou WhatsApp desconectado' })
  try {
    const jid = formatBrazilianNumber(number)
    const type = mediatype.toLowerCase()
    let mediaContent
    if (media.startsWith('http://') || media.startsWith('https://')) {
      mediaContent = { url: media }
    } else {
      mediaContent = Buffer.from(media, 'base64')
    }
    let msgPayload
    if (type === 'image') {
      msgPayload = { image: mediaContent, caption: caption || '', mimetype: mimetype || 'image/jpeg' }
    } else if (type === 'video') {
      msgPayload = { video: mediaContent, caption: caption || '', mimetype: mimetype || 'video/mp4' }
    } else {
      msgPayload = { document: mediaContent, mimetype: mimetype || 'application/octet-stream', fileName: fileName || 'arquivo', caption: caption || '' }
    }
    const logMsg = `[${type}] ${fileName || media.substring(0, 60)} → ${number}`
    addToQueue(async () => { await sock.sendMessage(jid, msgPayload) }, { mensagem: logMsg })
    res.json({ key: { remoteJid: jid }, status: 'PENDING', mediaType: type })
  } catch (e) { res.status(500).json({ status: 'error', message: e.message }) }
})

// POST /telegram-test — envia mensagem de teste via bot configurado
app.post('/telegram-test', async (req, res) => {
  const { botToken, chatId } = req.body
  if (!botToken || !chatId) return res.status(400).json({ ok: false, message: 'botToken e chatId são obrigatórios' })
  try {
    const url = `https://api.telegram.org/bot${botToken}/sendMessage`
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ chat_id: chatId, text: '✅ Teste de alerta WhatsApp API — configuração OK!' })
    })
    const data = await response.json()
    if (data.ok) res.json({ ok: true })
    else res.status(400).json({ ok: false, message: data.description || 'Erro desconhecido do Telegram' })
  } catch (e) { res.status(500).json({ ok: false, message: e.message }) }
})

app.listen(PORT, '0.0.0.0', () => {
  console.log(`🚀 Server running on port ${PORT}`)
  connectToWhatsApp()
})
EOF
chown "$TARGET_USER":"$TARGET_USER" "$APP_DIR/index.js"

# 9. Dashboard (HTML)
log "📝 Criando dashboard web..."
cat <<'EOF' > "$APP_DIR/public/index.html"
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>WhatsApp API Dashboard</title>
<style>
:root{--bg:#f0f2f5;--card:#ffffff;--green:#25d366;--red:#ef4444;--border:#e5e7eb;--text:#1f2937;--muted:#6b7280;--primary:#075e54}
*{box-sizing:border-box}html,body{width:100%;overflow-x:hidden;margin:0;padding:0}
body{font-family:sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;min-height:100vh}
.container{width:100%;max-width:900px;max-height: 90vh;margin:0 auto;padding:15px;flex:1;display:flex;flex-direction:column;}
.header{text-align:center;margin-bottom:20px}.title{font-size:22px;font-weight:800;color:var(--primary)}
.stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px}
.card{background:var(--card);border-radius:12px;padding:15px;text-align:center;border:1px solid var(--border);box-shadow:0 2px 4px rgba(0,0,0,0.05)}
.label{font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:600;margin-bottom:5px;display:block}
.val{font-size:18px;font-weight:700}.online{color:var(--green)}.offline{color:var(--red)}
.qr{text-align:center;margin-bottom:20px}.qr img{max-width:300px;border-radius:12px;border:1px solid var(--border);background:#fff;padding:10px}
.logs{background:var(--card);border:1px solid var(--border);border-radius:12px;flex:1;display:flex;flex-direction:column;overflow:hidden;min-height:400px}
.l-head{padding:12px 15px;font-weight:700;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;background:#fff}
.l-body{flex:1;overflow-y:auto;background:#fcfcfc}.l-row{display:flex;flex-direction:column;padding:12px;border-bottom:1px solid #eee;font-size:13px}
.l-meta{display:flex;align-items:center;gap:8px;margin-bottom:6px}.tag{font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px;text-transform:uppercase}
.sent{background:#dcfce7;color:#166534}.err{background:#fee2e2;color:#991b1b}.msg{word-break:break-word;line-height:1.4;color:#4b5563}
@media(min-width:600px){.title{font-size:28px}.l-row{flex-direction:row;align-items:center}.l-meta{margin-bottom:0;width:280px;flex-shrink:0}.msg{flex:1}}
button{padding:6px 12px;border:none;border-radius:6px;background:#374151;color:#fff;cursor:pointer;font-size:12px;margin-left:5px}
.btn-group{display:flex;align-items:center}
.modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center}
.modal-content{background:#fff;padding:20px;border-radius:12px;width:95%;max-width:480px;box-shadow:0 4px 12px rgba(0,0,0,0.15)}
.modal-content h3{margin-top:0;color:var(--primary)}
.modal-content input, .modal-content select{width:100%;padding:10px;margin:10px 0;border:1px solid var(--border);border-radius:6px;font-size:14px}
.modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:15px}
.btn-save{background:var(--green)}
.btn-cancel{background:var(--muted)}
.help-text{font-size:11px;color:var(--muted);margin-top:5px;margin-bottom:10px;line-height:1.4}
.help-table{width:100%;border-collapse:collapse;margin-top:5px}
.help-table td{padding:2px 0;vertical-align:top}
.help-table td:first-child{width:60px;font-weight:bold;color:var(--text)}
.custom-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.btn-test{background:#2563eb;font-size:12px;padding:6px 10px}
.field-wrap{position:relative;margin:6px 0 2px}
.field-wrap input{margin:0;padding-right:30px}
.field-icon{position:absolute;right:9px;top:50%;transform:translateY(-50%);font-size:14px;pointer-events:none}
.field-msg{font-size:11px;min-height:16px;margin-bottom:4px}
.field-msg.err{color:#dc2626}
.field-msg.ok{color:#16a34a}
.input-valid{border-color:#16a34a!important;background:#f0fdf4}
.input-invalid{border-color:#dc2626!important;background:#fff1f2}
.cron-preview{font-size:12px;padding:8px 12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;color:#0369a1;margin:8px 0 4px;min-height:36px;line-height:1.4}
.test-result{font-size:12px;padding:7px 10px;border-radius:6px;margin-top:6px;display:none}
.test-ok{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
.test-fail{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}</style>
</head>
<body>
<div class="container">
  <div class="header"><div class="title">📲 WhatsApp API Dashboard</div></div>
  <div class="stats"><div class="card"><span class="label">Status</span><div id="st" class="val">---</div></div><div class="card"><span class="label">Fila de Envio</span><div id="q" class="val">0</div></div></div>
  <div class="qr"><img id="qri" style="display:none"></div>
  <div class="logs">
    <div class="l-head">
      <span>📜 Logs de Atividade (Últimas 10)</span>
      <div class="btn-group">
        <button id="ps">⏸ Pausar</button>
        <button id="openLog" onclick="window.open('/logs', '_blank')">📂 Abrir Log</button>
        <button id="clearLog" onclick="clearLocalLog()">🧹 Limpar Log</button>
        <button id="tgBtn" onclick="openTelegramModal()">🔔 Alertar se Offline</button>
      </div>
    </div>
    <div id="lb" class="l-body"></div>
  </div>
</div>

<div id="tgModal" class="modal">
  <div class="modal-content">
    <h3>🔔 Configurar Alerta Telegram</h3>
    <p style="font-size:12px; color:var(--muted); margin-bottom:10px;">
      Receba uma notificação automática no Telegram quando o WhatsApp ficar <b>Offline</b>.
    </p>

    <label class="label">BOT TOKEN</label>
    <div class="field-wrap">
      <input type="text" id="botToken" placeholder="Ex: 123456789:ABCdef..." oninput="validateBotToken(this)" onpaste="setTimeout(()=>validateBotToken(this),0)">
      <span class="field-icon" id="iconBotToken"></span>
    </div>
    <div class="field-msg" id="msgBotToken"></div>

    <label class="label">CHAT ID</label>
    <div class="field-wrap">
      <input type="text" id="chatId" placeholder="Ex: -123456789 ou 123456789" oninput="validateChatId(this)" onpaste="setTimeout(()=>validateChatId(this),0)">
      <span class="field-icon" id="iconChatId"></span>
    </div>
    <div class="field-msg" id="msgChatId"></div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin:6px 0 2px">
      <label class="label" style="margin:0">TESTAR CONFIGURAÇÃO</label>
      <button class="btn-test" onclick="testTelegram()">📨 Enviar Mensagem Teste</button>
    </div>
    <div class="test-result" id="testResult"></div>

    <label class="label" style="margin-top:12px;display:block">AGENDAR HORÁRIOS DE ALERTA</label>
    <select id="alertSchedule" onchange="onScheduleChange()">
      <option value="0 * * * *">A cada 1 hora (Sempre)</option>
      <option value="0 */2 * * *">A cada 2 horas (Sempre)</option>
      <option value="0 8-18 * * *">Horário Comercial (08h às 18h)</option>
      <option value="0 8,12,18 * * *">3 vezes ao dia (08h, 12h, 18h)</option>
      <option value="0 7,11,15,19 * * *">4 vezes ao dia (07h, 11h, 15h, 19h)</option>
      <option value="custom">Personalizado (Configurar Minutos e Horas)</option>
    </select>

    <div id="customScheduleBox" style="display:none">
      <div class="custom-grid" style="margin-top:8px">
        <div>
          <label class="label">MINUTOS (0-59)</label>
          <div class="field-wrap">
            <input type="text" id="customMinutes" placeholder="Ex: 0 ou */20 ou 0,30" oninput="sanitizeCronInput(this,'min')" onpaste="setTimeout(()=>sanitizeCronInput(this,'min'),0)">
            <span class="field-icon" id="iconMins"></span>
          </div>
          <div class="field-msg" id="msgMins"></div>
        </div>
        <div>
          <label class="label">HORAS (0-23)</label>
          <div class="field-wrap">
            <input type="text" id="customHours" placeholder="Ex: * ou 8,12,18 ou 8-18" oninput="sanitizeCronInput(this,'hour')" onpaste="setTimeout(()=>sanitizeCronInput(this,'hour'),0)">
            <span class="field-icon" id="iconHours"></span>
          </div>
          <div class="field-msg" id="msgHours"></div>
        </div>
      </div>
      <div class="cron-preview" id="cronPreview">⏰ Preencha os campos acima para ver o resumo</div>
      <div class="help-text">
        <b>Exemplos válidos</b>
        <table class="help-table">
          <tr><td>*</td><td>Todo valor (ex: toda hora, todo minuto)</td></tr>
          <tr><td>*/15</td><td>A cada 15 unidades</td></tr>
          <tr><td>8,12,18</td><td>Apenas nesses valores específicos</td></tr>
          <tr><td>8-18</td><td>Intervalo de 8 até 18</td></tr>
        </table>
      </div>
    </div>

    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeTelegramModal()">Cancelar</button>
      <button class="btn-save" onclick="saveTelegramConfig()">Salvar</button>
    </div>
  </div>
</div>

<script>
let sc=true;const ps=document.getElementById('ps');ps.onclick=()=>{sc=!sc;ps.textContent=sc?'⏸ Pausar':'▶ Retomar'};

function clearLocalLog() {
  const lb = document.getElementById('lb');
  lb.innerHTML = '<div style="padding:20px;text-align:center;color:#999">Log limpo pelo usuário</div>';
}

// ── Validação Bot Token ────────────────────────────────────────
function validateBotToken(el) {
  const v = el.value.trim();
  const icon = document.getElementById('iconBotToken');
  const msg  = document.getElementById('msgBotToken');
  if (!v) { setField(el, icon, msg, '', '', ''); return false; }
  const ok = /^\d{6,12}:[A-Za-z0-9_-]{35,}$/.test(v);
  if (ok) setField(el, icon, msg, 'valid', '✅', '');
  else    setField(el, icon, msg, 'invalid', '❌', 'Formato inválido. Ex: 123456789:ABCDefgh...');
  return ok;
}

// ── Validação Chat ID ──────────────────────────────────────────
function validateChatId(el) {
  const v = el.value.trim();
  const icon = document.getElementById('iconChatId');
  const msg  = document.getElementById('msgChatId');
  if (!v) { setField(el, icon, msg, '', '', ''); return false; }
  const ok = /^-?\d{5,15}$/.test(v);
  if (ok) setField(el, icon, msg, 'valid', '✅', '');
  else    setField(el, icon, msg, 'invalid', '❌', 'Deve ser numérico (negativo para grupos). Ex: -123456789');
  return ok;
}

// ── Helper visual ──────────────────────────────────────────────
function setField(el, icon, msg, state, ico, text) {
  el.className = state === 'valid' ? 'input-valid' : state === 'invalid' ? 'input-invalid' : '';
  icon.textContent = ico;
  msg.textContent  = text;
  msg.className    = 'field-msg ' + (state === 'invalid' ? 'err' : state === 'valid' ? 'ok' : '');
}

// ── Sanitização e validação cron em tempo real ─────────────────
function sanitizeCronInput(el, type) {
  // Remove espaços imediatamente enquanto digita
  const pos = el.selectionStart;
  const original = el.value;
  const clean = original.replace(/\s/g, '');
  if (clean !== original) {
    el.value = clean;
    el.setSelectionRange(Math.max(0, pos - (original.length - clean.length)), Math.max(0, pos - (original.length - clean.length)));
  }
  const iconId = type === 'min' ? 'iconMins' : 'iconHours';
  const msgId  = type === 'min' ? 'msgMins'  : 'msgHours';
  const max    = type === 'min' ? 59 : 23;
  validateCronField(el, document.getElementById(iconId), document.getElementById(msgId), max, type);
  updateCronPreview();
}

function validateCronField(el, icon, msg, max, type) {
  const v = el.value.trim();
  if (!v) { setField(el, icon, msg, '', '', ''); return false; }
  const result = checkCronExpr(v, max);
  if (result === true) {
    setField(el, icon, msg, 'valid', '✅', '');
    return true;
  } else {
    setField(el, icon, msg, 'invalid', '❌', result);
    return false;
  }
}

function checkCronExpr(v, max) {
  if (v === '*') return true;
  // */n
  const stepMatch = v.match(/^\*\/(\d+)$/);
  if (stepMatch) {
    const n = parseInt(stepMatch[1]);
    if (n < 1 || n > max) return `Passo inválido: deve ser entre 1 e ${max}`;
    return true;
  }
  // Partes separadas por vírgula (podem ser números ou ranges)
  const parts = v.split(',');
  for (const part of parts) {
    const rangeMatch = part.match(/^(\d+)-(\d+)$/);
    if (rangeMatch) {
      const a = parseInt(rangeMatch[1]), b = parseInt(rangeMatch[2]);
      if (a > max || b > max) return `Valor fora do limite (máx ${max})`;
      if (a >= b) return `Intervalo inválido: ${a}-${b} (início deve ser menor que fim)`;
    } else if (/^\d+$/.test(part)) {
      if (parseInt(part) > max) return `Valor ${part} fora do limite (máx ${max})`;
    } else {
      return `Expressão inválida: "${part}"`;
    }
  }
  return true;
}

// ── Preview legível do cron ────────────────────────────────────
function updateCronPreview() {
  const mins  = document.getElementById('customMinutes').value.trim();
  const hours = document.getElementById('customHours').value.trim();
  const el    = document.getElementById('cronPreview');
  if (!mins || !hours) { el.textContent = '⏰ Preencha os campos acima para ver o resumo'; return; }
  const mOk = checkCronExpr(mins, 59) === true;
  const hOk = checkCronExpr(hours, 23) === true;
  if (!mOk || !hOk) { el.textContent = '⚠️ Corrija os erros acima para ver o resumo'; return; }
  el.textContent = '⏰ ' + describeCron(mins, hours);
}

function describeCron(mins, hours) {
  return describeMinutes(mins) + ', ' + describeHours(hours) + ', todos os dias';
}

// Formata lista de valores com "e" antes do último: [0,30] → "0 e 30"
function joinList(items) {
  if (items.length === 1) return items[0];
  return items.slice(0, -1).join(', ') + ' e ' + items[items.length - 1];
}

function describeMinutes(v) {
  if (v === '*') return 'a cada minuto';
  const step = v.match(/^\*\/(\d+)$/);
  if (step) return `a cada ${step[1]} ${step[1] === '1' ? 'minuto' : 'minutos'}`;
  const parts = v.split(',');
  const labels = parts.map(p => {
    const r = p.match(/^(\d+)-(\d+)$/);
    return r ? `dos minutos ${r[1]} ao ${r[2]}` : null;
  });
  // Se tem algum range, retorna descritivo de range
  if (labels.some(l => l !== null)) {
    return labels.map((l, i) => l || `minuto ${parts[i]}`).join(', ');
  }
  // Lista simples de valores
  const vals = parts.map(p => `minuto ${p}`);
  if (vals.length === 1) return vals[0];
  // "nos minutos X, Y e Z"
  return 'nos minutos ' + joinList(parts);
}

function describeHours(v) {
  if (v === '*') return 'todas as horas';
  const step = v.match(/^\*\/(\d+)$/);
  if (step) return `a cada ${step[1]} ${step[1] === '1' ? 'hora' : 'horas'}`;
  const parts = v.split(',');
  const labels = parts.map(p => {
    const r = p.match(/^(\d+)-(\d+)$/);
    return r ? `das ${r[1]}h às ${r[2]}h` : null;
  });
  if (labels.some(l => l !== null)) {
    return labels.map((l, i) => l || `${parts[i]}h`).join(', ');
  }
  if (parts.length === 1) return `à${parseInt(parts[0]) !== 1 ? 's' : ''} ${parts[0]}h`;
  return 'às ' + joinList(parts.map(p => `${p}h`));
}

// ── Teste de envio Telegram ────────────────────────────────────
async function testTelegram() {
  const botToken = document.getElementById('botToken').value.trim();
  const chatId   = document.getElementById('chatId').value.trim();
  const resultEl = document.getElementById('testResult');
  if (!validateBotToken(document.getElementById('botToken')) || !validateChatId(document.getElementById('chatId'))) {
    showTestResult(false, 'Corrija os campos Bot Token e Chat ID antes de testar.');
    return;
  }
  resultEl.style.display = 'block';
  resultEl.className = 'test-result';
  resultEl.textContent = '⏳ Enviando mensagem de teste...';
  try {
    const r = await fetch('/telegram-test', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ botToken, chatId })
    });
    const d = await r.json();
    if (d.ok) showTestResult(true, '✅ Mensagem enviada com sucesso! Verifique seu Telegram.');
    else      showTestResult(false, '❌ Erro: ' + (d.message || 'Resposta inválida do Telegram'));
  } catch(e) { showTestResult(false, '❌ Falha na requisição: ' + e.message); }
}
function showTestResult(ok, text) {
  const el = document.getElementById('testResult');
  el.style.display = 'block';
  el.className = 'test-result ' + (ok ? 'test-ok' : 'test-fail');
  el.textContent = text;
}

// ── Schedule ───────────────────────────────────────────────────
function onScheduleChange() {
  const val = document.getElementById('alertSchedule').value;
  document.getElementById('customScheduleBox').style.display = val === 'custom' ? 'block' : 'none';
  if (val === 'custom') updateCronPreview();
}

function openTelegramModal() {
  fetch('/telegram-config').then(r=>r.json()).then(d=>{
    const botEl = document.getElementById('botToken');
    const cidEl = document.getElementById('chatId');
    botEl.value = d.botToken || '';
    cidEl.value = d.chatId  || '';
    if (d.botToken) validateBotToken(botEl);
    if (d.chatId)   validateChatId(cidEl);
    document.getElementById('testResult').style.display = 'none';
    const select    = document.getElementById('alertSchedule');
    const customBox = document.getElementById('customScheduleBox');
    if (d.schedule) {
      let found = false;
      for (let i=0; i<select.options.length; i++) {
        if (select.options[i].value === d.schedule) { select.selectedIndex = i; found = true; break; }
      }
      if (!found) {
        select.value = 'custom';
        const parts = d.schedule.split(' ');
        document.getElementById('customMinutes').value = parts[0] || '';
        document.getElementById('customHours').value   = parts[1] || '';
        customBox.style.display = 'block';
        sanitizeCronInput(document.getElementById('customMinutes'), 'min');
        sanitizeCronInput(document.getElementById('customHours'), 'hour');
      } else {
        customBox.style.display = 'none';
      }
    }
    document.getElementById('tgModal').style.display = 'flex';
  });
}

function closeTelegramModal() { document.getElementById('tgModal').style.display = 'none'; }

function saveTelegramConfig() {
  const botEl = document.getElementById('botToken');
  const cidEl = document.getElementById('chatId');
  const botToken = botEl.value.trim();
  const chatId   = cidEl.value.trim();
  if (!validateBotToken(botEl) || !validateChatId(cidEl)) {
    showTestResult(false, '⚠️ Corrija os erros antes de salvar.');
    return;
  }
  let schedule = document.getElementById('alertSchedule').value;
  if (schedule === 'custom') {
    const mins  = document.getElementById('customMinutes').value.trim();
    const hours = document.getElementById('customHours').value.trim();
    if (checkCronExpr(mins, 59) !== true || checkCronExpr(hours, 23) !== true) {
      showTestResult(false, '⚠️ Corrija os campos de minutos e horas antes de salvar.');
      return;
    }
    schedule = `${mins} ${hours} * * *`;
  }
  fetch('/telegram-config', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ botToken, chatId, schedule })
  }).then(() => { closeTelegramModal(); });
}
async function up(){
  try{
    const r=await fetch('/status');const d=await r.json();
    const st=document.getElementById('st');const lb=document.getElementById('lb');
    st.textContent=d.status==='connected'?'Online':'Offline';st.className='val '+(d.status==='connected'?'online':'offline');
    document.getElementById('q').textContent = d.queue || 0;
    const qri=document.getElementById('qri');if(d.qr){qri.src=d.qr;qri.style.display='inline'}else{qri.style.display='none'}
    if(d.status==='connected'){
      if(sc) {
        lb.innerHTML='';
        const logs = (d.sent??[]);
        const last10 = logs.slice(-10);
        if(last10.length === 0) lb.innerHTML='<div style="padding:20px;text-align:center;color:#999">Nenhum log disponível</div>';
        last10.forEach(l=>{
          const row=document.createElement('div');row.className='l-row';const tc=l.status==='sent'?'sent':'err';
          row.innerHTML=`<div class="l-meta"><span>${l.date}</span><span class="tag ${tc}">${l.status}</span></div><div class="msg">${l.mensagem}</div>`;
          lb.appendChild(row);
        });
        if(lb.lastChild) lb.scrollTop=lb.scrollHeight;
      }
    }else{lb.innerHTML='<div style="padding:20px;text-align:center;color:#999">Aguardando conexão...</div>'}
  }catch(e){}
}
setInterval(up,5000);up();
</script>
</body>
</html>
EOF
chown "$TARGET_USER":"$TARGET_USER" "$APP_DIR/public/index.html"

# 10. Debug
log "📝 Criando visualizador de logs..."
cat <<'EOF' > "$APP_DIR/public/log_viewer.html"
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Visualizador de Logs - WhatsApp API</title>
<style>
:root{--bg:#f0f2f5;--card:#ffffff;--primary:#075e54;--text:#1f2937;--border:#e5e7eb}
body{font-family:sans-serif;background:var(--bg);color:var(--text);margin:0;padding:20px}
.container{max-width:1000px;margin:0 auto;background:var(--card);border-radius:12px;border:1px solid var(--border);box-shadow:0 2px 4px rgba(0,0,0,0.05);overflow:hidden;display:flex;flex-direction:column;height:90vh}
.header{padding:15px 20px;background:var(--primary);color:white;display:flex;justify-content:space-between;align-items:center}
.header h1{margin:0;font-size:18px}.log-content{flex:1;overflow-y:auto;padding:15px;font-family:monospace;font-size:13px;line-height:1.5;white-space:pre-wrap;background:#1e1e1e;color:#d4d4d4}
.controls{padding:10px 20px;background:#f9fafb;border-top:1px solid var(--border);display:flex;gap:10px}
button{padding:8px 15px;border:none;border-radius:6px;background:#374151;color:white;cursor:pointer;font-size:13px}
.status-sent{color:#4ade80}.status-error{color:#f87171}
</style>
</head>
<body>
<div class="container">
  <div class="header"><h1>📜 Logs do Sistema (Arquivo)</h1><button onclick="window.close()">Fechar</button></div>
  <div id="logContent" class="log-content">Carregando logs...</div>
  <div class="controls"><button onclick="loadLogs()">Atualizar</button><button onclick="scrollToBottom()">Ir para o fim</button></div>
</div>
<script>
async function loadLogs(){
  const logDiv=document.getElementById('logContent');
  try{
    const r=await fetch('/logs-data');if(!r.ok)throw new Error('Erro ao carregar logs');
    const text=await r.text();
    const coloredText=text.replace(/\[SENT\]/g,'<span class="status-sent">[SENT]</span>').replace(/\[ERROR\]/g,'<span class="status-error">[ERROR]</span>');
    logDiv.innerHTML=coloredText||'Nenhum registro encontrado.';scrollToBottom();
  }catch(e){logDiv.textContent='Erro: '+e.message}
}
function scrollToBottom(){const logDiv=document.getElementById('logContent');logDiv.scrollTop=logDiv.scrollHeight}
loadLogs();setInterval(loadLogs,30000);
</script>
</body>
</html>
EOF
chown "$TARGET_USER":"$TARGET_USER" "$APP_DIR/public/log_viewer.html"

# 11. Script de Alerta Telegram
log "📝 Criando script de alerta Telegram..."
cat <<'EOF' > "$APP_DIR/telegram_alert.sh"
#!/bin/bash
CONFIG_FILE="$(dirname "$0")/telegram_config.json"
if [ ! -f "$CONFIG_FILE" ]; then exit 0; fi

BOT_TOKEN=$(grep -oP '"botToken":"\K[^"]+' "$CONFIG_FILE")
CHAT_ID=$(grep -oP '"chatId":"\K[^"]+' "$CONFIG_FILE")

if [ -z "$BOT_TOKEN" ] || [ -z "$CHAT_ID" ]; then exit 0; fi

API_URL="http://localhost:8000/status"
MSG="⚠️ WhatsApp Desconectado!"

STATUS=$(curl -s "$API_URL" | grep -o '"status":"[^"]*"' | cut -d'"' -f4 | head -1)

if [ "$STATUS" != "connected" ]; then
    curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/sendMessage" \
      -d "chat_id=${CHAT_ID}" \
      -d "text=${MSG}" > /dev/null
fi
EOF
chmod +x "$APP_DIR/telegram_alert.sh"
chown "$TARGET_USER":"$TARGET_USER" "$APP_DIR/telegram_alert.sh"

# 12. Configurar Cron para Alerta (Unificado no usuário da aplicação)
log "⏰ Configurando Cron inicial (a cada 4 horas) no usuário $TARGET_USER..."
crontab -l 2>/dev/null | grep -v "telegram_alert.sh" | crontab -
su - "$TARGET_USER" -c "(crontab -l 2>/dev/null | grep -v 'telegram_alert.sh'; echo '0 7,11,15 * * * /bin/bash $APP_DIR/telegram_alert.sh > /dev/null 2>&1') | crontab -"

# 13. PHP (exemplo de integração)
log "📝 Criando arquivo de exemplos..."
cat <<EOF > "$APP_DIR/public/exemplo.html"
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exemplos de Envio - WhatsApp API</title>
<style>
body{font-family:Arial,sans-serif;line-height:1.6;margin:0;padding:20px;background-color:#f4f4f4;color:#333}
.container{max-width:900px;margin:20px auto;background:#fff;padding:30px;border-radius:8px;box-shadow:0 0 10px rgba(0,0,0,0.1)}
h1,h2,h3{color:#075e54}pre{background-color:#eee;padding:15px;border-radius:5px;overflow-x:auto;font-family:monospace;font-size:0.9em}
.note{background-color:#fff3cd;border-left:5px solid #ffc107;padding:10px;margin:20px 0;border-radius:4px}
</style>
</head>
<body>
<div class="container">
<h1>Exemplos de Envio de Mensagens</h1>
<div class="note">Substitua <strong>${FIXED_TOKEN}</strong> pelo seu token e ajuste a URL se necessário.</div>
<h2>1. PHP</h2>
<pre><code>&lt;?php
\$api_url = 'http://localhost:${PORT}/send';
\$api_token = '${FIXED_TOKEN}';
\$data = ["numero" => "5511999999999", "mensagem" => "Teste via PHP"];
\$options = ['http' => ['header' => "Content-type: application/json\r\n" . "x-api-token: \$api_token\r\n", 'method' => 'POST', 'content' => json_encode(\$data)]];
\$context = stream_context_create(\$options);
echo file_get_contents(\$api_url, false, \$context);
?&gt;</code></pre>
<h2>2. Bash (curl)</h2>
<pre><code>curl -X POST -H "Content-Type: application/json" -H "x-api-token: ${FIXED_TOKEN}" -d '{"numero": "5511999999999", "mensagem": "Teste via Curl"}' http://localhost:${PORT}/send</code></pre>
</div>
</body>
</html>
EOF
chown "$TARGET_USER":"$TARGET_USER" "$APP_DIR/public/exemplo.html"

#Correção temporária
sed -i 's/proto.ClientPayload.UserAgent.Platform.WEB/proto.ClientPayload.UserAgent.Platform.MACOS/' $APP_DIR/node_modules/@whiskeysockets/baileys/lib/Utils/validate-connection.js

# 14. Iniciar com PM2 e configurar Startup
log "🚀 Iniciando a API com PM2..."
su - "$TARGET_USER" -c "cd $APP_DIR && pm2 start index.js --name $APP_NAME --silent"
su - "$TARGET_USER" -c "pm2 save --silent"

log "⚙️  Configurando PM2 para iniciar automaticamente no boot..."
STARTUP_CMD=$(su - "$TARGET_USER" -c "pm2 startup systemd" | grep "sudo" | sed 's/sudo //')
if [ -n "$STARTUP_CMD" ]; then
    eval "$STARTUP_CMD" >/dev/null 2>&1
fi

echo ""
log "✅ INSTALAÇÃO DA API WHATSAPP CONCLUÍDA!"
log "-------------------------------------------------------"
log "🌐 Abra a página para ler o QR Code e iniciar a sessão:"
log "   http://$(hostname -I | awk '{print $1}'):${PORT}"
log ""
log "📄 Exemplos de integração disponíveis em:"
log "   http://$(hostname -I | awk '{print $1}'):${PORT}/exemplo.html"
log ""
log "🔑 Token da API: ${FIXED_TOKEN}"
log "-------------------------------------------------------"
log "📝 Comandos úteis:"
log "   Ver status: pm2 status"
log "   Ver logs: pm2 logs $APP_NAME"
log "   Parar: pm2 stop $APP_NAME"
log "   Reiniciar: pm2 restart $APP_NAME"
log "-------------------------------------------------------"
echo ""