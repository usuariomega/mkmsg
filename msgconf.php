<?php include 'header.php';?>

<div class="menu">
    <button class="button3" onclick="location.href='index.php'" type="button">No prazo</button>
    <button class="button3" onclick="location.href='vencido.php'" type="button">Vencidos</button>
    <button class="button3" onclick="location.href='pago.php'" type="button">Pagos</button>
    <button class="button2" onclick="location.href='msgconf.php'" type="button">Conf. msg</button>
</div><br>

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
    echo "<script>alert('Configurações salvas com sucesso!'); window.location.href='msgconf.php';</script>";
}

$msgnoprazo = $db->querySingle("SELECT msg FROM msgnoprazo") ?: "";
$msgvencido = $db->querySingle("SELECT msg FROM msgvencido") ?: "";
$msgpago    = $db->querySingle("SELECT msg FROM msgpago") ?: "";
?>

<style>
    .container-conf {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    .msg-section {
        margin-bottom: 40px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    .section-header {
        background: #075e54;
        color: white;
        padding: 10px 20px;
        font-weight: bold;
    }
    .flex-layout {
        display: flex;
        flex-wrap: wrap;
        gap: 0;
    }
    .editor-side {
        flex: 1;
        min-width: 350px;
        padding: 20px;
        border-right: 1px solid #eee;
    }
    .preview-side {
        flex: 1;
        min-width: 350px;
        background-color: #e5ddd5;
        background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png');
        background-repeat: repeat;
        padding: 20px;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    textarea {
        width: 100%;
        height: 180px;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-family: 'Courier New', Courier, monospace;
        font-size: 14px;
        resize: vertical;
        margin-bottom: 10px;
    }
    .whatsapp-bubble {
        background: #fff;
        padding: 8px 12px;
        border-radius: 7.5px;
        position: relative;
        max-width: 85%;
        font-size: 14px;
        line-height: 1.4;
        box-shadow: 0 1px 0.5px rgba(0,0,0,0.13);
        margin-bottom: 10px;
        word-wrap: break-word;
        white-space: pre-wrap;
    }
    .whatsapp-bubble::before {
        content: "";
        position: absolute;
        width: 0;
        height: 0;
        border-top: 10px solid transparent;
        border-bottom: 10px solid transparent;
        border-right: 10px solid #fff;
        left: -10px;
        top: 0;
    }
    .coringas-list {
        font-size: 12px;
        color: #555;
        background: #f1f1f1;
        padding: 10px;
        border-radius: 4px;
        margin-top: 10px;
        line-height: 1.6;
    }
    .coringas-list b { color: #075e54; }
    
    @media (max-width: 768px) {
        .editor-side { border-right: none; border-bottom: 1px solid #eee; }
    }
</style>

<div class="container-conf">
    <form method="POST">
        <!-- BLOCO NO PRAZO -->
        <div class="msg-section">
            <div class="section-header">MENSAGEM: NO PRAZO</div>
            <div class="flex-layout">
                <div class="editor-side">
                    <textarea name="msgnoprazo" id="input-noprazo" oninput="updatePreview('noprazo')"><?php echo htmlspecialchars($msgnoprazo); ?></textarea>
                    <div class="coringas-list">
                        <b>Coringas Disponíveis:</b><br>
                        %cliente%, %vencimento%, %valor%, %linhadig%, %qrcode%, %copiacola%, %provedor%, %site%<br>
                        <b>Comandos:</b> %0A (Quebra de Linha), ## (Novo Balão)
                    </div>
                </div>
                <div class="preview-side" id="preview-noprazo"></div>
            </div>
        </div>

        <!-- BLOCO VENCIDO -->
        <div class="msg-section">
            <div class="section-header">MENSAGEM: VENCIDO</div>
            <div class="flex-layout">
                <div class="editor-side">
                    <textarea name="msgvencido" id="input-vencido" oninput="updatePreview('vencido')"><?php echo htmlspecialchars($msgvencido); ?></textarea>
                    <div class="coringas-list">
                        <b>Coringas Disponíveis:</b><br>
                        %cliente%, %vencimento%, %valor%, %linhadig%, %qrcode%, %copiacola%, %provedor%, %site%<br>
                        <b>Comandos:</b> %0A (Quebra de Linha), ## (Novo Balão)
                    </div>
                </div>
                <div class="preview-side" id="preview-vencido"></div>
            </div>
        </div>

        <!-- BLOCO PAGO -->
        <div class="msg-section">
            <div class="section-header">MENSAGEM: PAGO</div>
            <div class="flex-layout">
                <div class="editor-side">
                    <textarea name="msgpago" id="input-pago" oninput="updatePreview('pago')"><?php echo htmlspecialchars($msgpago); ?></textarea>
                    <div class="coringas-list">
                        <b>Coringas Disponíveis:</b><br>
                        %cliente%, %valor%, %datapago%, %provedor%, %site%<br>
                        <b>Comandos:</b> %0A (Quebra de Linha), ## (Novo Balão)
                    </div>
                </div>
                <div class="preview-side" id="preview-pago"></div>
            </div>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <button type="submit" class="button">SALVAR CONFIGURAÇÕES</button>
        </div>
    </form>
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
