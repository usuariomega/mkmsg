<?php
if (isset($_POST['ajax_send']) || isset($_POST['get_all_ids'])) {
    ob_start();
    include 'header.php';
    ob_clean();
    
    if (isset($_POST['ajax_send'])) {
        $contato = $_POST['contato'];
        $db = new SQLite3('db/msgdb.sqlite3');
        $msgvencido = $db->querySingle("SELECT msg FROM msgvencido");
        $db->close();
        
        $nome = isset($contato['nome_res']) ? $contato['nome_res'] : 'N/A';
        $celular = isset($contato['celular']) ? $contato['celular'] : '';
        $datavenc = isset($contato['datavenc']) ? $contato['datavenc'] : '';
        $linhadig = isset($contato['linhadig']) ? $contato['linhadig'] : '';
        $qrcode = isset($contato['qrcode']) ? $contato['qrcode'] : '';

        $buscar = array('/%provedor%/', '/%nomeresumido%/', '/%vencimento%/', '/%linhadig%/', '/%copiacola%/', '/%site%/');
        $substituir = array($provedor, $nome, $datavenc, $linhadig, $qrcode, $site);
        $msgFinal = preg_replace($buscar, $substituir, $msgvencido);

        $payload = ["numero" => "55" . $celular, "mensagem" => $msgFinal];
        $ch = curl_init("http://$wsip:8000/send");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "x-api-token: $token"],
            CURLOPT_TIMEOUT => 10
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        // Validação da resposta da API
        $apiSuccess = false;
        if (!$error) {
            $resData = json_decode($response, true);
            if (isset($resData['status']) && ($resData['status'] === 'sent' || $resData['status'] === true)) {
                $apiSuccess = true;
            } elseif (isset($resData['key'])) {
                $apiSuccess = true;
            }
        }

        $month = date("Y-m");
        $root = $_SERVER["DOCUMENT_ROOT"] . "/mkmsg";
        $dir = "$root/logs/$month/vencido";
        if (!is_dir($dir)) { mkdir($dir, 0755, true); }

        $logFile = "$dir/vencido_" . date("d-M-Y") . ".log";
        $logData = sprintf("%s;%s;%s;%s\n", date("d-m-Y"), date("H:i:s"), $nome, $error ?: $response);
        file_put_contents($logFile, $logData, FILE_APPEND);

        header('Content-Type: application/json');
        echo json_encode(["success" => $apiSuccess, "nome" => $nome, "response" => $error ?: $response]);
        exit;
    }

    if (isset($_POST['get_all_ids'])) {
        $conn = new mysqli($servername, $username, $password, $dbname);
        $valorsel = $_GET['menumes'];
        $sql_todos = "SELECT upper(vtab_titulos.nome_res) as nome_res, REGEXP_REPLACE(vtab_titulos.celular,'[( )-]+','') AS celular, 
                      DATE_FORMAT(vtab_titulos.datavenc,'%d/%m/%y') AS datavenc, 
                      vtab_titulos.linhadig, sis_qrpix.qrcode 
                      FROM vtab_titulos 
                      INNER JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo 
                      WHERE DATE_FORMAT(datavenc,'%m-%Y') = ? AND vtab_titulos.status = 'vencido' AND vtab_titulos.cli_ativado = 's'
                      GROUP BY vtab_titulos.uuid_lanc
                      ORDER BY nome_res ASC";
        $stmt_todos = $conn->prepare($sql_todos);
        $stmt_todos->bind_param("s", $valorsel);
        $stmt_todos->execute();
        $res_todos = $stmt_todos->get_result();
        $todos = [];
        while ($row = $res_todos->fetch_assoc()) { $todos[] = $row; }
        header('Content-Type: application/json');
        echo json_encode($todos);
        exit;
    }
}

include 'header.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Configurações de Filtro e Ordenação
$mesAtual = date("m-Y");
$valorsel = isset($_GET['menumes']) ? $_GET['menumes'] : $mesAtual;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'nome_res';
$order_dir = isset($_GET['order_dir']) && $_GET['order_dir'] == 'desc' ? 'desc' : 'asc';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Conexão e Busca de Dados
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Erro de conexão: " . $conn->connect_error); }

