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
    res.json({ botToken: '', chatId: '' })
  }
})

app.post('/telegram-config', (req, res) => {
  const { botToken, chatId } = req.body
  fs.writeFileSync(TELEGRAM_CONFIG_FILE, JSON.stringify({ botToken, chatId }))
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
.modal-content{background:#fff;padding:20px;border-radius:12px;width:90%;max-width:400px;box-shadow:0 4px 12px rgba(0,0,0,0.15)}
.modal-content h3{margin-top:0;color:var(--primary)}
.modal-content input{width:100%;padding:10px;margin:10px 0;border:1px solid var(--border);border-radius:6px}
.modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:15px}
.btn-save{background:var(--green)}
.btn-cancel{background:var(--muted)}
</style>
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
    <p style="font-size:12px; color:var(--muted); margin-bottom:15px;">
      Configure abaixo para receber uma notificação automática no seu Telegram caso o status do WhatsApp mude de <b>Online</b> para <b>Offline</b>.
    </p>
    <label class="label">BOT TOKEN</label>
    <input type="text" id="botToken" placeholder="Ex: 123456:ABCDEF...">
    <label class="label">CHAT ID</label>
    <input type="text" id="chatId" placeholder="Ex: -123456789">
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
function openTelegramModal() {
  fetch('/telegram-config').then(r=>r.json()).then(d=>{
    document.getElementById('botToken').value = d.botToken || '';
    document.getElementById('chatId').value = d.chatId || '';
    document.getElementById('tgModal').style.display = 'flex';
  });
}
function closeTelegramModal() { document.getElementById('tgModal').style.display = 'none'; }
function saveTelegramConfig() {
  const botToken = document.getElementById('botToken').value;
  const chatId = document.getElementById('chatId').value;
  fetch('/telegram-config', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ botToken, chatId })
  }).then(() => { alert('Configuração salva!'); closeTelegramModal(); });
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

# 12. Configurar Cron para Alerta
log "⏰ Configurando Cron para alertas (a cada 4 horas)..."
(crontab -l 2>/dev/null | grep -v "telegram_alert.sh"; echo "0 7,11,15 * * * /bin/bash $APP_DIR/telegram_alert.sh > /dev/null 2>&1") | crontab -

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
