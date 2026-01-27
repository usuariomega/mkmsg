<?php
include 'config.php';


$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$result = $conn->query($cronpago);
$dados = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dados[] = array($row["nome_res"], $row["celular"], $row["datavenc"], $row["datapag"], $row["linhadig"], $row["qrcode"]);
    }
} else {
    echo "\nSem dados para enviar! \n";
    $conn->close();
    exit();
}
$conn->close();


if (!empty($_POST) && isset($_POST['posttodos'])) {

    $db = new SQLite3('db/msgdb.sqlite3');
    $msgpago = $db->querySingle("SELECT msg FROM msgpago");
    $db->close();

    if (ob_get_level() == 0) { ob_start(); }

    foreach ($dados as $contato) {
        list($nome, $celular, $datavenc, $datapag, $linhadig, $qrcode) = $contato;

        $buscar = array('/%provedor%/', '/%nomeresumido%/', '/%vencimento%/', '/%pagamento%/', '/%linhadig%/', '/%copiacola%/', '/%site%/');
        $substituir = array($provedor, $nome, $datavenc, $datapag, $linhadig, $qrcode, $site);
        $msgFinal = preg_replace($buscar, $substituir, $msgpago);

        echo "\n" . date('d-M-Y H:i:s') . " - Enviando para: " . str_pad($nome, 25);

        $payload = ["numero" => "55" . $celular, "mensagem" => $msgFinal];
        $ch = curl_init("http://$wsip:8000/send");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "x-api-token: $token"],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);


        $month = date("Y-m");
        $root = $_SERVER["DOCUMENT_ROOT"] . "/mkmsg";
        $dir = "$root/logs/$month/pago";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            if (file_exists("$root/logs/.ler/modelo/index.php")) {
                copy("$root/logs/.ler/modelo/index.php", "$dir/index.php");
            }
        }

        $logFile = "$dir/pago_" . date("d-M-Y") . ".log";

        $logData = sprintf("%s;%s;%s;%s\n", date("d-m-Y"), date("H:i:s"), $nome, $err ?: $response);
        file_put_contents($logFile, $logData, FILE_APPEND);

        echo $err ? "Erro: $err" : "Resposta: $response";

        ob_flush();
        flush();

        if ($tempomax > 0 && $nome !== 'Vazio') { sleep(rand($tempomin, $tempomax)); }
    }
    ob_end_flush();
    echo "\n\nFim do envio!\n\n";
} else {
    echo "Use o Cron para automatizar esse envio.\n";
    echo "Comando exemplo:\n";
    echo "0 9 * * * curl -X POST -F 'posttodos=1' http://admin:suasenha@127.0.0.1/mkmsg/cronpago.php > /dev/null 2>&1\n";
}
?>

