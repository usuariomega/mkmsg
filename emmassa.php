<?php
// Configurações e Diretórios
$dataDir = __DIR__ . '/db/emmassa';
$listsDir = $dataDir . '/lists';
$messagesDir = $dataDir . '/messages';
$importedClientsFile = $dataDir . '/imported_clients.json';

// Criar diretórios se não existirem
if (!is_dir($listsDir)) mkdir($listsDir, 0755, true);
if (!is_dir($messagesDir)) mkdir($messagesDir, 0755, true);

// Funções Auxiliares
function getMySQLConnection($servername, $username, $password, $dbname, $port) {
    try {
        $conn = new mysqli($servername, $username, $password, $dbname, $port);
        if ($conn->connect_error) return null;
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        return null;
    }
}

function getClientsFromVtabTitulos($servername, $username, $password, $dbname, $port) {
    $conn = getMySQLConnection($servername, $username, $password, $dbname, $port);
    if (!$conn) return [];
    $sql = "SELECT id, upper(nome_res) as nome_res, 
            REGEXP_REPLACE(celular,'[( )-]+','') AS celular 
            FROM sis_cliente WHERE cli_ativado = 's' 
            AND nome_res IS NOT NULL AND TRIM(nome_res) <> '' 
            AND celular IS NOT NULL AND TRIM(celular) <> '' 
            ORDER BY nome_res ASC";

    $result = $conn->query($sql);
    $clients = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $clients[] = [
                'id' => 'mk_' . $row['id'], 
                'nome' => $row['nome_res'], 
                'celular' => $row['celular'],
                'origem' => 'mk'
            ];
        }
    }
    $conn->close();
    return $clients;
}

function getImportedClients($file) {
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true) ?: [];
    }
    return [];
}

function saveImportedClients($file, $clients) {
    file_put_contents($file, json_encode($clients));
}

function sanitizeFilename($name) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
}

function getFilenameFromName($name, $dir) {
    $safeName = sanitizeFilename($name);
    return $safeName . '.json';
}

function fileExists($name, $dir) {
    $filename = getFilenameFromName($name, $dir);
    return file_exists($dir . '/' . $filename);
}

function saveList($name, $clients, $listsDir, $isEdit = false) {
    if (!$isEdit && fileExists($name, $listsDir)) {
        return ['success' => false, 'error' => 'Uma lista com este nome já existe'];
    }
    $filename = getFilenameFromName($name, $listsDir);
    $clientIds = array_map(function($c) { return $c['id']; }, $clients);
    file_put_contents($listsDir . '/' . $filename, json_encode([
        'name' => $name, 
        'clientIds' => $clientIds, 
        'createdAt' => date('Y-m-d H:i:s')
    ]));
    return ['success' => true, 'filename' => $filename];
}

function saveMessage($name, $content, $messagesDir, $isEdit = false) {
    if (!$isEdit && fileExists($name, $messagesDir)) {
        return ['success' => false, 'error' => 'Uma mensagem com este nome já existe'];
    }
    $filename = getFilenameFromName($name, $messagesDir);
    file_put_contents($messagesDir . '/' . $filename, json_encode(['name' => $name, 'content' => $content, 'createdAt' => date('Y-m-d H:i:s')]));
    return ['success' => true, 'filename' => $filename];
}

function listFiles($dir) {
    $files = [];
    if (is_dir($dir)) {
        foreach (scandir($dir) as $file) {
            if (strpos($file, '.json') !== false) { $files[] = $file; }
        }
    }
    return array_reverse($files);
}

