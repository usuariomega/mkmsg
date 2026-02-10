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
        
        preg_match('/\$ignorar_fds_feriado\s*=\s*(\d+)/', $content, $matches);
        $config['ignorar_fds_feriado'] = $matches[1] ?? '0';
        
        // Extrair arrays de dias
        preg_match('/\$diasnoprazo\s*=\s*\[(.*?)\]/', $content, $matches);
        $config['diasnoprazo'] = $matches[1] ?? '3';
        
        preg_match('/\$diasvencido\s*=\s*\[(.*?)\]/', $content, $matches);
        $config['diasvencido'] = $matches[1] ?? '3';
        
        preg_match('/\$diaspago\s*=\s*\[(.*?)\]/', $content, $matches);
        $config['diaspago'] = $matches[1] ?? '3';
        
        // Extrair horários
        preg_match('/\$horario_noprazo\s*=\s*"([^"]+)"/', $content, $matches);
        $config['horario_noprazo'] = $matches[1] ?? '09:00';
        
        preg_match('/\$horario_pago\s*=\s*"([^"]+)"/', $content, $matches);
        $config['horario_pago'] = $matches[1] ?? '10:00';

        preg_match('/\$horario_vencido\s*=\s*"([^"]+)"/', $content, $matches);
        $config['horario_vencido'] = $matches[1] ?? '08:00';

        preg_match('/\$horario_bloqueado\s*=\s*"([^"]+)"/', $content, $matches);
        $config['horario_bloqueado'] = $matches[1] ?? '08:30';
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
    $template = preg_replace('/\$ignorar_fds_feriado\s*=\s*\d+/', '$ignorar_fds_feriado = ' . (isset($data['ignorar_fds_feriado']) ? 1 : 0), $template);
    
    // Atualizar arrays de dias
    $diasnoprazo = array_map('intval', array_filter(array_map('trim', explode(',', $data['diasnoprazo']))));
    $diasvencido = array_map('intval', array_filter(array_map('trim', explode(',', $data['diasvencido']))));
    $diaspago = array_map('intval', array_filter(array_map('trim', explode(',', $data['diaspago']))));
    
    $template = preg_replace('/\$diasnoprazo\s*=\s*\[.*?\]/', '$diasnoprazo = [' . implode(', ', $diasnoprazo) . ']', $template);
    $template = preg_replace('/\$diasvencido\s*=\s*\[.*?\]/', '$diasvencido = [' . implode(', ', $diasvencido) . ']', $template);
    $template = preg_replace('/\$diaspago\s*=\s*\[.*?\]/', '$diaspago = [' . implode(', ', $diaspago) . ']', $template);
    
    // Atualizar horários
    $template = preg_replace('/\$horario_noprazo\s*=\s*"([^"]+)"/', '$horario_noprazo = "' . addslashes($data['horario_noprazo']) . '"', $template);
    $template = preg_replace('/\$horario_pago\s*=\s*"([^"]+)"/', '$horario_pago = "' . addslashes($data['horario_pago']) . '"', $template);
    $template = preg_replace('/\$horario_vencido\s*=\s*"([^"]+)"/', '$horario_vencido = "' . addslashes($data['horario_vencido']) . '"', $template);
    $template = preg_replace('/\$horario_bloqueado\s*=\s*"([^"]+)"/', '$horario_bloqueado = "' . addslashes($data['horario_bloqueado']) . '"', $template);
    
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

        <!-- SEÇÃO: CONDIÇÕES DE ENVIO -->
        <div class="card mb-3">
            <h3 style="color: var(--tertiary); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--border);">
                🛡️ Condições de Envio
            </h3>
            
            <div style="display: flex; align-items: center; gap: 15px; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6;">
                <div style="position: relative; display: inline-block; width: 60px; height: 34px;">
                    <input type="checkbox" name="ignorar_fds_feriado" id="ignorar_fds_feriado" <?= $config['ignorar_fds_feriado'] == '1' ? 'checked' : '' ?> style="opacity: 0; width: 0; height: 0;" onchange="updateToggleStyle(this)">
                    <label for="ignorar_fds_feriado" id="toggle-label" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: <?= $config['ignorar_fds_feriado'] == '1' ? 'var(--primary)' : '#ccc' ?>; transition: .4s; border-radius: 34px;">
                        <span id="toggle-span" style="position: absolute; content: ''; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; transform: <?= $config['ignorar_fds_feriado'] == '1' ? 'translateX(26px)' : 'translateX(0)' ?>;"></span>
                    </label>
                </div>
                <div>
                    <label for="ignorar_fds_feriado" style="font-weight: 600; color: var(--text-primary); cursor: pointer; display: block;">
                        Evitar envios em Finais de Semana e Feriados
                    </label>
                    <small style="color: var(--text-secondary);">
                        Se ativado, as mensagens que cairiam em sábado, domingo ou feriado serão ajustadas para o dia útil mais próximo (antecipado ou postergado).
                    </small>
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
                        ✅ Horário "Pago"
                    </label>
                    <input type="time" name="horario_pago" value="<?= htmlspecialchars($config['horario_pago']) ?>" class="form-input-full">
                </div>
                <div>
                    <label class="form-label">
                        ⚠️ Horário "Vencido"
                    </label>
                    <input type="time" name="horario_vencido" value="<?= htmlspecialchars($config['horario_vencido']) ?>" class="form-input-full">
                </div>
                <div>
                    <label class="form-label">
                        🚫 Horário "Bloqueado"
                    </label>
                    <input type="time" name="horario_bloqueado" value="<?= htmlspecialchars($config['horario_bloqueado']) ?>" class="form-input-full">
                </div>
            </div>
        </div>

        <!-- NOVO BALÃO: SIMULADOR DE NOTIFICAÇÕES -->
        <div class="card mb-3" style="border: 2px dashed var(--tertiary); background-color: var(--secondary-light);">
            <h3 style="color: var(--tertiary); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--tertiary);">
                🧪 Simulador de Notificações
            </h3>
            
            <p class="text-subtitle mb-3" style="font-size: 14px; color: var(--text-primary);">
                Escolha o cenário abaixo para simular a data de envio.
            </p>
            
            <div class="grid-2">
                <div>
                    <label class="form-label">Cenário de Simulação</label>
                    <select id="sim_tipo" class="form-input-full" onchange="simular()">
                        <optgroup label="📅 NO PRAZO">
                            <option value="noprazo_normal">No Prazo (Todos os dias)</option>
                            <option value="noprazo_util">No Prazo (Apenas dias úteis)</option>
                        </optgroup>
                        <optgroup label="⚠️ VENCIDO">
                            <option value="vencido_normal">Vencido (Todos os dias)</option>
                            <option value="vencido_util">Vencido (Apenas dias úteis)</option>
                        </optgroup>
                        <optgroup label="✅ PAGO">
                            <option value="pago_normal">Pago (Todos os dias)</option>
                            <option value="pago_util">Pago (Apenas dias úteis)</option>
                        </optgroup>
                        <optgroup label="🚫 BLOQUEADO">
                            <option value="bloqueado_normal">Bloqueado (Todos os dias)</option>
                            <option value="bloqueado_util">Bloqueado (Apenas dias úteis)</option>
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label class="form-label" id="label_sim_data">Data do Evento (Vencimento)</label>
                    <input type="date" id="sim_data" class="form-input-full" onchange="simular()" style="height: 48px; padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-md);">
                </div>
                <div>
                    <label class="form-label" id="label_sim_dias">Dias para Notificar (ex: 1, 3, 7)</label>
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
            <li><strong>Dias para o Bloqueio:</strong> O bloqueio vai ler o dia vencido somado ao dia de corte. (De acordo com o valor no financeiro do cliente).</li>
            <li><strong>Tempos de Pausa:</strong> O sistema aguardará um tempo aleatório entre o mínimo e máximo configurado antes de enviar cada mensagem.</li>
            <li><strong>Horários:</strong> O daemon verifica a cada minuto. Se for 09:00 e o horário configurado for 09:00, o envio será processado.</li>
        </ul>
    </div>
