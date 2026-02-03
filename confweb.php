<?php
include 'header.php';

$root = $_SERVER["DOCUMENT_ROOT"] . "/mkmsg";
$configFile = "$root/config.php";

// Função para ler o config.php e extrair as variáveis
function readConfig($file) {
    $config = [];
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Extrair variáveis usando regex
        preg_match('/\$provedor\s*=\s*"([^"]+)"/', $content, $matches);
        $config['provedor'] = $matches[1] ?? '';
        
        preg_match('/\$site\s*=\s*"([^"]+)"/', $content, $matches);
        $config['site'] = $matches[1] ?? '';
        
        preg_match('/\$wsip\s*=\s*"([^"]+)"/', $content, $matches);
        $config['wsip'] = $matches[1] ?? '';
        
        preg_match('/\$token\s*=\s*"([^"]+)"/', $content, $matches);
        $config['token'] = $matches[1] ?? '';
        
        preg_match('/\$tempomin\s*=\s*(\d+)/', $content, $matches);
        $config['tempomin'] = $matches[1] ?? '30';
        
        preg_match('/\$tempomax\s*=\s*(\d+)/', $content, $matches);
        $config['tempomax'] = $matches[1] ?? '120';
        
        // Extrair arrays de dias
        preg_match('/\$diasnoprazo\s*=\s*\[(.*?)\]/', $content, $matches);
        $config['diasnoprazo'] = $matches[1] ?? '3';
        
        preg_match('/\$diasvencido\s*=\s*\[(.*?)\]/', $content, $matches);
        $config['diasvencido'] = $matches[1] ?? '3';
        
        preg_match('/\$diaspago\s*=\s*\[(.*?)\]/', $content, $matches);
        $config['diaspago'] = $matches[1] ?? '3';
        
        // Extrair horários
        preg_match('/\$horario_vencido\s*=\s*"([^"]+)"/', $content, $matches);
        $config['horario_vencido'] = $matches[1] ?? '09:00';
        
        preg_match('/\$horario_noprazo\s*=\s*"([^"]+)"/', $content, $matches);
        $config['horario_noprazo'] = $matches[1] ?? '10:00';
        
        preg_match('/\$horario_pago\s*=\s*"([^"]+)"/', $content, $matches);
        $config['horario_pago'] = $matches[1] ?? '11:00';
    }
    return $config;
}

// Função para salvar as configurações
function saveConfig($file, $data) {
    $template = file_get_contents($file);
    
    // Atualizar variáveis simples
    $template = preg_replace('/\$provedor\s*=\s*"[^"]*"/', '$provedor = "' . addslashes($data['provedor']) . '"', $template);
    $template = preg_replace('/\$site\s*=\s*"[^"]*"/', '$site = "' . addslashes($data['site']) . '"', $template);
    $template = preg_replace('/\$wsip\s*=\s*"[^"]*"/', '$wsip = "' . addslashes($data['wsip']) . '"', $template);
    $template = preg_replace('/\$token\s*=\s*"[^"]*"/', '$token = "' . addslashes($data['token']) . '"', $template);
    $template = preg_replace('/\$tempomin\s*=\s*\d+/', '$tempomin = ' . (int)$data['tempomin'], $template);
    $template = preg_replace('/\$tempomax\s*=\s*\d+/', '$tempomax = ' . (int)$data['tempomax'], $template);
    
    // Atualizar arrays de dias
    $diasnoprazo = array_map('intval', array_filter(array_map('trim', explode(',', $data['diasnoprazo']))));
    $diasvencido = array_map('intval', array_filter(array_map('trim', explode(',', $data['diasvencido']))));
    $diaspago = array_map('intval', array_filter(array_map('trim', explode(',', $data['diaspago']))));
    
    $template = preg_replace('/\$diasnoprazo\s*=\s*\[.*?\]/', '$diasnoprazo = [' . implode(', ', $diasnoprazo) . ']', $template);
    $template = preg_replace('/\$diasvencido\s*=\s*\[.*?\]/', '$diasvencido = [' . implode(', ', $diasvencido) . ']', $template);
    $template = preg_replace('/\$diaspago\s*=\s*\[.*?\]/', '$diaspago = [' . implode(', ', $diaspago) . ']', $template);
    
    // Atualizar horários
    $template = preg_replace('/\$horario_vencido\s*=\s*"([^"]+)"/', '$horario_vencido = "' . addslashes($data['horario_vencido']) . '"', $template);
    $template = preg_replace('/\$horario_noprazo\s*=\s*"([^"]+)"/', '$horario_noprazo = "' . addslashes($data['horario_noprazo']) . '"', $template);
    $template = preg_replace('/\$horario_pago\s*=\s*"([^"]+)"/', '$horario_pago = "' . addslashes($data['horario_pago']) . '"', $template);
    
    return file_put_contents($file, $template) !== false;
}

