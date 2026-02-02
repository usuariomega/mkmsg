<?php
if (php_sapi_name() !== 'cli') {
    die("Acesso negado: Este script deve ser executado apenas via CLI.\n");
}

include 'config.php';

if (!is_array($diasnoprazo)) $diasnoprazo = [$diasnoprazo];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Erro de conexão: " . $conn->connect_error . "\n");

foreach ($diasnoprazo as $dias) {
    $sql = "SELECT upper(vtab_titulos.nome_res) as nome_res, REGEXP_REPLACE(vtab_titulos.celular,'[( )-]+','') AS celular,
            DATE_FORMAT(vtab_titulos.datavenc,'%d/%m/%y') AS datavenc, vtab_titulos.linhadig, sis_qrpix.qrcode
            FROM vtab_titulos
            INNER JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
            WHERE DATEDIFF(vtab_titulos.datavenc, CURRENT_DATE()) = $dias
            AND vtab_titulos.status = 'aberto' AND vtab_titulos.cli_ativado = 's'
            AND TRIM(IFNULL(vtab_titulos.linhadig, '')) <> '' AND TRIM(IFNULL(sis_qrpix.qrcode, '')) <> ''
            GROUP BY vtab_titulos.uuid_lanc ORDER BY nome_res ASC";
    
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Sem registros para o dia $dias.\n";
        continue;
    }

    echo "[" . date('Y-m-d H:i:s') . "] Processando Dia $dias (" . $result->num_rows . " registros)...\n";

    $db = new SQLite3('db/msgdb.sqlite3');
    $msg = $db->querySingle("SELECT msg FROM msgnoprazo");
    $db->close();

    while ($row = $result->fetch_assoc()) {
        $nome = $row['nome_res'];
        $buscar = ['/%provedor%/', '/%nomeresumido%/', '/%vencimento%/', '/%linhadig%/', '/%copiacola%/', '/%site%/'];
        $substituir = [$provedor, $nome, $row['datavenc'], $row['linhadig'], $row['qrcode'], $site];
        $msgFinal = preg_replace($buscar, $substituir, $msg);

        $payload = ["numero" => "55" . $row['celular'], "mensagem" => $msgFinal];
        $ch = curl_init("http://$wsip:8000/send");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "x-api-token: $token"],
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        $month = date("Y-m");
        $root = dirname(__FILE__);
        $dir = "$root/logs/$month/noprazo";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            if (file_exists("$root/logs/.ler/modelo/index.php")) copy("$root/logs/.ler/modelo/index.php", "$dir/index.php");
        }

        $logFile = "$dir/noprazo_" . date("d-M-Y") . ".log";
        $logData = sprintf("%s;%s;%s;%s;Dia:%d\n", date("d-m-Y"), date("H:i:s"), $nome, $err ?: $response, $dias);
        file_put_contents($logFile, $logData, FILE_APPEND);

        echo "[" . date('H:i:s') . "] Enviado para: $nome | Status: " . ($err ? "Erro: $err" : "OK") . "\n";
        if ($tempomax > 0) sleep(rand($tempomin, $tempomax));
    }
}
$conn->close();
echo "[" . date('Y-m-d H:i:s') . "] Automação No Prazo finalizada.\n";
?>
