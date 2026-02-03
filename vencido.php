<?php
if (isset($_POST['ajax_send']) || isset($_POST['get_all_ids'])) {
    ob_start();
    include 'header.php';
    ob_clean();

    if (isset($_POST['ajax_send'])) {
        $contato = $_POST['contato'];
        $jsonFile = __DIR__ . '/db/messages/vencido.json';
        $msgvencido = "";
        if (file_exists($jsonFile)) {
            $jsonData = json_decode(file_get_contents($jsonFile), true);
            $msgvencido = $jsonData['content'] ?? "";
        }

        $nome = $contato['nome_res'] ?? 'N/A';
        $celular = $contato['celular'] ?? '';
        $datavenc = $contato['datavenc'] ?? '';
        $linhadig = $contato['linhadig'] ?? '';
        $qrcode = $contato['qrcode'] ?? '';

        $buscar = ['/%provedor%/', '/%nomeresumido%/', '/%vencimento%/', '/%linhadig%/', '/%copiacola%/', '/%site%/'];
        $substituir = [$provedor, $nome, $datavenc, $linhadig, $qrcode, $site];
        $msgFinal = preg_replace($buscar, $substituir, $msgvencido);

        $payload = ["numero" => "55" . $celular, "mensagem" => $msgFinal];
        $ch = curl_init("http://$wsip:8000/send");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "x-api-token: $token"],
            CURLOPT_TIMEOUT => 10
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        $apiSuccess = false;
        if (!$error) {
            $resData = json_decode($response, true);
            $apiSuccess = (isset($resData['status']) && ($resData['status'] === 'sent' || $resData['status'] === true)) || isset($resData['key']);
        }

        $month = date("Y-m");
        $root = $_SERVER["DOCUMENT_ROOT"] . "/mkmsg";
        $dir = "$root/logs/$month/vencido";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            if (file_exists("$root/logs/.ler/modelo/index.php")) copy("$root/logs/.ler/modelo/index.php", "$dir/index.php");
        }

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
                      DATE_FORMAT(vtab_titulos.datavenc,'%d/%m/%y') AS datavenc, vtab_titulos.linhadig, sis_qrpix.qrcode 
                      FROM vtab_titulos 
                      LEFT JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo 
                      WHERE DATE_FORMAT(datavenc,'%m-%Y') = ? AND vtab_titulos.status = 'vencido' AND vtab_titulos.cli_ativado = 's'
                      AND (vtab_titulos.deltitulo = 0 OR vtab_titulos.deltitulo IS NULL)
                      AND vtab_titulos.nome_res IS NOT NULL AND TRIM(vtab_titulos.nome_res) <> ''
                      AND vtab_titulos.celular IS NOT NULL AND TRIM(vtab_titulos.celular) <> ''
                      AND vtab_titulos.linhadig IS NOT NULL AND TRIM(vtab_titulos.linhadig) <> ''
                      GROUP BY vtab_titulos.uuid_lanc ORDER BY nome_res ASC";

        $stmt_todos = $conn->prepare($sql_todos);
        $stmt_todos->bind_param("s", $valorsel);
        $stmt_todos->execute();
        $res_todos = $stmt_todos->get_result();
        $todos = [];
        while ($row = $res_todos->fetch_assoc()) $todos[] = $row;
        header('Content-Type: application/json');
        echo json_encode($todos);
        exit;
    }
}

include 'header.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$valorsel = $_GET['menumes'] ?? date("m-Y");
$search = $_GET['search'] ?? '';
$order_by = $_GET['order_by'] ?? 'nome_res';
$order_dir = (isset($_GET['order_dir']) && $_GET['order_dir'] == 'desc') ? 'desc' : 'asc';
$limit = (int)($_GET['limit'] ?? 10);
$page = (int)($_GET['page'] ?? 1);
$offset = ($page - 1) * $limit;

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Erro de conexão: " . $conn->connect_error);

$where_clause = "WHERE DATE_FORMAT(datavenc,'%m-%Y') = ? AND vtab_titulos.status = 'vencido' AND vtab_titulos.cli_ativado = 's'
                 AND (vtab_titulos.deltitulo = 0 OR vtab_titulos.deltitulo IS NULL)
                 AND vtab_titulos.nome_res IS NOT NULL AND TRIM(vtab_titulos.nome_res) <> ''
                 AND vtab_titulos.celular IS NOT NULL AND TRIM(vtab_titulos.celular) <> ''
                 AND vtab_titulos.linhadig IS NOT NULL AND TRIM(vtab_titulos.linhadig) <> ''";

