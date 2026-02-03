<?php include 'header.php'; ?>

<!-- Cabeçalho da Página -->
<div class="container">
    <div class="card mb-3">
        <h2 class="title-config">
            💬 Configuração de Mensagens
        </h2>
        <p class="text-subtitle">
            Configure as mensagens automáticas do WhatsApp para cada tipo de situação.
        </p>
    </div>

<!-- Menu de Navegação -->
    <div class="menu card mb-3">
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <button class="button3" onclick="location.href='index.php'" type="button">📅 No prazo</button>
            <button class="button3" onclick="location.href='vencido.php'" type="button">⚠️ Vencidos</button>
            <button class="button3" onclick="location.href='pago.php'" type="button">✅ Pagos</button>
            <button class="button3" onclick="location.href='emmassa.php'" type="button">📢 Em massa</button>
            <button class="button2" onclick="location.href='confmsg.php'" type="button" style="background-color: var(--secondary); border: 2px solid var(--secondary);">💬 Conf. msg</button>
            <button class="button3" onclick="location.href='confweb.php'" type="button">⚙️ Conf. geral</button>
        </div>
    </div>

<?php
$db = new SQLite3('db/msgdb.sqlite3');

function salvarMensagem($db, $tabela, $coluna, $conteudo) {
    $db->exec("DELETE FROM $tabela");
    $stmt = $db->prepare("INSERT INTO $tabela ($coluna) VALUES (:conteudo)");
    $stmt->bindValue(':conteudo', $conteudo, SQLITE3_TEXT);
    return $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['msgnoprazo'])) salvarMensagem($db, 'msgnoprazo', 'msg', $_POST['msgnoprazo']);
    if (isset($_POST['msgvencido'])) salvarMensagem($db, 'msgvencido', 'msg', $_POST['msgvencido']);
    if (isset($_POST['msgpago']))    salvarMensagem($db, 'msgpago', 'msg', $_POST['msgpago']);
    echo "<script>alert('✅ Configurações salvas com sucesso!'); window.location.href='confmsg.php';</script>";
}

