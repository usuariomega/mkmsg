<?php
include 'header.php';


ini_set('display_errors', 1);
error_reporting(E_ALL);


$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Erro de conexão: " . $conn->connect_error); }
$result = $conn->query($sqlnoprazo);
$dados = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dados[] = array($row["nome_res"], $row["celular"], $row["datavenc"], $row["linhadig"], $row["qrcode"]);
    }
} else {
    $dados[] = array('Vazio', '-', '-', '-', '-');
}
$conn->close();


function enviarMensagem($contato, $msgnoprazo, $vars, $wsip, $token, $tempomin, $tempomax) {
    list($nome, $celular, $datavenc, $linhadig, $qrcode) = $contato;
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
    if ($tempomax > 0 && $nome !== 'Vazio') { sleep(rand($tempomin, $tempomax)); }
}
?>

<div class="menu">
    <button class="button2" onclick="location.href='index.php'" type="button">No prazo</button>
    <button class="button3" onclick="location.href='vencido.php'" type="button">Vencidos</button>
    <button class="button3" onclick="location.href='pago.php'" type="button">Pagos</button>
    <button class="button3" onclick="location.href='msgconf.php'" type="button">Conf. msg</button>
</div>

<div id="overlay" class="overlay">
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['posttodos']) || isset($_POST['postsel']))) {
        $db = new SQLite3('db/msgdb.sqlite3');
        $msgnoprazo = $db->querySingle("SELECT msg FROM msgnoprazo");
        $db->close();
        $vars = ['provedor' => $provedor, 'site' => $site];
        if (ob_get_level() == 0) ob_start();
        if (isset($_POST['posttodos'])) {
            foreach ($dados as $contato) { if ($contato[0] !== 'Vazio') enviarMensagem($contato, $msgnoprazo, $vars, $wsip, $token, $tempomin, $tempomax); }
        } elseif (isset($_POST['postsel']) && isset($_POST['check'])) {
            foreach ($_POST['check'] as $index => $value) {
                if ($value == "1") {
                    $contato = [$_POST['nome'][$index], $_POST['celular'][$index], $_POST['datavenc'][$index], $_POST['linhadig'][$index], $_POST['qrcode'][$index]];
                    enviarMensagem($contato, $msgnoprazo, $vars, $wsip, $token, $tempomin, $tempomax);
                }
            }
        }
        echo "<p><b>Fim do processamento!</b></p>";
        ob_end_flush();
    }
    ?>
</div>

<form enctype="multipart/form-data" id="form" name="form" method="post">
    <table id="table_id" class="display responsive" width="100%">
        <thead>
            <tr>
                <th>NOME:</th>
                <th>CELULAR:</th>
                <th>DATA VENC:</th>
                <th><input type="checkbox" id="select_all"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dados as $row): list($nome, $celular, $datavenc, $linhadig, $qrcode) = array_pad($row, 5, ''); ?>
                <tr>
                    <td><input type="hidden" name="nome[]" value="<?= $nome ?>"><?= $nome ?></td>
                    <td><input type="hidden" name="celular[]" value="<?= $celular ?>"><?= $celular ?></td>
                    <td><input type="hidden" name="datavenc[]" value="<?= $datavenc ?>"><?= $datavenc ?></td>
                    <td>
                        <input type="hidden" name="linhadig[]" value="<?= $linhadig ?>">
                        <input type="hidden" name="qrcode[]" value="<?= $qrcode ?>">
                        <input type="hidden" name="check[]" value="0">
                        <input type="checkbox" class="check">
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="menu">
        <button class="button" name="posttodos" type="submit" onclick="return confirm('Confirma o Envio para TODOS?')">Enviar para todos</button>
        <button class="button" name="postsel" type="submit" id="btn_postsel">Enviar para selecionados</button>
        <button class="button" onclick="window.open('logs/', '_blank')" type="button">Verificar logs</button>
    </div>
</form>

<script>
$(document).ready(function() {
    var table = $('#table_id').DataTable();


    $('#select_all').on('click', function() {
        var rows = table.rows({ 'search': 'applied' }).nodes();
        $('input[type="checkbox"]', rows).prop('checked', this.checked);
        $('input[name="check[]"]', rows).val(this.checked ? "1" : "0");
    });


    $('#table_id tbody').on('change', 'input.check', function() {
        $(this).prev('input[name="check[]"]').val(this.checked ? "1" : "0");
    });


    $('#form').on('submit', function(e) {
        var form = this;
        var btnName = $(document.activeElement).attr('name');

        if (btnName === 'postsel') {
            if (!confirm('Confirma o Envio para os selecionados?')) {
                e.preventDefault();
                return false;
            }


            table.$('input[type="hidden"], input[type="checkbox"]:checked').each(function() {
                if(!$.contains(document, this)) {
                    $(form).append(
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('name', this.name)
                            .val(this.value)
                    );
                }
            });
        }
    });

    const overlay = document.getElementById('overlay');
    const observer = new MutationObserver(() => { overlay.scrollTop = overlay.scrollHeight; });
    observer.observe(overlay, { childList: true });
});
</script>

</body>
</html>