$where_clause = "WHERE DATE_FORMAT(datavenc,'%m-%Y') = ? AND vtab_titulos.status = 'vencido' AND vtab_titulos.cli_ativado = 's'";
if (!empty($search)) {
    $where_clause .= " AND (vtab_titulos.nome_res LIKE ? OR vtab_titulos.celular LIKE ?)";
}

$count_sql = "SELECT COUNT(DISTINCT vtab_titulos.uuid_lanc) as total FROM vtab_titulos $where_clause";
$stmt_count = $conn->prepare($count_sql);
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt_count->bind_param("sss", $valorsel, $search_param, $search_param);
} else {
    $stmt_count->bind_param("s", $valorsel);
}
$stmt_count->execute();
$total_registros = $stmt_count->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $limit);

$sql = "SELECT vtab_titulos.uuid_lanc, upper(vtab_titulos.nome_res) as nome_res, REGEXP_REPLACE(vtab_titulos.celular,'[( )-]+','') AS celular, 
        DATE_FORMAT(vtab_titulos.datavenc,'%d/%m/%y') AS datavenc, 
        vtab_titulos.linhadig, sis_qrpix.qrcode 
        FROM vtab_titulos 
        INNER JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo 
        $where_clause 
        GROUP BY vtab_titulos.uuid_lanc
        ORDER BY $order_by $order_dir 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $stmt->bind_param("sssii", $valorsel, $search_param, $search_param, $limit, $offset);
} else {
    $stmt->bind_param("sii", $valorsel, $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$dados_pagina = [];
while ($row = $result->fetch_assoc()) {
    $dados_pagina[] = $row;
}
?>

<div class="container">
    <div class="card mb-3">
        <h2 class="title-vencido">⚠️ Títulos Vencidos</h2>
        <p class="text-subtitle">
            Total de registros no mês: <strong><?= $total_registros ?></strong> | Selecionados: <strong id="selected-count">0</strong>
            <button type="button" class="button3 btn-small" onclick="clearSelection()">Limpar Seleção</button>
        </p>
    </div>

    <div class="menu card mb-3">
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <button class="button3" onclick="location.href='index.php'" type="button">📅 No prazo</button>
            <button class="button2" onclick="location.href='vencido.php'" type="button" style="background-color: var(--danger); border: 2px solid var(--danger);">⚠️ Vencidos</button>
            <button class="button3" onclick="location.href='pago.php'" type="button">✅ Pagos</button>
            <button class="button3" onclick="location.href='confmsg.php'" type="button">💬 Conf. msg</button>
            <button class="button3" onclick="location.href='confweb.php'" type="button">⚙️ Conf. geral</button>
        </div>

        <form id="formmes" method="get" style="display: flex; gap: 10px; align-items: center;">
            <select name="menumes" class="selectmes" onchange="this.form.submit()" required>
                <option value="">📆 Mês</option>
                <?php
                    for ($i = -5; $i <= 5; $i++) {
                        $v = date("m-Y", strtotime("first day of $i months"));
                        $sel = ($v == $valorsel) ? "selected" : "";
                        echo "<option value=\"$v\" $sel>$v</option>";
                    }
                ?>
            </select>
        </form>
    </div>

    <div class="card mb-3">
        <form method="get" class="search-container">
            <input type="hidden" name="menumes" value="<?= $valorsel ?>">
            <input type="hidden" name="order_by" value="<?= $order_by ?>">
            <input type="hidden" name="order_dir" value="<?= $order_dir ?>">
            
            <select name="limit" onchange="this.form.submit()" class="select-limit">
                <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
            </select>
            
            <input type="text" name="search" class="resp-search" placeholder="Buscar nome ou celular..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="button btn-search">🔍</button>
        </form>
    </div>

    <form id="form" method="post">
        <input type="hidden" name="selected_data" id="selected_data">
        
        <div class="card p-0 overflow-x-auto">
            <table class="custom-table w-100">
                <thead>
                    <tr>
                        <th><a href="?menumes=<?= $valorsel ?>&search=<?= $search ?>&limit=<?= $limit ?>&order_by=nome_res&order_dir=<?= ($order_by == 'nome_res' && $order_dir == 'asc') ? 'desc' : 'asc' ?>">NOME <?= $order_by == 'nome_res' ? ($order_dir == 'asc' ? '↑' : '↓') : '' ?></a></th>
                        <th class="hide-mobile">CELULAR</th>
                        <th><a href="?menumes=<?= $valorsel ?>&search=<?= $search ?>&limit=<?= $limit ?>&order_by=datavenc&order_dir=<?= ($order_by == 'datavenc' && $order_dir == 'asc') ? 'desc' : 'asc' ?>">VENC. <?= $order_by == 'datavenc' ? ($order_dir == 'asc' ? '↑' : '↓') : '' ?></a></th>
                        <th style="width: 50px; text-align: center;">
                            <input type="checkbox" id="select_all" class="check">
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dados_pagina)): ?>
                        <tr><td colspan="4" class="text-center" style="padding: 20px;">Nenhum registro encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dados_pagina as $row): ?>
                            <tr data-id="<?= $row['uuid_lanc'] ?>" 
                                data-nome="<?= htmlspecialchars($row['nome_res']) ?>" 
                                data-celular="<?= $row['celular'] ?>" 
                                data-venc="<?= $row['datavenc'] ?>" 
                                data-linha="<?= $row['linhadig'] ?>" 
                                data-qr="<?= $row['qrcode'] ?>">
                                <td><strong><?= $row['nome_res'] ?></strong></td>
                                <td class="hide-mobile"><?= $row['celular'] ?></td>
                                <td><span class="badge" style="background-color: #f1f3f5;"><?= $row['datavenc'] ?></span></td>
                                <td class="text-center">
                                    <input type="checkbox" class="check row-check">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
        <div class="pagination mt-3">
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <a href="?menumes=<?= $valorsel ?>&search=<?= $search ?>&limit=<?= $limit ?>&order_by=<?= $order_by ?>&order_dir=<?= $order_dir ?>&page=<?= $i ?>" 
                   class="page-link <?= $page == $i ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <div class="menu mt-3">
            <button class="button" id="btn-todos" type="button" style="background-color: var(--success);">📤 Enviar para todos</button>
            <button class="button" id="btn-sel" type="button" style="background-color: var(--secondary);">📨 Enviar selecionados</button>
            <button class="button3" onclick="window.open('logs/', '_blank')" type="button">📋 Logs</button>
        </div>
    </form>
