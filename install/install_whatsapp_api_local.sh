#!/bin/bash
# ====================================
# WhatsApp API - Script de Instalação 
# ====================================

set -e

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

# Verificar se está sendo instalado em Devuan (MK-Auth)
if grep -qi "devuan" /etc/os-release; then
    error "INSTALAÇÃO CANCELADA: Este sistema não pode ser instalado dentro do MK-Auth. Use o MK-MSG em uma máquina separada."
fi

# Detectar o usuário que chamou o script (se foi com sudo)
if [ -n "$SUDO_USER" ]; then
    TARGET_USER="$SUDO_USER"
    TARGET_HOME=$(eval echo ~$SUDO_USER)
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
        echo "     Opção A (recomendado - sem sudo):"
        echo "     cat /var/www/html/mkmsg/config.php | grep token"
        echo ""
        echo "     Opção B (com sudo):"
        echo "     sudo grep token /var/www/html/mkmsg/config.php"
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
sudo -u "$TARGET_USER" pm2 delete "$APP_NAME" >/dev/null 2>&1 || true
sudo -u "$TARGET_USER" rm -rf "$APP_DIR"

# 2. Sistema - Instalar dependências globais com sudo
log "🚀 Instalando dependências do sistema..."
apt-get update -qq
apt-get install -y -qq curl git ca-certificates build-essential >/dev/null

# 3. Node.js & PM2
if ! command -v node >/dev/null; then
    log "🌐 Instalando Node.js $NODE_VERSION..."
    curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - >/dev/null
    apt-get install -y -qq nodejs >/dev/null
fi

if ! command -v pm2 >/dev/null; then
    log "📦 Instalando PM2 globalmente..."
    npm install -g pm2 -s
fi

# 4. Estrutura - Criar diretórios locais do usuário
log "📁 Criando estrutura de diretórios..."
sudo -u "$TARGET_USER" mkdir -p "$APP_DIR"/{auth,logs,public}

# 5. package.json
log "📝 Configurando dependências do projeto..."
sudo -u "$TARGET_USER" tee "$APP_DIR/package.json" > /dev/null <<EOF
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

log "💾 Instalando dependências do projeto (npm install)..."
sudo -u "$TARGET_USER" bash -c "cd $APP_DIR && npm install --quiet --no-fund --no-audit 2>&1 | grep -v 'npm notice' | grep -v 'npm warn' || true"

# 6. Configuração
log "⚙️  Criando arquivo de configuração..."
sudo -u "$TARGET_USER" tee "$APP_DIR/config.js" > /dev/null <<EOF
export const API_TOKEN = "${FIXED_TOKEN}"
export const MESSAGE_DELAY = 3000
export const PORT = ${PORT}
EOF

# 7. queue.js
log "📝 Criando gerenciador de fila..."
sudo -u "$TARGET_USER" tee "$APP_DIR/queue.js" > /dev/null <<'EOF'
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

# 8. index.js
log "📝 Criando servidor Express..."
sudo -u "$TARGET_USER" tee "$APP_DIR/index.js" > /dev/null <<'EOF'
import express from 'express'
import makeWASocket, { useMultiFileAuthState, DisconnectReason } from '@whiskeysockets/baileys'
import QRCode from 'qrcode'
import fs from 'fs'
import pino from 'pino'
import { API_TOKEN, PORT } from './config.js'
import { addToQueue, getQueueSize, getSentLogs } from './queue.js'

const app = express()
app.use(express.json())
app.use(express.static('public'))

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
    logger: pino({ level: 'silent' }),
    browser: ['macOS', 'Chrome', '144.0.7559.96']
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

app.get('/status', (req, res) => res.json({ status, qr: qrBase64, queue: getQueueSize(), sent: getSentLogs() }))
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

app.listen(PORT, () => console.log(`🚀 Servidor na porta ${PORT}`))
connectToWhatsApp()
EOF

