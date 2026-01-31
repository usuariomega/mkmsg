<?php
include 'header.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Configurações de Filtro e Ordenação
$search = isset($_GET['search']) ? $_GET['search'] : '';
$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'nome_res';
$order_dir = isset($_GET['order_dir']) && $_GET['order_dir'] == 'desc' ? 'desc' : 'asc';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Conexão e Busca de Dados
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Erro de conexão: " . $conn->connect_error); }

$where_clause = "WHERE vtab_titulos.status = 'aberto' 
                 AND vtab_titulos.cli_ativado = 's' 
                 AND MONTH(vtab_titulos.datavenc) = MONTH(CURDATE()) 
                 AND YEAR(vtab_titulos.datavenc) = YEAR(CURDATE())";

if (!empty($search)) {
    $where_clause .= " AND (vtab_titulos.nome_res LIKE ? OR vtab_titulos.celular LIKE ?)";
}

$count_sql = "SELECT COUNT(*) as total FROM vtab_titulos $where_clause";
$stmt_count = $conn->prepare($count_sql);
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt_count->bind_param("ss", $search_param, $search_param);
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
        ORDER BY $order_by $order_dir 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $stmt->bind_param("ssii", $search_param, $search_param, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$dados_pagina = [];
while ($row = $result->fetch_assoc()) {
    $dados_pagina[] = $row;
}

function enviarMensagem($contato, $msgnoprazo, $vars, $wsip, $token, $tempomin, $tempomax) {
    $nome = $contato['nome_res'];
    $celular = $contato['celular'];
    $datavenc = $contato['datavenc'];
    $linhadig = $contato['linhadig'];
    $qrcode = $contato['qrcode'];

    $buscar = array('/%provedor%/', '/%nomeresumido%/', '/%vencimento%/', '/%linhadig%/', '/%copiacola%/', '/%site%/');
    $substituir = array($vars['provedor'], $nome, $datavenc, $linhadig, $qrcode, $vars['site']);
    $msgFinal = preg_replace($buscar, $substituir, $msgnoprazo);

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

    $month = date("Y-m");
    $root = $_SERVER["DOCUMENT_ROOT"] . "/mkmsg";
    $dir = "$root/logs/$month/noprazo";

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        if (file_exists("$root/logs/.ler/modelo/index.php")) {
            copy("$root/logs/.ler/modelo/index.php", "$dir/index.php");
        }
    }

    $logFile = "$dir/noprazo_" . date("d-M-Y") . ".log";
    $logData = sprintf("%s;%s;%s;%s\n", date("d-m-Y"), date("H:i:s"), $nome, $error ?: $response);
    file_put_contents($logFile, $logData, FILE_APPEND);

    echo "<br>Enviando para: <b>$nome</b>... " . ($error ? "Erro: $error" : "Resposta: $response");
    ob_flush(); flush();
    if ($tempomax > 0) { sleep(rand($tempomin, $tempomax)); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new SQLite3('db/msgdb.sqlite3');
    $msgnoprazo = $db->querySingle("SELECT msg FROM msgnoprazo");
    $db->close();
    $vars = ['provedor' => $provedor, 'site' => $site];
    
    if (isset($_POST['posttodos'])) {
        $sql_todos = "SELECT upper(vtab_titulos.nome_res) as nome_res, REGEXP_REPLACE(vtab_titulos.celular,'[( )-]+','') AS celular, 
                      DATE_FORMAT(vtab_titulos.datavenc,'%d/%m/%y') AS datavenc, 
                      vtab_titulos.linhadig, sis_qrpix.qrcode 
                      FROM vtab_titulos 
                      INNER JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo 
                      WHERE vtab_titulos.status = 'aberto' AND vtab_titulos.cli_ativado = 's' AND vtab_titulos.datavenc >= CURDATE()
                      ORDER BY nome_res ASC";
        $stmt_todos = $conn->prepare($sql_todos);
        $stmt_todos->execute();
        $res_todos = $stmt_todos->get_result();
        
        echo '<div id="overlay" class="overlay" style="display: flex;">';
        echo '<div class="card">';
        echo '<h3>📤 Processando Envios (Todos No Prazo)...</h3>';
        echo '<div id="overlay-content">';
        
        if (ob_get_level() == 0) ob_start();
        while ($contato = $res_todos->fetch_assoc()) {
            enviarMensagem($contato, $msgnoprazo, $vars, $wsip, $token, $tempomin, $tempomax);
        }
        echo '<div class="badge badge-success mt-3" style="font-size: 16px; padding: 12px 24px;">✅ Fim do processamento!</div>';
        echo '<br><button class="button mt-3" onclick="window.location.href=\'index.php\'">Fechar</button>';
        echo '</div></div></div>';
        ob_end_flush();
        exit;
    } elseif (isset($_POST['postsel']) && isset($_POST['selected_data'])) {
        $selected_items = json_decode($_POST['selected_data'], true);
        
        echo '<div id="overlay" class="overlay" style="display: flex;">';
        echo '<div class="card">';
        echo '<h3>📤 Processando Envios Selecionados...</h3>';
        echo '<div id="overlay-content">';
        
        if (ob_get_level() == 0) ob_start();
        foreach ($selected_items as $contato) {
            enviarMensagem($contato, $msgnoprazo, $vars, $wsip, $token, $tempomin, $tempomax);
        }
        echo '<div class="badge badge-success mt-3" style="font-size: 16px; padding: 12px 24px;">✅ Fim do processamento!</div>';
        echo '<br><button class="button mt-3" onclick="window.location.href=\'index.php\'">Fechar</button>';
        echo '</div></div></div>';
        ob_end_flush();
        exit;
    }
}
?>

<div class="container">
    <div class="card mb-3">
        <h2 class="title-noprazo">📅 Títulos No Prazo</h2>
        <p class="text-subtitle">
            Total de registros: <strong><?= $total_registros ?></strong> | Selecionados: <strong id="selected-count">0</strong>
            <button type="button" class="button3 btn-small" onclick="clearSelection()">Limpar Seleção</button>
        </p>
    </div>

    <div class="menu card mb-3">
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <button class="button2" onclick="location.href='index.php'" type="button" style="background-color: var(--secondary); border: 2px solid var(--secondary);">📅 No prazo</button>
            <button class="button3" onclick="location.href='vencido.php'" type="button">⚠️ Vencidos</button>
            <button class="button3" onclick="location.href='pago.php'" type="button">✅ Pagos</button>
            <button class="button3" onclick="location.href='confmsg.php'" type="button">💬 Conf. msg</button>
            <button class="button3" onclick="location.href='confweb.php'" type="button">⚙️ Conf. geral</button>
        </div>
    </div>

    <div class="card mb-3">
        <form method="get" class="search-container">
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
                        <th><a href="?search=<?= $search ?>&limit=<?= $limit ?>&order_by=nome_res&order_dir=<?= ($order_by == 'nome_res' && $order_dir == 'asc') ? 'desc' : 'asc' ?>">NOME <?= $order_by == 'nome_res' ? ($order_dir == 'asc' ? '↑' : '↓') : '' ?></a></th>
                        <th class="hide-mobile">CELULAR</th>
                        <th><a href="?search=<?= $search ?>&limit=<?= $limit ?>&order_by=datavenc&order_dir=<?= ($order_by == 'datavenc' && $order_dir == 'asc') ? 'desc' : 'asc' ?>">VENC. <?= $order_by == 'datavenc' ? ($order_dir == 'asc' ? '↑' : '↓') : '' ?></a></th>
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
                <a href="?search=<?= $search ?>&limit=<?= $limit ?>&order_by=<?= $order_by ?>&order_dir=<?= $order_dir ?>&page=<?= $i ?>" 
                   class="page-link <?= $page == $i ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <div class="menu mt-3">
            <button class="button" name="posttodos" type="submit" onclick="return confirm('✅ Enviar para TODOS os <?= $total_registros ?> registros no prazo?')" style="background-color: var(--success);">📤 Enviar para todos</button>
            <button class="button" name="postsel" type="submit" style="background-color: var(--secondary);">📨 Enviar selecionados</button>
            <button class="button3" onclick="window.open('logs/', '_blank')" type="button">📋 Logs</button>
        </div>
    </form>
</div>

<script>
const STORAGE_KEY = 'mkmsg_selected_noprazo';
function getSelected() { const data = sessionStorage.getItem(STORAGE_KEY); return data ? JSON.parse(data) : {}; }
function saveSelected(selected) { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(selected)); updateSelectedCount(); }
function updateSelectedCount() { const selected = getSelected(); const count = Object.keys(selected).length; $('#selected-count').text(count); }
function clearSelection() { if(confirm('Deseja limpar todos os itens selecionados?')) { sessionStorage.removeItem(STORAGE_KEY); $('.row-check').prop('checked', false); $('#select_all').prop('checked', false); updateSelectedCount(); } }

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
    $('#form').on('submit', function(e) {
        const btnName = $(document.activeElement).attr('name');
        if (btnName === 'postsel') {
            const currentSelected = getSelected();
            const selectedArray = Object.values(currentSelected);
            if (selectedArray.length === 0) { alert('⚠️ Por favor, selecione pelo menos um cliente!'); e.preventDefault(); return false; }
            if (confirm('📨 Confirma o Envio para ' + selectedArray.length + ' cliente(s) selecionados?')) {
                $('#selected_data').val(JSON.stringify(selectedArray));
                sessionStorage.removeItem(STORAGE_KEY);
                return true;
            } else { e.preventDefault(); return false; }
        }
    });
    updateSelectedCount();
});
</script>
</body>
</html>