</div>

<!-- Overlay de Processamento -->
<div id="overlay" class="overlay" style="display: none;">
    <div class="card" style="max-width: 500px; width: 90%;">
        <h3 id="overlay-title">📤 Processando Envios...</h3>
        <div id="overlay-progress" style="margin: 10px 0; font-weight: bold;">Progresso: 0/0</div>
        <div id="overlay-content" style="max-height: 300px; overflow-y: auto; text-align: left; font-size: 14px; border: 1px solid #eee; padding: 10px; background: #f9f9f9;">
        </div>
        <div id="overlay-footer" class="mt-3">
            <button id="btn-parar" class="button" style="background-color: var(--danger);">🛑 Parar Envio</button>
            <button id="btn-fechar" class="button" style="display: none;" onclick="location.reload()">Fechar</button>
        </div>
    </div>
</div>

<script>
const STORAGE_KEY = 'mkmsg_selected_vencido';
let isStopped = false;

function getSelected() { const data = sessionStorage.getItem(STORAGE_KEY); return data ? JSON.parse(data) : {}; }
function saveSelected(selected) { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(selected)); updateSelectedCount(); }
function updateSelectedCount() { const selected = getSelected(); const count = Object.keys(selected).length; $('#selected-count').text(count); }
function clearSelection() { if(confirm('Deseja limpar todos os itens selecionados?')) { sessionStorage.removeItem(STORAGE_KEY); $('.row-check').prop('checked', false); $('#select_all').prop('checked', false); updateSelectedCount(); } }