</div>

<script>
function updateToggleStyle(checkbox) {
    const label = document.getElementById('toggle-label');
    const span = document.getElementById('toggle-span');
    if (checkbox.checked) {
        label.style.backgroundColor = 'var(--primary)';
        span.style.transform = 'translateX(26px)';
    } else {
        label.style.backgroundColor = '#ccc';
        span.style.transform = 'translateX(0)';
    }
}

// Função para calcular feriados móveis
function getFeriados(ano) {
    const feriados = [
        `${ano}-01-01`, // Confraternização Universal
        `${ano}-04-21`, // Tiradentes
        `${ano}-05-01`, // Dia do Trabalho
        `${ano}-09-07`, // Independência do Brasil
        `${ano}-10-12`, // Nossa Senhora Aparecida
        `${ano}-11-02`, // Finados
        `${ano}-11-15`, // Proclamação da República
        `${ano}-11-20`, // Dia da Consciência Negra
        `${ano}-12-25`, // Natal
    ];

    // Cálculo da Páscoa (Algoritmo de Meeus/Jones/Butcher)
    const a = ano % 19;
    const b = Math.floor(ano / 100);
    const c = ano % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7;
    const m = Math.floor((a + 11 * h + 22 * l) / 451);
    const mes = Math.floor((h + l - 7 * m + 114) / 31);
    const dia = ((h + l - 7 * m + 114) % 31) + 1;
    
    const pascoa = new Date(ano, mes - 1, dia);
    
    const format = (d) => d.toISOString().split('T')[0];
    
    const carnaval = new Date(pascoa);
    carnaval.setDate(pascoa.getDate() - 47);
    
    const sextaSanta = new Date(pascoa);
    sextaSanta.setDate(pascoa.getDate() - 2);
    
    const corpusChristi = new Date(pascoa);
    corpusChristi.setDate(pascoa.getDate() + 60);
    
    feriados.push(format(carnaval), format(sextaSanta), format(corpusChristi));
    return feriados;
}