if (!empty($search)) $where_clause .= " AND (vtab_titulos.nome_res LIKE ? OR vtab_titulos.celular LIKE ?)";

$count_sql = "SELECT COUNT(DISTINCT vtab_titulos.uuid_lanc) as total FROM vtab_titulos 
              LEFT JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo $where_clause";

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
        DATE_FORMAT(vtab_titulos.datavenc,'%d/%m/%y') AS datavenc, vtab_titulos.linhadig, sis_qrpix.qrcode 
        FROM vtab_titulos 
        LEFT JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo 
        $where_clause GROUP BY vtab_titulos.uuid_lanc
        ORDER BY $order_by $order_dir LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $stmt->bind_param("sssii", $valorsel, $search_param, $search_param, $limit, $offset);
} else {
    $stmt->bind_param("sii", $valorsel, $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container">
    <div class="card mb-3">
        <h2 class="title-vencido">⚠️ Títulos Vencidos</h2>
        <p class="text-subtitle">
            Total de registros: <strong><?= $total_registros ?></strong> | Selecionados: <strong id="selected-count">0</strong>
            <button type="button" class="button3 btn-small" onclick="clearSelection()">Limpar Seleção</button>
        </p>
    </div>

    <div class="menu card mb-3">
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <button class="button3" onclick="location.href='index.php'" type="button">📅 No prazo</button>
            <button class="button2" onclick="location.href='vencido.php'" type="button" style="background-color: var(--danger); border: 2px solid var(--danger);">⚠️ Vencidos</button>
            <button class="button3" onclick="location.href='pago.php'" type="button">✅ Pagos</button>
            <button class="button3" onclick="location.href='emmassa.php'" type="button">📢 Em massa</button>
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
                <?php foreach ([10, 25, 50, 100] as $l): ?>
                    <option value="<?= $l ?>" <?= $limit == $l ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" class="resp-search" placeholder="Buscar nome ou celular..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="button btn-search">🔍</button>
        </form>
    </div>

    <form id="form" method="post">
        <div class="card p-0 overflow-x-auto">
            <table class="custom-table w-100">
                <thead>
                    <tr>
                        <th><a href="?menumes=<?= $valorsel ?>&search=<?= $search ?>&limit=<?= $limit ?>&order_by=nome_res&order_dir=<?= ($order_by == 'nome_res' && $order_dir == 'asc') ? 'desc' : 'asc' ?>">NOME <?= $order_by == 'nome_res' ? ($order_dir == 'asc' ? '↑' : '↓') : '' ?></a></th>
                        <th class="hide-mobile">CELULAR</th>
                        <th><a href="?menumes=<?= $valorsel ?>&search=<?= $search ?>&limit=<?= $limit ?>&order_by=datavenc&order_dir=<?= ($order_by == 'datavenc' && $order_dir == 'asc') ? 'desc' : 'asc' ?>">VENC. <?= $order_by == 'datavenc' ? ($order_dir == 'asc' ? '↑' : '↓') : '' ?></a></th>
                        <th style="width: 50px; text-align: center;"><input type="checkbox" id="select_all" class="check"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="4" class="text-center" style="padding: 20px;">Nenhum registro encontrado.</td></tr>
                    <?php else: ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr data-id="<?= $row['uuid_lanc'] ?>" data-nome="<?= htmlspecialchars($row['nome_res']) ?>" data-celular="<?= $row['celular'] ?>" data-venc="<?= $row['datavenc'] ?>" data-linha="<?= $row['linhadig'] ?>" data-qr="<?= $row['qrcode'] ?>">
                                <td><strong><?= $row['nome_res'] ?></strong></td>
                                <td class="hide-mobile"><?= $row['celular'] ?></td>
                                <td><span class="badge" style="background-color: #f1f3f5;"><?= $row['datavenc'] ?></span></td>
                                <td class="text-center"><input type="checkbox" class="check row-check"></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
        <div class="pagination mt-3">
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <a href="?menumes=<?= $valorsel ?>&search=<?= $search ?>&limit=<?= $limit ?>&order_by=<?= $order_by ?>&order_dir=<?= $order_dir ?>&page=<?= $i ?>" class="page-link <?= $page == $i ? 'active' : '' ?>"><?= $i ?></a>
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

<div id="overlay" class="overlay" style="display: none;">
    <div class="card" style="max-width: 500px; width: 90%;">
        <h3 id="overlay-title">📤 Processando Envios...</h3>
        <div id="overlay-progress" style="margin: 10px 0; font-weight: bold;">Progresso: 0/0</div>
        <div id="overlay-content" style="max-height: 300px; overflow-y: auto; text-align: left; padding: 10px; background: #f8f9fa; border-radius: 8px; font-size: 13px;"></div>
        <div id="overlay-footer" class="mt-3">
            <button id="btn-parar" class="button" style="background-color: var(--danger);">🛑 Parar Envio</button>
            <button id="btn-fechar" class="button" style="display: none;" onclick="location.reload()">Fechar</button>
        </div>
    </div>
</div>

<script>
const STORAGE_KEY = 'mkmsg_selected_vencido';
let isStopped = false;

const getSelected = () => JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '{}');
const saveSelected = (s) => { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(s)); updateSelectedCount(); };
const updateSelectedCount = () => $('#selected-count').text(Object.keys(getSelected()).length);
const clearSelection = () => { if(confirm('Limpar selecionados?')) { sessionStorage.removeItem(STORAGE_KEY); $('.row-check, #select_all').prop('checked', false); updateSelectedCount(); } };