async function processarEnvios(lista) {
    isStopped = false;
    $('#overlay').css('display', 'flex');
    $('#overlay-content').html('');
    $('#btn-parar').show();
    $('#btn-fechar').hide();
    
    const total = lista.length;
    $('#overlay-progress').text(`Progresso: 0/${total}`);

    for (let i = 0; i < total; i++) {
        if (isStopped) {
            $('#overlay-content').append('<br><b style="color:red;">⚠️ Processamento interrompido pelo usuário.</b>');
            break;
        }

        const contato = lista[i];
        $('#overlay-progress').text(`Progresso: ${i + 1}/${total}`);
        
        try {
            const response = await $.ajax({
                url: 'vencido.php?menumes=<?= $valorsel ?>',
                method: 'POST',
                data: { ajax_send: true, contato: contato },
                dataType: 'json'
            });
            
            const status = response.success ? '<span style="color:green;">Sucesso</span>' : '<span style="color:red;">Falha (Verifique o Log)</span>';
            $('#overlay-content').append(`<br>Enviando para: <b>${response.nome}</b>... ${status}`);
            $('#overlay-content').scrollTop($('#overlay-content')[0].scrollHeight);
        } catch (e) {
            const nomeErro = contato.nome_res || 'Cliente';
            $('#overlay-content').append(`<br><span style="color:red;">Erro de conexão ao enviar para ${nomeErro}</span>`);
        }

        const tempomin = <?= (int)$tempomin ?>;
        const tempomax = <?= (int)$tempomax ?>;
        if (tempomax > 0) {
            const delay = Math.floor(Math.random() * (tempomax - tempomin + 1) + tempomin) * 1000;
            await new Promise(resolve => setTimeout(resolve, delay));
        }
    }

    $('#overlay-content').append('<br><div class="badge badge-success mt-3">✅ Fim do processamento!</div>');
    $('#btn-parar').hide();
    $('#btn-fechar').show();
}

$(document).ready(function() {
    const selected = getSelected();
    $('.row-check').each(function() {
        const row = $(this).closest('tr');
        const id = row.data('id');
        if (selected[id]) { $(this).prop('checked', true); }
    });

    $('.row-check').on('change', function() {
        const row = $(this).closest('tr');
        const id = row.data('id');
        const currentSelected = getSelected();
        if (this.checked) {
            currentSelected[id] = { nome_res: row.data('nome'), celular: row.data('celular'), datavenc: row.data('venc'), linhadig: row.data('linha'), qrcode: row.data('qr') };
        } else { delete currentSelected[id]; }
        saveSelected(currentSelected);
    });

    $('#select_all').on('click', function() {
        const isChecked = this.checked;
        $('.row-check').each(function() { $(this).prop('checked', isChecked).trigger('change'); });
    });

    $('#btn-parar').on('click', function() {
        isStopped = true;
        $(this).prop('disabled', true).text('Parando...');
    });

    $('#btn-todos').on('click', function() {
        if (confirm('✅ Enviar para TODOS os <?= $total_registros ?> registros vencidos no mês?')) {
            $.post('vencido.php?menumes=<?= $valorsel ?>', { get_all_ids: true }, function(data) {
                const lista = (typeof data === 'string') ? JSON.parse(data) : data;
                processarEnvios(lista);
            });
        }
    });

    $('#btn-sel').on('click', function() {
        const currentSelected = getSelected();
        const selectedArray = Object.values(currentSelected);
        if (selectedArray.length === 0) { alert('⚠️ Por favor, selecione pelo menos um cliente!'); return; }
        if (confirm('📨 Confirma o Envio para ' + selectedArray.length + ' cliente(s) selecionados?')) {
            sessionStorage.removeItem(STORAGE_KEY);
            processarEnvios(selectedArray);
        }
    });

    updateSelectedCount();
});
</script>
</body>
</html>