function isDiaUtil(data, feriados) {
    const diaSemana = data.getDay(); // 0 = Domingo, 6 = Sábado
    if (diaSemana === 0 || diaSemana === 6) return false;
    
    const dataStr = data.toISOString().split('T')[0];
    return !feriados.includes(dataStr);
}

function simular() {
    const cenario = document.getElementById('sim_tipo').value;
    const labelDias = document.getElementById('label_sim_dias');
    const labelData = document.getElementById('label_sim_data');
    
    // Ajuste dinâmico do rótulo de dias
    if (cenario.startsWith('bloqueado')) {
        labelDias.innerText = 'Dias para o corte (ex: 3)';
    } else {
        labelDias.innerText = 'Dias para Notificar (ex: 1, 3, 7)';
    }

    // Ajuste dinâmico do rótulo de data
    if (cenario.startsWith('pago')) {
        labelData.innerText = 'Data do Evento (Pagamento)';
    } else {
        labelData.innerText = 'Data do Evento (Vencimento)';
    }

    const dataStr = document.getElementById('sim_data').value;
    const diasStr = document.getElementById('sim_dias').value;
    const resultadoDiv = document.getElementById('sim_resultado');

    if (!dataStr || !diasStr) {
        resultadoDiv.innerHTML = '<span style="color: var(--text-light);">Aguardando dados...</span>';
        return;
    }

    const dataBase = new Date(dataStr + 'T00:00:00');
    const feriados = getFeriados(dataBase.getFullYear());
    const dias = diasStr.split(',').map(d => parseInt(d.trim())).filter(d => !isNaN(d));
    
    if (dias.length === 0) {
        resultadoDiv.innerHTML = '<span style="color: var(--danger);">Insira dias válidos.</span>';
        return;
    }

    const [tipo, modo] = cenario.split('_');
    const isUtil = (modo === 'util');

    let html = `<div style="font-weight: 600; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 5px; color: var(--primary);">Resultado da Simulação (${isUtil ? 'Dias Úteis' : 'Todos os dias'}):</div>`;
    
    dias.forEach(d => {
        let dataSimulada = new Date(dataBase);
        if (tipo === 'noprazo') {
            dataSimulada.setDate(dataBase.getDate() - d);
        } else {
            // Vencido, Pago e Bloqueado somam dias
            dataSimulada.setDate(dataBase.getDate() + d);
        }
        
        let statusMsg = "";
        let corStatus = "var(--text-secondary)";

        if (isUtil) {
            if (!isDiaUtil(dataSimulada, feriados)) {
                let diasAjustados = 0;
                if (tipo === 'noprazo') {
                    // Antecipa
                    while (!isDiaUtil(dataSimulada, feriados)) {
                        dataSimulada.setDate(dataSimulada.getDate() - 1);
                        diasAjustados++;
                    }
                    // Regra especial Segunda-feira
                    if (d === 1 && dataBase.getDay() === 1) {
                        dataSimulada = new Date(dataBase);
                        statusMsg = " (Mesmo dia, Segunda-feira)";
                    } else {
                        statusMsg = ` (Antecipado ${diasAjustados}d)`;
                    }
                } else {
                    // Posterga (Vencido, Pago, Bloqueado)
                    while (!isDiaUtil(dataSimulada, feriados)) {
                        dataSimulada.setDate(dataSimulada.getDate() + 1);
                        diasAjustados++;
                    }
                    statusMsg = ` (Postergado ${diasAjustados}d)`;
                }
                corStatus = "var(--danger)";
            }
        }

        const dataFormatada = dataSimulada.toLocaleDateString('pt-BR');
        const diaSemanaNome = dataSimulada.toLocaleDateString('pt-BR', { weekday: 'long' });
        
        let labelDias = "";
        if (tipo === 'noprazo') labelDias = `${d} dia(s) antes`;
        else if (tipo === 'bloqueado') labelDias = `Bloqueio (${d} dias após)`;
        else labelDias = `${d} dia(s) depois`;

        html += `<div style="margin-bottom: 8px; line-height: 1.4;">
                    <span style="font-size: 12px; color: #888;">${labelDias}:</span><br>
                    <b>${dataFormatada}</b> (${diaSemanaNome})
                    <span style="color: ${corStatus}; font-size: 11px; font-weight: bold;">${statusMsg}</span>
                 </div>`;
    });

    resultadoDiv.innerHTML = html;
}

// Inicializar com a data de hoje para facilitar
document.getElementById('sim_data').valueAsDate = new Date();
simular();
</script>

</body>
</html>