$message = '';
$messageType = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (saveConfig($configFile, $_POST)) {
        $message = '✅ Configurações salvas com sucesso!';
        $messageType = 'success';
    } else {
        $message = '❌ Erro ao salvar as configurações.';
        $messageType = 'error';
    }
}

$config = readConfig($configFile);
?>

<div class="container">
    <!-- Cabeçalho da Página -->
    <div class="card mb-3">
        <h2 class="title-config">
            ⚙️ Configurações do Sistema
        </h2>
        <p class="text-subtitle">
            Gerencie todas as configurações do MK-MSG: provedor, API, horários e dias de envio.
        </p>
    </div>

    <!-- Menu de Navegação -->
    <div class="menu card mb-3">
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <button class="button3" onclick="location.href='index.php'" type="button">📅 No prazo</button>
            <button class="button3" onclick="location.href='vencido.php'" type="button">⚠️ Vencidos</button>
            <button class="button3" onclick="location.href='pago.php'" type="button">✅ Pagos</button>
            <button class="button3" onclick="location.href='emmassa.php'" type="button">📢 Em massa</button>
            <button class="button3" onclick="location.href='confmsg.php'" type="button">💬 Conf. msg</button>
            <button class="button2" onclick="location.href='confweb.php'" type="button" style="background-color: var(--secondary); border: 2px solid var(--secondary);">⚙️ Conf. geral</button>
        </div>
    </div>

    <!-- Mensagem de Sucesso/Erro -->
    <?php if ($message): ?>
        <div class="card mb-3" style="background-color: <?= $messageType === 'success' ? '#d4edda' : '#f8d7da' ?>; border-left: 4px solid <?= $messageType === 'success' ? '#28a745' : '#dc3545' ?>;">
            <p style="color: <?= $messageType === 'success' ? '#155724' : '#721c24' ?>; margin: 0; font-weight: 600;">
                <?= $message ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Formulário de Configurações -->
    <form method="POST">
        <input type="hidden" name="save_config" value="1">
        <!-- SEÇÃO: INFORMAÇÕES DO PROVEDOR -->
        <div class="card mb-3">
            <h3 style="color: var(--primary); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--border);">
                📊 Informações do Provedor
            </h3>
            
            <div class="grid-2">
                <div>
                    <label class="form-label">
                        Nome do Provedor
                    </label>
                    <input type="text" name="provedor" value="<?= htmlspecialchars($config['provedor']) ?>" required class="form-input-full">
                </div>
                <div>
                    <label class="form-label">
                        Site/URL
                    </label>
                    <input type="text" name="site" value="<?= htmlspecialchars($config['site']) ?>" required class="form-input-full">
                </div>
            </div>
        </div>

        <!-- SEÇÃO: CONFIGURAÇÕES DA API -->
        <div class="card mb-3">
            <h3 style="color: var(--secondary); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--border);">
                🔌 Configurações da API WhatsApp
            </h3>
            
            <div class="grid-2">
                <div>
                    <label class="form-label">
                        IP da API
                    </label>
                    <input type="text" name="wsip" value="<?= htmlspecialchars($config['wsip']) ?>" required class="form-input-full">
                </div>
                <div>
                    <label class="form-label">
                        Token de Autenticação
                    </label>
                    <input type="text" name="token" value="<?= htmlspecialchars($config['token']) ?>" required class="form-input-full">
                </div>
            </div>
        </div>

        <!-- SEÇÃO: TEMPOS DE PAUSA -->
        <div class="card mb-3">
            <h3 style="color: var(--danger); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--border);">
                ⏱️ Tempos de Pausa Entre Envios
            </h3>
            
            <div class="grid-2">
                <div>
                    <label class="form-label">
                        Tempo Mínimo (segundos)
                    </label>
                    <input type="number" name="tempomin" value="<?= htmlspecialchars($config['tempomin']) ?>" required class="form-input-full">
                </div>
                <div>
                    <label class="form-label">
                        Tempo Máximo (segundos)
                    </label>
                    <input type="number" name="tempomax" value="<?= htmlspecialchars($config['tempomax']) ?>" required class="form-input-full">
                </div>
            </div>
        </div>

        <!-- SEÇÃO: DIAS DE ENVIO -->
        <div class="card mb-3">
            <h3 style="color: var(--primary); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--border);">
                📆 Dias de Envio (separados por vírgula)
            </h3>
            
            <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label class="form-label">
                        📅 Dias para "No Prazo" (ex: 3, 7, 15)
                    </label>
                    <input type="text" name="diasnoprazo" value="<?= htmlspecialchars($config['diasnoprazo']) ?>" placeholder="3, 7, 15" class="form-input-full">
                </div>
                <div>
                    <label class="form-label">
                        ⚠️ Dias para "Vencido" (ex: 1, 10, 15)
                    </label>
                    <input type="text" name="diasvencido" value="<?= htmlspecialchars($config['diasvencido']) ?>" placeholder="1, 10, 15" class="form-input-full">
                </div>
                <div>
                    <label class="form-label">
                        ✅ Dias para "Pago" (ex: 1)
                    </label>
                    <input type="text" name="diaspago" value="<?= htmlspecialchars($config['diaspago']) ?>" placeholder="1" class="form-input-full">
                </div>
            </div>
        </div>

        <!-- SEÇÃO: HORÁRIOS DE ENVIO -->
        <div class="card mb-3">
            <h3 style="color: var(--secondary); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--border);">
                🕐 Horários de Envio (Daemon)
            </h3>
            
            <p class="text-subtitle mb-3" style="font-size: 14px;">
                Configure os horários em que o daemon automático enviará as mensagens. O daemon verifica a cada minuto se chegou a hora configurada.
            </p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label class="form-label">
                        📅 Horário "No Prazo"
                    </label>
                    <input type="time" name="horario_noprazo" value="<?= htmlspecialchars($config['horario_noprazo']) ?>" class="form-input-full">
                </div>
                <div>
                    <label class="form-label">
                        ⚠️ Horário "Vencido"
                    </label>
                    <input type="time" name="horario_vencido" value="<?= htmlspecialchars($config['horario_vencido']) ?>" class="form-input-full">
                </div>
                <div>
                    <label class="form-label">
                        ✅ Horário "Pago"
                    </label>
                    <input type="time" name="horario_pago" value="<?= htmlspecialchars($config['horario_pago']) ?>" class="form-input-full">
                </div>
            </div>
        </div>

        <!-- NOVO BALÃO: SIMULADOR DE NOTIFICAÇÕES -->
        <div class="card mb-3" style="border: 2px dashed var(--tertiary); background-color: var(--secondary-light);">
            <h3 style="color: var(--tertiary); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--tertiary);">
                🧪 Simulador de Notificações
            </h3>
            
            <p class="text-subtitle mb-3" style="font-size: 14px; color: var(--text-primary);">
                Simule quando uma notificação será enviada com base na data do título e nos dias configurados para automação.
            </p>
            
            <div class="grid-2">
                <div>
                    <label class="form-label">Tipo de Título</label>
                    <select id="sim_tipo" class="form-input-full" onchange="simular()">
                        <option value="noprazo">📅 No Prazo (Antes do Vencimento)</option>
                        <option value="vencido">⚠️ Vencido (Após o Vencimento)</option>
                        <option value="pago">✅ Pago (Após o Pagamento)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Data do Evento (Vencimento ou Pagamento)</label>
                    <input type="date" id="sim_data" class="form-input-full" onchange="simular()" style="height: 48px; padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-md);">
                </div>
                <div>
                    <label class="form-label">Dias para Notificar (ex: 3, 7)</label>
                    <input type="text" id="sim_dias" class="form-input-full" placeholder="Ex: 1, 5, 10" oninput="simular()">
                </div>
                <div id="sim_resultado_container" style="display: flex; align-items: flex-end;">
                    <div id="sim_resultado" style="width: 100%; padding: 12px; border-radius: var(--radius-md); background: white; border: 1px solid var(--border); min-height: 48px; font-size: 14px; display: flex; flex-direction: column; justify-content: center;">
                        <span style="color: var(--text-light);">Aguardando dados...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botão de Salvar -->
        <div class="menu mt-3">
            <button type="submit" class="button" style="background-color: var(--primary); border: 2px solid var(--primary); min-width: 300px; font-size: 16px; padding: 14px 32px;">
                💾 SALVAR CONFIGURAÇÕES
            </button>
            <button type="button" class="button3" onclick="location.href='index.php'">
                ← Voltar
            </button>
        </div>
    </form>

    <!-- Informações Adicionais -->
    <div class="card mt-3" style="background-color: #f0f4f8; border-left: 4px solid var(--tertiary);">
        <h4 style="color: var(--tertiary); margin-bottom: 12px;">
            💡 Dicas Importantes
        </h4>
        <ul style="color: var(--text-secondary); margin-left: 20px; line-height: 1.8;">
            <li><strong>Dias de Envio:</strong> Separe os dias por vírgula. Exemplo: "1, 10" enviará mensagens 1 e 10 dias antes/após o vencimento.</li>
            <li><strong>Tempos de Pausa:</strong> O sistema aguardará um tempo aleatório entre o mínimo e máximo configurado antes de enviar cada mensagem.</li>
            <li><strong>Horários:</strong> O daemon verifica a cada minuto. Se for 09:00 e o horário configurado for 09:00, o envio será processado.</li>
        </ul>
    </div>