$msgnoprazo = $db->querySingle("SELECT msg FROM msgnoprazo") ?: "";
$msgvencido = $db->querySingle("SELECT msg FROM msgvencido") ?: "";
$msgpago    = $db->querySingle("SELECT msg FROM msgpago") ?: "";
?>

    <div class="container-conf">
        <form method="POST">
            <!-- BLOCO NO PRAZO -->
            <div class="msg-section">
                <div class="section-header">
                    📅 MENSAGEM: NO PRAZO
                </div>
                <div class="flex-layout">
                    <div class="editor-side">
                        <label class="form-label">
                            ✏️ Editor de Mensagem
                        </label>
                        <textarea name="msgnoprazo" id="input-noprazo" oninput="updatePreview('noprazo')"><?php echo htmlspecialchars($msgnoprazo); ?></textarea>
                        <div class="coringas-list">
                            <b>📌 Coringas Disponíveis:</b><br>
                            %cliente%, %vencimento%, %valor%, %linhadig%, %qrcode%, %copiacola%, %provedor%, %site%<br>
                            <b>⚡ Comandos:</b> %0A (Quebra de Linha), ## (Novo Balão)
                        </div>
                    </div>
                    <div class="preview-side">
                        <label style="display: block; font-weight: 600; margin-bottom: 12px; color: #075e54;">
                            👁️ Pré-visualização WhatsApp
                        </label>
                        <div id="preview-noprazo"></div>
                    </div>
                </div>
            </div>
    
            <!-- BLOCO VENCIDO -->
            <div class="msg-section">
                <div class="section-header vencido">
                    ⚠️ MENSAGEM: VENCIDO
                </div>
                <div class="flex-layout">
                    <div class="editor-side">
                        <label class="form-label">
                            ✏️ Editor de Mensagem
                        </label>
                        <textarea name="msgvencido" id="input-vencido" oninput="updatePreview('vencido')"><?php echo htmlspecialchars($msgvencido); ?></textarea>
                        <div class="coringas-list">
                            <b>📌 Coringas Disponíveis:</b><br>
                            %cliente%, %vencimento%, %valor%, %linhadig%, %qrcode%, %copiacola%, %provedor%, %site%<br>
                            <b>⚡ Comandos:</b> %0A (Quebra de Linha), ## (Novo Balão)
                        </div>
                    </div>
                    <div class="preview-side">
                        <label style="display: block; font-weight: 600; margin-bottom: 12px; color: #075e54;">
                            👁️ Pré-visualização WhatsApp
                        </label>
                        <div id="preview-vencido"></div>
                    </div>
                </div>
            </div>
    
            <!-- BLOCO PAGO -->
            <div class="msg-section">
                <div class="section-header pago">
                    ✅ MENSAGEM: PAGO
                </div>
                <div class="flex-layout">
                    <div class="editor-side">
                        <label class="form-label">
                            ✏️ Editor de Mensagem
                        </label>
                        <textarea name="msgpago" id="input-pago" oninput="updatePreview('pago')"><?php echo htmlspecialchars($msgpago); ?></textarea>
                        <div class="coringas-list">
                            <b>📌 Coringas Disponíveis:</b><br>
                            %cliente%, %valor%, %datapago%, %provedor%, %site%<br>
                            <b>⚡ Comandos:</b> %0A (Quebra de Linha), ## (Novo Balão)
                        </div>
                    </div>
                    <div class="preview-side">
                        <label style="display: block; font-weight: 600; margin-bottom: 12px; color: #075e54;">
                            👁️ Pré-visualização WhatsApp
                        </label>
                        <div id="preview-pago"></div>
                    </div>
                </div>
            </div>
    
            <div class="text-center" style="margin: 40px 0 20px 0;">
                <button type="submit" class="button" style="min-width: 300px; font-size: 16px; padding: 14px 32px;">
                    💾 SALVAR CONFIGURAÇÕES
                </button>
            </div>
        </form>
    </div>

</div>

<script>
function updatePreview(type) {
    const input = document.getElementById('input-' + type).value;
    const previewContainer = document.getElementById('preview-' + type);
    
    const mockData = {
        '%cliente%': '<b>João da Silva</b>',
        '%nomeresumido%': '<b>João</b>',
        '%vencimento%': '<b>10/02/2026</b>',
        '%valor%': '<b>R$ 99,90</b>',
        '%linhadig%': '<b>00190.00009 02714.720008 05071.402272 9 0000000000</b>',
        '%qrcode%': '<b>[QR CODE]</b>',
        '%copiacola%': '<b>00020101021226850014br.gov.bcb.pix...</b>',
        '%provedor%': '<b><?php echo $provedor; ?></b>',
        '%site%': '<b><?php echo $site; ?></b>',
        '%datapago%': '<b>27/01/2026</b>'
    };

    previewContainer.innerHTML = '';

    // 1. Divide por ## para novos balões
    const bubbles = input.split('##');

    bubbles.forEach(text => {
        if (text.trim() === '') return;

        let content = text;

        // 2. Substitui %0A por quebra de linha real (\n)
        content = content.split('%0A').join('\n');

        // 3. Substitui os coringas
        for (const [key, value] of Object.entries(mockData)) {
            content = content.split(key).join(value);
        }

        // 4. Formatação básica de negrito do WhatsApp (*texto*)
        content = content.replace(/\*(.*?)\*/g, '<b>$1</b>');

        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'whatsapp-bubble';
        bubbleDiv.innerHTML = content;
        previewContainer.appendChild(bubbleDiv);
    });
}

// Inicializa
window.onload = function() {
    updatePreview('noprazo');
    updatePreview('vencido');
    updatePreview('pago');
};
</script>

</body>
</html>