async function processarEnvios(lista) {
    isStopped = false;
    $('#overlay').css('display', 'flex');
    $('#overlay-content').empty();
    $('#btn-parar').show(); $('#btn-fechar').hide();
    
    for (let i = 0; i < lista.length; i++) {
        if (isStopped) { $('#overlay-content').append('<br><b style="color:red;">⚠️ Interrompido.</b>'); break; }
        const contato = lista[i];
        $('#overlay-progress').text(`Progresso: ${i + 1}/${lista.length}`);
        try {
            const res = await $.ajax({ url: 'vencido.php?menumes=<?= $valorsel ?>', method: 'POST', data: { ajax_send: true, contato }, dataType: 'json' });
            const status = res.success ? '<span style="color:green;">Sucesso</span>' : '<span style="color:red;">Falha</span>';
            $('#overlay-content').append(`<br>Enviando para: <b>${res.nome}</b>... ${status}`);
            $('#overlay-content').scrollTop($('#overlay-content')[0].scrollHeight);
        } catch (e) {
            $('#overlay-content').append(`<br><span style="color:red;">Erro ao enviar para ${contato.nome_res}</span>`);
        }
        const delay = Math.floor(Math.random() * (<?= (int)$tempomax ?> - <?= (int)$tempomin ?> + 1) + <?= (int)$tempomin ?>) * 1000;
        if (delay > 0) await new Promise(r => setTimeout(r, delay));
    }
    $('#overlay-content').append('<br><div class="badge badge-success mt-3">✅ Concluído!</div>');
    $('#btn-parar').hide(); $('#btn-fechar').show();
}

$(document).ready(() => {
    const selected = getSelected();
    $('.row-check').each(function() {
        if (selected[$(this).closest('tr').data('id')]) $(this).prop('checked', true);
    });

    $('.row-check').on('change', function() {
        const tr = $(this).closest('tr');
        const id = tr.data('id');
        const s = getSelected();
        if (this.checked) s[id] = { nome_res: tr.data('nome'), celular: tr.data('celular'), datavenc: tr.data('venc'), linhadig: tr.data('linha'), qrcode: tr.data('qr') };
        else delete s[id];
        saveSelected(s);
    });

    $('#select_all').on('click', function() {
        $('.row-check').prop('checked', this.checked).trigger('change');
    });

    $('#btn-parar').on('click', function() { isStopped = true; $(this).prop('disabled', true).text('Parando...'); });

    $('#btn-todos').on('click', () => {
        if (confirm('Enviar para TODOS os <?= $total_registros ?> registros?')) {
            $.post('vencido.php?menumes=<?= $valorsel ?>', { get_all_ids: true }, (data) => processarEnvios(data));
        }
    });

    $('#btn-sel').on('click', () => {
        const s = Object.values(getSelected());
        if (s.length === 0) return alert('Selecione pelo menos um!');
        if (confirm(`Enviar para ${s.length} selecionados?`)) { sessionStorage.removeItem(STORAGE_KEY); processarEnvios(s); }
    });

    updateSelectedCount();
});
</script>
</body>
</html>