// Processar requisições AJAX ANTES de qualquer saída (header.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    include 'config.php';
    $action = $_POST['action'];
    header('Content-Type: application/json');

    if ($action === 'sendMessage') {
        $contato = json_decode($_POST['contato'] ?? '{}', true);
        $message = $_POST['message'] ?? '';
        $nome = $contato['nome'] ?? 'N/A';
        $celular = $contato['celular'] ?? '';
        $firstName = explode(' ', $nome)[0];
        $baloes = explode('##', $message);
        $responses = [];
        $allSuccess = true;
        $lastError = '';

        foreach ($baloes as $balao) {
            $balao = trim($balao);
            if (empty($balao)) continue;
            $msgFinal = str_replace(
                ['%nomeresumido%', '%celular%', '%provedor%', '%site%', '%0A'], 
                [$firstName, $celular, $provedor ?? '', $site ?? '', "\n"], 
                $balao
            );
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
            if (!$apiSuccess) {
                $allSuccess = false;
                $lastError = $error ?: $response;
            }
            $responses[] = $error ?: $response;
            usleep(500000);
        }
        
        $month = date("Y-m");
        $root = $_SERVER["DOCUMENT_ROOT"] . "/mkmsg";
        $dir = "$root/logs/$month/emmassa";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            if (file_exists("$root/logs/.ler/modelo/index.php")) copy("$root/logs/.ler/modelo/index.php", "$dir/index.php");
        }
        $logFile = "$dir/emmassa_" . date("d-m-Y") . ".log";
        file_put_contents($logFile, sprintf("%s;%s;%s;%s\n", date("d-m-Y"), date("H:i:s"), $nome, $allSuccess ? $response : $lastError), FILE_APPEND);
        echo json_encode(['success' => $allSuccess, 'nome' => $nome, 'response' => implode(' | ', $responses)]);
        exit;
    }
    
    if ($action === 'saveList') {
        $name = $_POST['name'] ?? 'Lista sem nome';
        $clients = json_decode($_POST['clients'] ?? '[]', true);
        $isEdit = isset($_POST['isEdit']) && $_POST['isEdit'] === 'true';
        $result = saveList($name, $clients, $listsDir, $isEdit);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'saveMessage') {
        $name = $_POST['name'] ?? 'Mensagem sem nome';
        $content = $_POST['content'] ?? '';
        $isEdit = isset($_POST['isEdit']) && $_POST['isEdit'] === 'true';
        $result = saveMessage($name, $content, $messagesDir, $isEdit);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'editList') {
        $oldFilename = $_POST['oldFilename'] ?? '';
        $name = $_POST['name'] ?? 'Lista sem nome';
        $clients = json_decode($_POST['clients'] ?? '[]', true);
        $oldFilepath = $listsDir . '/' . basename($oldFilename);
        if ($oldFilename && file_exists($oldFilepath)) unlink($oldFilepath);
        $result = saveList($name, $clients, $listsDir, true);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'loadList') {
        $filename = $_POST['filename'] ?? '';
        $filepath = $listsDir . '/' . basename($filename);
        if (file_exists($filepath)) {
            $listData = json_decode(file_get_contents($filepath), true);
            if (isset($listData['clients']) && !isset($listData['clientIds'])) {
                $listData['clientIds'] = array_map(function($c) { return $c['id']; }, $listData['clients']);
            }
            echo json_encode(['success' => true, 'data' => $listData]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    if ($action === 'editMessage') {
        $oldFilename = $_POST['oldFilename'] ?? '';
        $name = $_POST['name'] ?? 'Mensagem sem nome';
        $content = $_POST['content'] ?? '';
        $oldFilepath = $messagesDir . '/' . basename($oldFilename);
        if ($oldFilename && file_exists($oldFilepath)) unlink($oldFilepath);
        $result = saveMessage($name, $content, $messagesDir, true);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'loadMessage') {
        $filename = $_POST['filename'] ?? '';
        $filepath = $messagesDir . '/' . basename($filename);
        if (file_exists($filepath)) {
            echo json_encode(['success' => true, 'data' => json_decode(file_get_contents($filepath), true)]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    if ($action === 'listFiles') {
        $type = $_POST['type'] ?? 'lists';
        $dir = $type === 'messages' ? $messagesDir : $listsDir;
        echo json_encode(['files' => listFiles($dir)]);
        exit;
    }
    
    if ($action === 'deleteFile') {
        $type = $_POST['type'] ?? 'lists';
        $filename = $_POST['filename'] ?? '';
        $dir = $type === 'messages' ? $messagesDir : $listsDir;
        $filepath = $dir . '/' . basename($filename);
        if (file_exists($filepath)) {
            unlink($filepath);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    if ($action === 'importClients') {
        $newClients = json_decode($_POST['clients'] ?? '[]', true);
        $currentImported = getImportedClients($importedClientsFile);
        
        foreach ($newClients as $nc) {
            $nc['id'] = 'imp_' . time() . '_' . rand(1000, 9999);
            $nc['origem'] = 'imp';
            $currentImported[] = $nc;
        }
        
        saveImportedClients($importedClientsFile, $currentImported);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'clearImported') {
        if (file_exists($importedClientsFile)) unlink($importedClientsFile);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'deleteImportedClients') {
        $clientIds = json_decode($_POST['clientIds'] ?? '[]', true);
        $currentImported = getImportedClients($importedClientsFile);
        $updated = array_filter($currentImported, function($client) use ($clientIds) {
            return !in_array($client['id'], $clientIds);
        });
        if (count($updated) > 0) {
            saveImportedClients($importedClientsFile, array_values($updated));
        } else {
            if (file_exists($importedClientsFile)) unlink($importedClientsFile);
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

include 'config.php';
include 'header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/mkmsg/install/version.php';
$mkClients = getClientsFromVtabTitulos($servername, $username, $password, $dbname, $port);
$impClients = getImportedClients($importedClientsFile);
$allClients = array_merge($mkClients, $impClients);

$tMin = isset($tempomin) ? (int)$tempomin : 10;
$tMax = isset($tempomax) ? (int)$tempomax : 90;
?>

<style>
    .checkbox-list { max-height: 300px; overflow-y: auto; border: 1px solid var(--border); padding: 12px; background: var(--bg-white); border-radius: var(--radius-md); }
    .checkbox-item { display: flex; align-items: center; padding: 8px; border-bottom: 1px solid var(--border-light); }
    .checkbox-item:last-child { border-bottom: none; }
    .checkbox-item input[type="checkbox"] { margin-right: 12px; width: 18px; height: 18px; cursor: pointer; }
    .checkbox-item label { flex: 1; cursor: pointer; margin: 0; font-size: 14px; }
    .recipients-summary { background: var(--primary-light); border: 1px solid var(--primary); border-radius: var(--radius-md); padding: 12px 16px; margin-top: 16px; color: var(--text-primary); font-weight: 600; }
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; }
    .modal.active { display: flex; }
    .modal-content { background: var(--bg-white); border-radius: var(--radius-md); padding: 24px; max-width: 500px; width: 90%; box-shadow: var(--shadow-md); max-height: 90vh; overflow-y: auto; }
    .modal-header { font-size: 18px; font-weight: 700; margin-bottom: 16px; color: var(--tertiary); border-bottom: 1px solid var(--border-light); padding-bottom: 10px; }
    .modal-footer { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }
    .saved-items { display: grid; grid-template-columns: 1fr; gap: 12px; margin-top: 12px; }
    .saved-item { display: flex; align-items: center; padding: 12px; background: var(--bg-light); border-radius: var(--radius-sm); border: 1px solid var(--border); }
    .saved-item-info { flex: 1; }
    .saved-item-name { font-weight: 600; font-size: 14px; color: var(--text-primary); }
    .saved-item-actions { display: flex; gap: 8px; }
    .saved-item-actions button { padding: 6px 12px; font-size: 12px; height: auto; }
    .msg-section textarea { width: 100%; min-height: 180px; font-family: 'Courier New', Courier, monospace; font-size: 14px; line-height: 1.6; resize: vertical; margin-bottom: 12px; }
    .whatsapp-bubble { background: #fff; padding: 10px 14px; border-radius: 8px; position: relative; max-width: 85%; font-size: 14px; line-height: 1.5; box-shadow: 0 1px 2px rgba(0,0,0,0.15); margin-bottom: 8px; word-wrap: break-word; white-space: pre-wrap; display: table; width: auto; min-width: 50px; }
    .whatsapp-bubble::before { content: ""; position: absolute; width: 0; height: 0; border-top: 10px solid transparent; border-bottom: 10px solid transparent; border-right: 10px solid #fff; left: -10px; top: 0; }
    .badge-mk { background: #28a745; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-right: 8px; font-weight: bold; }
    .badge-imp { background: #007bff; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-right: 8px; font-weight: bold; }
</style>

<div class="container">
    <div class="card mb-3">
        <h2 class="title-config">📤 Envio em Massa</h2>
        <p class="text-subtitle">Envie mensagens para todos os clientes ou selecione uma lista específica.</p>
    </div>

    <div class="menu card mb-3">
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <button class="button3" onclick="location.href='index.php'" type="button">📅 No prazo</button>
            <button class="button3" onclick="location.href='vencido.php'" type="button">⚠️ Vencidos</button>
            <button class="button3" onclick="location.href='pago.php'" type="button">✅ Pagos</button>
            <button class="button2" onclick="location.href='emmassa.php'" type="button" style="background-color: var(--secondary); border: 2px solid var(--secondary);">📢 Em massa</button>
            <button class="button3" onclick="location.href='confmsg.php'" type="button">💬 Conf. msg</button>
            <button class="button3" onclick="location.href='confweb.php'" type="button">⚙️ Conf. geral</button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="section-header">📋 Selecione os Destinatários</div>
        <div style="padding: 24px;">
            <div style="margin-bottom: 16px;">
                <label class="form-label">Opções de Envio</label>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;"><input type="radio" name="recipientType" value="all" checked class="check" style="width: 20px; height: 20px;"><span>Todos os clientes (<?php echo count($allClients); ?>)</span></label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;"><input type="radio" name="recipientType" value="list" class="check" style="width: 20px; height: 20px;"><span>Selecionar lista salva</span></label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;"><input type="radio" name="recipientType" value="manual" class="check" style="width: 20px; height: 20px;"><span>Seleção manual</span></label>
                </div>
            </div>

            <div id="listSelection" style="display: none; margin-bottom: 16px;">
                <label class="form-label">Escolha uma lista salva</label>
                <div style="display: flex; gap: 10px;">
                    <select id="savedLists" class="form-input-full" style="flex: 1;"><option value="">Selecione uma lista...</option></select>
                    <button type="button" class="button3" onclick="openLoadListModal()">Gerenciar</button>
                </div>
            </div>

            <div id="manualSelection" style="display: none; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label class="form-label" style="margin: 0;">Selecione os clientes</label>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="button3 btn-small" onclick="selectAllClients()">Todos</button>
                        <button type="button" class="button3 btn-small" onclick="deselectAllClients()">Nenhum</button>
                    </div>
                </div>
                <div style="margin-bottom: 12px;">
                    <input type="text" id="searchManual" class="form-input-full" placeholder="🔍 Pesquisar por nome ou celular..." onkeyup="filterClients('manual')">
                </div>
                <div id="clientsList" class="checkbox-list">
                    <?php foreach ($allClients as $client): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" id="client_<?php echo $client['id']; ?>" value="<?php echo $client['id']; ?>" onchange="updateRecipientCount()" class="check">
                            <label for="client_<?php echo $client['id']; ?>">
                                <span class="badge-<?php echo $client['origem']; ?>"><?php echo strtoupper($client['origem']); ?></span>
                                <strong><?php echo $client['nome']; ?></strong> - <?php echo $client['celular']; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="button button-small" onclick="openSaveListModal()">💾 Salvar como Lista</button>
                    <button type="button" class="button button-small" onclick="openImportModal()" style="background-color: #00b32b;">📥 Importar Contatos</button>
                    <?php if (count($impClients) > 0): ?>
                        <button type="button" class="button button-danger button-small" onclick="deleteSelectedImported()" style="background-color: #dc3545;">🗑️ Excluir Selecionados</button>
                        <button type="button" class="button button-danger button-small" onclick="clearAllImported()" style="background-color: #dc3545;">🗑️ Limpar Todos</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="recipients-summary">Destinatários selecionados: <span id="recipientCount">0</span></div>
        </div>
    </div>

    <div class="msg-section">
        <div class="section-header">💬 Mensagem e Pré-visualização</div>
        <div class="flex-layout">
            <div class="editor-side">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <label class="form-label" style="margin: 0;">✏️ Editor de Mensagem</label>
                    <button type="button" class="button3 btn-small" onclick="openLoadMessageModal()">📂 Carregar Salva</button>
                </div>
                <textarea id="messageContent" placeholder="Digite sua mensagem aqui..."></textarea>
                <div class="coringas-list">
                    <b>📌 Coringas:</b> %nomeresumido%, %provedor%, %site%<br>
                    <b>⚡ Comandos:</b> %0A (Quebra), ## (Novo Balão), *texto* (Negrito)
                </div>
                <div class="mt-3"><button type="button" class="button button-small" onclick="openSaveMessageModal()">💾 Salvar Mensagem</button></div>
            </div>
            <div class="preview-side">
                <label style="display: block; font-weight: 600; margin-bottom: 12px; color: #075e54;">👁️ Pré-visualização WhatsApp</label>
                <div id="preview" class="w-100"></div>
            </div>
        </div>
    </div>

    <div class="text-center" style="margin-top: 30px; padding-bottom: 40px;">
        <button type="button" id="btnSend" class="button" style="min-width: 300px; font-size: 18px;" onclick="sendMessages()">🚀 INICIAR ENVIO EM MASSA</button>
        <button type="button" id="btnStop" class="button button-danger" style="min-width: 300px; font-size: 18px; display: none;" onclick="stopSending()">🛑 PARAR ENVIO</button>
    </div>
</div>

<!-- Modais -->
<div id="importModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">Importar Contatos Externos</div>
        <div class="modal-body">
            <div style="margin-bottom: 20px;">
                <label class="form-label">Opção 1: Upload de VCF</label>
                <input type="file" id="vcfFile" accept=".vcf" class="form-input-full" style="padding: 8px;">
                <small style="color: #666;">Importe arquivos VCF do Google Contatos, Outlook ou celular.</small>
            </div>
            <div style="border-top: 1px solid #eee; padding-top: 15px;">
                <label class="form-label">Opção 2: Entrada Manual</label>
                <textarea id="manualImport" class="form-input-full" style="min-height: 100px; font-size: 13px;" placeholder="Nome;Celular (um por linha)"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button3" onclick="$('#importModal').removeClass('active')">Cancelar</button>
            <button type="button" class="button" onclick="processImport()">Importar</button>
        </div>
    </div>
</div>

<div id="saveListModal" class="modal"><div class="modal-content"><div class="modal-header">Salvar Lista de Contatos</div><div class="modal-body"><label class="form-label">Nome da Lista</label><input type="text" id="newListName" class="form-input-full" placeholder="Ex: Clientes Vencidos Jan"></div><div class="modal-footer"><button type="button" class="button3" onclick="$('#saveListModal').removeClass('active')">Cancelar</button><button type="button" class="button" onclick="saveCurrentList()">Salvar</button></div></div></div>
<div id="loadListModal" class="modal"><div class="modal-content" style="max-width: 600px;"><div class="modal-header">Gerenciar Listas Salvas</div><div id="savedListsContainer" class="saved-items"></div><div class="modal-footer"><button type="button" class="button3" onclick="$('#loadListModal').removeClass('active')">Fechar</button></div></div></div>
<div id="saveMessageModal" class="modal"><div class="modal-content"><div class="modal-header">Salvar Modelo de Mensagem</div><div class="modal-body"><label class="form-label">Nome do Modelo</label><input type="text" id="newMessageName" class="form-input-full" placeholder="Ex: Aviso de Vencimento"></div><div class="modal-footer"><button type="button" class="button3" onclick="$('#saveMessageModal').removeClass('active')">Cancelar</button><button type="button" class="button" onclick="saveCurrentMessage()">Salvar</button></div></div></div>
<div id="loadMessageModal" class="modal"><div class="modal-content" style="max-width: 600px;"><div class="modal-header">Modelos de Mensagem</div><div id="savedMessagesContainer" class="saved-items"></div><div class="modal-footer"><button type="button" class="button3" onclick="$('#loadMessageModal').removeClass('active')">Fechar</button></div></div></div>
<div id="editListModal" class="modal"><div class="modal-content"><div class="modal-header">Editar Lista de Contatos</div><div class="modal-body"><label class="form-label">Nome da Lista</label><input type="text" id="editListName" class="form-input-full" placeholder="Ex: Clientes Vencidos Jan"><div style="margin-top: 16px; margin-bottom: 12px;"><input type="text" id="searchEdit" class="form-input-full" placeholder="🔍 Pesquisar por nome ou celular..." onkeyup="filterClients('edit')"></div><div id="editListClientsContainer" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 12px;"></div></div><div class="modal-footer"><button type="button" class="button3" onclick="$('#editListModal').removeClass('active')">Cancelar</button><button type="button" class="button" onclick="saveEditedList()">Salvar Alteracoes</button></div></div></div>
<div id="editMessageModal" class="modal"><div class="modal-content"><div class="modal-header">Editar Modelo de Mensagem</div><div class="modal-body"><label class="form-label">Nome do Modelo</label><input type="text" id="editMessageName" class="form-input-full" placeholder="Ex: Aviso de Vencimento"><label class="form-label" style="margin-top: 16px;">Conteudo</label><textarea id="editMessageContent" class="form-input-full" style="min-height: 180px; font-family: 'Courier New', Courier, monospace; font-size: 14px;"></textarea></div><div class="modal-footer"><button type="button" class="button3" onclick="$('#editMessageModal').removeClass('active')">Cancelar</button><button type="button" class="button" onclick="saveEditedMessage()">Salvar Alteracoes</button></div></div></div>

<div id="overlay" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">Status do Envio em Massa</div>
        <div id="overlay-progress" style="font-weight: bold; margin-bottom: 10px; color: var(--primary);"></div>
        <div id="overlay-content" style="max-height: 400px; overflow-y: auto; background: #f8f9fa; padding: 15px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-family: 'Courier New', Courier, monospace; font-size: 13px; line-height: 1.5;"></div>
        <div class="modal-footer">
            <button type="button" id="btn-parar" class="button button-danger" onclick="stopSending()">🛑 Parar Envio</button>
            <button type="button" id="btn-fechar" class="button" onclick="$('#overlay').hide();" style="display:none;">Fechar</button>
        </div>
    </div>
</div>

<script>
let allClients = <?php echo json_encode($allClients); ?>;
let selectedClients = [];
let isStopped = false;

const PROVEDOR_NOME = "<?php echo $provedor ?? 'PROVEDOR'; ?>";
const PROVEDOR_SITE = "<?php echo $site ?? 'www.site.com.br'; ?>";
const TEMPO_MIN = <?php echo $tMin * 1000; ?>;
const TEMPO_MAX = <?php echo $tMax * 1000; ?>;

$(document).ready(function() {
    loadSavedLists();
    loadSavedMessages();
    updateRecipientCount();
    $('#messageContent').on('input', updatePreview);
    $('input[name="recipientType"]').on('change', function() {
        $('#listSelection').toggle(this.value === 'list');
        $('#manualSelection').toggle(this.value === 'manual');
        updateRecipientCount();
    });
    $('#savedLists').on('change', function() { if (this.value) loadList(this.value); });
});

function updatePreview() {
    const input = $('#messageContent').val();
    const previewContainer = $('#preview');
    const mockData = { '%nomeresumido%': '<b>João</b>', '%provedor%': `<b>${PROVEDOR_NOME}</b>`, '%site%': `<b>${PROVEDOR_SITE}</b>` };
    previewContainer.empty();
    if (!input.trim()) { previewContainer.html('<span style="color: #999;">Sua pré-visualização aparecerá aqui...</span>'); return; }
    input.split('##').forEach(text => {
        if (text.trim() === '') return;
        let content = text.split('%0A').join('\n');
        for (const [key, value] of Object.entries(mockData)) { content = content.replace(new RegExp(key, 'gi'), value); }
        content = content.replace(/\*(.*?)\*/g, '<b>$1</b>');
        $('<div class="whatsapp-bubble"></div>').html(content).appendTo(previewContainer);
    });
}

function updateRecipientCount() {
    const type = $('input[name="recipientType"]:checked').val();
    if (type === 'all') { 
        selectedClients = allClients.map(c => c.id); 
    } else if (type === 'manual') { 
        selectedClients = []; 
        $('.checkbox-item input[type="checkbox"]:checked').each(function() { selectedClients.push($(this).val()); }); 
    } else if (type === 'list') {
        if (!$('#savedLists').val()) selectedClients = [];
    }
    $('#recipientCount').text(selectedClients.length);
}

function selectAllClients() { $('.checkbox-item input[type="checkbox"]').prop('checked', true); updateRecipientCount(); }
function deselectAllClients() { $('.checkbox-item input[type="checkbox"]').prop('checked', false); updateRecipientCount(); }

function filterClients(type) {
    const searchValue = (type === 'manual' ? $('#searchManual').val() : $('#searchEdit').val()).toLowerCase();
    const container = type === 'manual' ? '#clientsList' : '#editListClientsContainer';
    $(container + ' .checkbox-item').each(function() {
        const text = $(this).text().toLowerCase();
        $(this).toggle(text.includes(searchValue));
    });
}

function openImportModal() { $('#importModal').addClass('active'); }

function processImport() {
    const manual = $('#manualImport').val().trim();
    const file = $('#vcfFile')[0].files[0];
    let clientsToImport = [];

    if (manual) {
        manual.split('\n').forEach(line => {
            const parts = line.split(';');
            if (parts.length >= 2) {
                clientsToImport.push({ nome: parts[0].trim().toUpperCase(), celular: parts[1].trim().replace(/\D/g, '') });
            }
        });
    }

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const text = e.target.result;
            const contacts = parseVCF(text);
            clientsToImport = clientsToImport.concat(contacts);
            sendImportRequest(clientsToImport);
        };
        reader.readAsText(file);
    } else if (clientsToImport.length > 0) {
        sendImportRequest(clientsToImport);
    } else {
        alert('Selecione um arquivo ou digite contatos manualmente.');
    }
}

function parseVCF(vcfText) {
    const contacts = [];
    const vCards = vcfText.split('BEGIN:VCARD');
    vCards.forEach(vCard => {
        if (!vCard.includes('END:VCARD')) return;
        let nome = '';
        let celular = '';
        const lines = vCard.split('\n');
        lines.forEach(line => {
            line = line.trim();
            if (line.startsWith('FN:')) {
                nome = line.substring(3).trim().toUpperCase();
            }
            if (line.startsWith('TEL')) {
                const match = line.match(/:(.*)$/);
                if (match) {
                    const phone = match[1].trim().replace(/\D/g, '');
                    if (phone && !celular) celular = phone;
                }
            }
        });
        if (nome && celular) {
            contacts.push({ nome: nome, celular: celular });
        }
    });
    return contacts;
}

function sendImportRequest(clients) {
    $.post('', { action: 'importClients', clients: JSON.stringify(clients) }, function(res) {
        if (res.success) { alert('Contatos importados!'); location.reload(); }
    });
}

function deleteSelectedImported() {
    const selectedImported = [];
    $('.checkbox-item input[type="checkbox"]:checked').each(function() {
        const id = $(this).val();
        const client = allClients.find(c => c.id == id);
        if (client && client.origem === 'imp') {
            selectedImported.push(id);
        }
    });
    
    if (selectedImported.length === 0) {
        return alert('Selecione apenas contatos importados para excluir.');
    }
    
    if (confirm(`Excluir ${selectedImported.length} contato(s) importado(s) selecionado(s)?`)) {
        $.post('', { action: 'deleteImportedClients', clientIds: JSON.stringify(selectedImported) }, function(res) {
            if (res.success) location.reload();
        });
    }
}

function clearAllImported() {
    if (confirm('Deseja remover TODOS os contatos importados?')) {
        $.post('', { action: 'clearImported' }, function(res) { if (res.success) location.reload(); });
    }
}

function openSaveListModal() { if (selectedClients.length === 0) return alert('Selecione clientes primeiro'); $('#saveListModal').addClass('active'); }

function saveCurrentList() {
    const name = $('#newListName').val();
    if (!name) return alert('Digite um nome');
    updateRecipientCount();
    const clientsData = selectedClients.map(id => allClients.find(c => c.id == id)).filter(c => c);
    $.post('', { action: 'saveList', name, clients: JSON.stringify(clientsData), isEdit: 'false' }, function(res) {
        if (res.success) { alert('Lista salva com sucesso!'); location.reload(); }
        else { alert(res.error || 'Erro ao salvar a lista.'); }
    });
}

function loadSavedLists() {
    $.post('', { action: 'listFiles', type: 'lists' }, function(res) {
        let options = '<option value="">Selecione uma lista...</option>';
        let container = '';
        if (res && res.files && Array.isArray(res.files)) {
            res.files.forEach(file => {
                const displayName = file.replace('.json', '').replace(/_/g, ' ');
                options += `<option value="${file}">${displayName}</option>`;
                container += `<div class="saved-item"><div class="saved-item-info"><div class="saved-item-name">${displayName}</div></div><div class="saved-item-actions"><button class="button button-small" onclick="loadList('${file}')">Carregar</button><button class="button button-small" onclick="editList('${file}')">Editar</button><button class="button button-danger button-small" onclick="deleteFile('${file}', 'lists')">Excluir</button></div></div>`;
            });
        }
        $('#savedLists').html(options); $('#savedListsContainer').html(container);
    });
}

function loadList(filename) {
    $.post('', { action: 'loadList', filename }, function(res) {
        if (res.success) {
            const clientIds = res.data.clientIds || [];
            selectedClients = allClients.filter(c => clientIds.includes(c.id)).map(c => c.id);
            $('#recipientCount').text(selectedClients.length);
            $('.checkbox-item input[type="checkbox"]').prop('checked', false);
            selectedClients.forEach(id => $(`#client_${id}`).prop('checked', true));
            if ($('input[name="recipientType"]:checked').val() !== 'list') {
                $('input[name="recipientType"][value="list"]').prop('checked', true).trigger('change');
            }
        }
    });
}

function openSaveMessageModal() { if (!$('#messageContent').val()) return alert('Digite uma mensagem'); $('#saveMessageModal').addClass('active'); }
function saveCurrentMessage() {
    const name = $('#newMessageName').val();
    $.post('', { action: 'saveMessage', name, content: $('#messageContent').val(), isEdit: 'false' }, function(res) {
        if (res.success) { alert('Mensagem salva!'); $('#saveMessageModal').removeClass('active'); loadSavedMessages(); }
        else { alert(res.error || 'Erro ao salvar a mensagem.'); }
    });
}

function loadSavedMessages() {
    $.post('', { action: 'listFiles', type: 'messages' }, function(res) {
        let container = '';
        if (res && res.files && Array.isArray(res.files)) {
            res.files.forEach(file => {
                const displayName = file.replace('.json', '').replace(/_/g, ' ');
                container += `<div class="saved-item"><div class="saved-item-info"><div class="saved-item-name">${displayName}</div></div><div class="saved-item-actions"><button class="button button-small" onclick="loadMessage('${file}')">Carregar</button><button class="button button-small" onclick="editMessage('${file}')">Editar</button><button class="button button-danger button-small" onclick="deleteFile('${file}', 'messages')">Excluir</button></div></div>`;
            });
        }
        $('#savedMessagesContainer').html(container);
    });
}

function loadMessage(filename) {
    $.post('', { action: 'loadMessage', filename }, function(res) {
        if (res.success) { $('#messageContent').val(res.data.content).trigger('input'); $('#loadMessageModal').removeClass('active'); }
    });
}

function deleteFile(filename, type) {
    if (confirm('Excluir este item?')) {
        $.post('', { action: 'deleteFile', filename, type }, function(res) {
            if (res.success) { if (type === 'lists') loadSavedLists(); else loadSavedMessages(); }
        });
    }
}

async function sendMessages() {
    const message = $('#messageContent').val().trim();
    updateRecipientCount();
    if (selectedClients.length === 0) return alert('Selecione destinatários');
    if (!message) return alert('Digite uma mensagem');
    if (!confirm(`Enviar para ${selectedClients.length} cliente(s)?`)) return;
    isStopped = false;
    $('#overlay').css('display', 'flex'); $('#overlay-content').empty(); $('#btn-parar').show(); $('#btn-fechar').hide();
    const clientsData = selectedClients.map(id => allClients.find(c => c.id == id)).filter(c => c);
    for (let i = 0; i < clientsData.length; i++) {
        if (isStopped) { $('#overlay-content').append('<br><b style="color:red;">⚠️ Interrompido.</b>'); break; }
        const contato = clientsData[i];
        $('#overlay-progress').text(`Progresso: ${i + 1}/${clientsData.length}`);
        try {
            const res = await $.ajax({ url: '', method: 'POST', data: { action: 'sendMessage', contato: JSON.stringify(contato), message }, dataType: 'json' });
            const status = res.success ? '<span style="color:green;">Sucesso</span>' : '<span style="color:red;">Falha</span>';
            $('#overlay-content').append(`<br>Enviando para: <b>${res.nome}</b>... ${status}`);
            $('#overlay-content').scrollTop($('#overlay-content')[0].scrollHeight);
        } catch (e) { $('#overlay-content').append(`<br><span style="color:red;">Erro ao enviar para ${contato.nome}</span>`); }
        const delay = Math.floor(Math.random() * (TEMPO_MAX - TEMPO_MIN + 1) + TEMPO_MIN);
        if (delay > 0 && i < clientsData.length - 1) {
            const segs = Math.round(delay / 1000);
            $('#overlay-content').append(`<br><small style="color:#999;">Aguardando ${segs}s para o próximo envio...</small>`);
            $('#overlay-content').scrollTop($('#overlay-content')[0].scrollHeight);
            await new Promise(r => setTimeout(r, delay));
        }
    }
    $('#overlay-content').append('<br><div class="badge badge-success mt-3">✅ Concluído!</div>'); $('#btn-parar').hide(); $('#btn-fechar').show();
}

function stopSending() { isStopped = true; $('#btn-parar').prop('disabled', true).text('Parando...'); }
function openLoadListModal() { $('#loadListModal').addClass('active'); }
function openLoadMessageModal() { $('#loadMessageModal').addClass('active'); }

let currentEditListFilename = '';
let currentEditMessageFilename = '';

function editList(filename) {
    $.post('', { action: 'loadList', filename }, function(res) {
        if (res.success) {
            currentEditListFilename = filename;
            const data = res.data;
            $('#editListName').val(data.name);
            let clientsHtml = '';
            const clientIds = data.clientIds || [];
            allClients.forEach(client => {
                const isChecked = clientIds.includes(client.id) ? 'checked' : '';
                clientsHtml += `<div class="checkbox-item"><input type="checkbox" id="edit_client_${client.id}" value="${client.id}" ${isChecked} class="check"><label for="edit_client_${client.id}"><span class="badge-${client.origem}">${client.origem.toUpperCase()}</span><strong>${client.nome}</strong> - ${client.celular}</label></div>`;
            });
            $('#editListClientsContainer').html(clientsHtml);
            $('#editListModal').addClass('active');
        }
    });
}

function saveEditedList() {
    const name = $('#editListName').val();
    if (!name) return alert('Digite um nome');
    const editedClients = [];
    $('#editListClientsContainer input[type="checkbox"]:checked').each(function() {
        const id = $(this).val();
        const client = allClients.find(c => c.id == id);
        if (client) editedClients.push(client);
    });
    if (editedClients.length === 0) return alert('Selecione clientes para salvar a lista');
    $.post('', { action: 'editList', oldFilename: currentEditListFilename, name, clients: JSON.stringify(editedClients), isEdit: 'true' }, function(res) {
        if (res.success) { alert('Lista atualizada com sucesso!'); location.reload(); }
        else { alert(res.error || 'Erro ao atualizar a lista.'); }
    });
}

function editMessage(filename) {
    $.post('', { action: 'loadMessage', filename }, function(res) {
        if (res.success) {
            currentEditMessageFilename = filename;
            const data = res.data;
            $('#editMessageName').val(data.name);
            $('#editMessageContent').val(data.content);
            $('#editMessageModal').addClass('active');
        }
    });
}

function saveEditedMessage() {
    const name = $('#editMessageName').val();
    const content = $('#editMessageContent').val();
    if (!name) return alert('Digite um nome');
    if (!content) return alert('Digite o conteudo da mensagem');
    $.post('', { action: 'editMessage', oldFilename: currentEditMessageFilename, name, content, isEdit: 'true' }, function(res) {
        if (res.success) { alert('Mensagem atualizada com sucesso!'); $('#editMessageModal').removeClass('active'); loadSavedMessages(); }
        else { alert(res.error || 'Erro ao atualizar a mensagem.'); }
    });
}
</script>
</body>
</html>
