<?php
/**
 * MK-MSG - Automação Pagos (CLI Only)
 */

// Impede execução via navegador
if (php_sapi_name() !== 'cli') {
    die("Acesso negado: Este script deve ser executado apenas via CLI.\n");
}

include 'config.php';

// Garantir que $diaspago é um array
if (!is_array($diaspago)) {
    $diaspago = [$diaspago];
}

// Conectar ao banco de dados
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { 
    die("Erro de conexão: " . $conn->connect_error . "\n"); 
}

foreach ($diaspago as $diaspagoAtual) {
    $queryPago = "SELECT upper(vtab_titulos.nome_res) as nome_res,
                  REGEXP_REPLACE(vtab_titulos.celular,'[( )-]+','') AS `celular`,
                  DATE_FORMAT(vtab_titulos.datapag,'%d/%m/%y') AS `datapag`,
                  vtab_titulos.linhadig, sis_qrpix.qrcode
                  FROM vtab_titulos
                  INNER JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
                  WHERE DATEDIFF(CURRENT_DATE(), vtab_titulos.datapag) = $diaspagoAtual
                  AND (vtab_titulos.status = 'pago')
                  AND (vtab_titulos.cli_ativado = 's')
                  GROUP BY vtab_titulos.uuid_lanc
                  ORDER BY nome_res ASC;";
    
    $result = $conn->query($queryPago);
    
    if (!$result || $result->num_rows === 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Sem registros para o dia $diaspagoAtual.\n";
        continue;
    }

    echo "[" . date('Y-m-d H:i:s') . "] Processando Dia $diaspagoAtual (" . $result->num_rows . " registros)...\n";

    $db = new SQLite3('db/msgdb.sqlite3');
    $msgpago = $db->querySingle("SELECT msg FROM msgpago");
    $db->close();

    while ($row = $result->fetch_assoc()) {
        $nome = $row['nome_res'];
        $celular = $row['celular'];
        $datapag = $row['datapag'];
        $linhadig = $row['linhadig'];
        $qrcode = $row['qrcode'];

        $buscar = array('/%provedor%/', '/%nomeresumido%/', '/%pagamento%/', '/%linhadig%/', '/%copiacola%/', '/%site%/');
        $substituir = array($provedor, $nome, $datapag, $linhadig, $qrcode, $site);
        $msgFinal = preg_replace($buscar, $substituir, $msgpago);

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
        $rootPath = dirname(__FILE__); 
        $dir = "$rootPath/logs/$month/pago";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            if (file_exists("$rootPath/logs/.ler/modelo/index.php")) {
                copy("$rootPath/logs/.ler/modelo/index.php", "$dir/index.php");
            }
        }

        $logFile = "$dir/pago_" . date("d-M-Y") . ".log";
        $logData = sprintf("%s;%s;%s;%s;Dia:%d\n", date("d-m-Y"), date("H:i:s"), $nome, $err ?: $response, $diaspagoAtual);
        file_put_contents($logFile, $logData, FILE_APPEND);

        echo "[" . date('H:i:s') . "] Enviado para: $nome | Status: " . ($err ? "Erro: $err" : "OK") . "\n";

        if ($tempomax > 0) { sleep(rand($tempomin, $tempomax)); }
    }
}

$conn->close();
echo "[" . date('Y-m-d H:i:s') . "] Automação Pagos finalizada.\n";
?>