# 9. Dashboard (HTML)
log "📝 Criando dashboard web..."
sudo -u "$TARGET_USER" tee "$APP_DIR/public/index.html" > /dev/null <<'EOF'
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>WhatsApp API Dashboard</title>
<style>
:root{--bg:#f0f2f5;--card:#ffffff;--green:#25d366;--red:#ef4444;--border:#e5e7eb;--text:#1f2937;--muted:#6b7280;--primary:#075e54}
*{box-sizing:border-box}html,body{width:100%;overflow-x:hidden;margin:0;padding:0}
body{font-family:sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;min-height:100vh}
.container{width:100%;max-width:900px;margin:0 auto;padding:15px;flex:1;display:flex;flex-direction:column}
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
button{padding:6px 12px;border:none;border-radius:6px;background:#374151;color:#fff;cursor:pointer;font-size:12px}
</style>
</head>
<body>
<div class="container">
  <div class="header"><div class="title">📲 WhatsApp API Dashboard</div></div>
  <div class="stats"><div class="card"><span class="label">Status</span><div id="st" class="val">---</div></div><div class="card"><span class="label">Fila de Envio</span><div id="q" class="val">0</div></div></div>
  <div class="qr"><img id="qri" style="display:none"></div>
  <div class="logs"><div class="l-head"><span>📜 Logs de Atividade</span><button id="ps">⏸ Pausar</button></div><div id="lb" class="l-body"></div></div>
</div>
<script>
let sc=true;const ps=document.getElementById('ps');ps.onclick=()=>{sc=!sc;ps.textContent=sc?'⏸ Pausar':'▶ Retomar'};
async function up(){
  try{
    const r=await fetch('/status');const d=await r.json();
    const st=document.getElementById('st');const lb=document.getElementById('lb');
    st.textContent=d.status==='connected'?'Online':'Offline';st.className='val '+(d.status==='connected'?'online':'offline');
    document.getElementById('q').textContent = d.queue || 0;
    const qri=document.getElementById('qri');if(d.qr){qri.src=d.qr;qri.style.display='inline'}else{qri.style.display='none'}
    if(d.status==='connected'){
      lb.innerHTML='';if((d.sent??[]).length === 0) lb.innerHTML='<div style="padding:20px;text-align:center;color:#999">Nenhum log disponível</div>';
      (d.sent??[]).forEach(l=>{
        const row=document.createElement('div');row.className='l-row';const tc=l.status==='sent'?'sent':'err';
        row.innerHTML=`<div class="l-meta"><span>${l.date}</span><span class="tag ${tc}">${l.status}</span></div><div class="msg">${l.mensagem}</div>`;
        lb.appendChild(row);
      });
    }else{lb.innerHTML='<div style="padding:20px;text-align:center;color:#999">Aguardando conexão...</div>'}
    if(sc&&lb.lastChild)lb.scrollTop=lb.scrollHeight;
  }catch(e){}
}
setInterval(up,5000);up();
</script>
</body>
</html>
EOF

# 10. Debug
log "📝 Criando script de debug..."
sudo -u "$TARGET_USER" tee "$APP_DIR/debug.sh" > /dev/null <<EOF
#!/bin/bash
echo "--- STATUS PM2 ---"
pm2 status $APP_NAME
echo ""
echo "--- ÚLTIMOS LOGS (ARQUIVO) ---"
ls -t logs/*.log | head -n 1 | xargs tail -n 20
EOF
sudo -u "$TARGET_USER" chmod +x "$APP_DIR/debug.sh"

# 11. PHP (exemplo de integração)
log "📝 Criando exemplo de integração PHP..."
sudo -u "$TARGET_USER" tee "$APP_DIR/exemplo.php" > /dev/null <<EOF
<?php
\$api_url = 'http://localhost:${PORT}/send';
\$api_token = '${FIXED_TOKEN}';
\$data = ["numero" => "5511999999999", "mensagem" => "Teste 1 2 3"];
\$options = ['http' => ['header' => "Content-type: application/json\r\n" . "x-api-token: \$api_token\r\n", 'method' => 'POST', 'content' => json_encode(\$data), 'ignore_errors' => true]];
\$context = stream_context_create(\$options);
\$result = file_get_contents(\$api_url, false, \$context);
echo "Resposta: " . \$result . PHP_EOL;
?>
EOF

# 12. Finalização - PM2 com sudo apenas para startup
log "🚀 Iniciando com PM2..."
sudo -u "$TARGET_USER" pm2 start "$APP_DIR/index.js" --name "$APP_NAME" --silent
sudo -u "$TARGET_USER" pm2 save --silent

log "⚙️  Configurando PM2 para iniciar automaticamente no boot..."
pm2 startup systemd -u "$TARGET_USER" --hp "$TARGET_HOME" --silent

log "✅ INSTALAÇÃO DA API WHATSAPP CONCLUÍDA!"
log "-------------------------------------------------------"
log "📁 Diretório: $APP_DIR"
log "🌐 URL: http://localhost:${PORT}"
log "📊 Dashboard: http://localhost:${PORT}/"
log "🔑 Token: ${FIXED_TOKEN}"
log "👤 Usuário: $TARGET_USER"
log "-------------------------------------------------------"
log "📝 Comandos úteis:"
log "   Ver status: pm2 status"
log "   Ver logs: pm2 logs $APP_NAME"
log "   Parar: pm2 stop $APP_NAME"
log "   Reiniciar: pm2 restart $APP_NAME"
log "   Remover: pm2 delete $APP_NAME"
log "-------------------------------------------------------"