</div>

<script>
function simular() {
    const tipo = document.getElementById('sim_tipo').value;
    const dataStr = document.getElementById('sim_data').value;
    const diasStr = document.getElementById('sim_dias').value;
    const resultadoDiv = document.getElementById('sim_resultado');

    if (!dataStr || !diasStr) {
        resultadoDiv.innerHTML = '<span style="color: var(--text-light);">Aguardando dados...</span>';
        return;
    }

    const dataBase = new Date(dataStr + 'T00:00:00');
    const dias = diasStr.split(',').map(d => parseInt(d.trim())).filter(d => !isNaN(d));
    
    if (dias.length === 0) {
        resultadoDiv.innerHTML = '<span style="color: var(--danger);">Insira dias válidos.</span>';
        return;
    }

    let html = '<div style="font-weight: 600; margin-bottom: 5px;">Datas de Notificação:</div>';
    
    dias.forEach(d => {
        let dataSimulada = new Date(dataBase);
        if (tipo === 'noprazo') {
            // No prazo: subtrai os dias do vencimento
            dataSimulada.setDate(dataBase.getDate() - d);
        } else {
            // Vencido ou Pago: soma os dias ao evento
            dataSimulada.setDate(dataBase.getDate() + d);
        }
        
        const dataFormatada = dataSimulada.toLocaleDateString('pt-BR');
        html += `<div>• ${d} dia(s) ${tipo === 'noprazo' ? 'antes' : 'depois'}: <b>${dataFormatada}</b></div>`;
    });

    resultadoDiv.innerHTML = html;
}

// Inicializar com a data de hoje para facilitar
document.getElementById('sim_data').valueAsDate = new Date();
</script>

</body>
</html>
