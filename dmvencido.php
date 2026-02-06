<?php
if (php_sapi_name() !== 'cli') {
    die("Acesso negado: Este script deve ser executado apenas via CLI.\n");
}

include 'config.php';

if (!is_array($diasvencido)) $diasvencido = [$diasvencido];

$conn = new mysqli($servername, $username, $password, $dbname, $port);
if ($conn->connect_error) die("Erro de conexão: " . $conn->connect_error . "\n");

foreach ($diasvencido as $dias) {
    $sql = "SELECT upper(vtab_titulos.nome_res) as nome_res, 
            REGEXP_REPLACE(vtab_titulos.celular,'[( )-]+','') AS celular,
            DATE_FORMAT(vtab_titulos.datavenc,'%d/%m/%y') AS datavenc, 
            vtab_titulos.valor, vtab_titulos.linhadig, sis_qrpix.qrcode 
            FROM vtab_titulos
            LEFT JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
            WHERE DATEDIFF(CURRENT_DATE(), vtab_titulos.datavenc) = $dias
            AND vtab_titulos.status = 'vencido' AND vtab_titulos.cli_ativado = 's'
            AND (vtab_titulos.deltitulo = 0 OR vtab_titulos.deltitulo IS NULL)
            AND vtab_titulos.nome_res IS NOT NULL AND TRIM(vtab_titulos.nome_res) <> ''
            AND vtab_titulos.celular IS NOT NULL AND TRIM(vtab_titulos.celular) <> ''
            AND vtab_titulos.linhadig IS NOT NULL AND TRIM(vtab_titulos.linhadig) <> ''
            GROUP BY vtab_titulos.uuid_lanc ORDER BY nome_res ASC";
    
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Sem registros para o dia $dias.\n";
        continue;
    }

    echo "[" . date('Y-m-d H:i:s') . "] Processando Dia $dias (" . $result->num_rows . " registros)...\n";

    $jsonFile = __DIR__ . '/db/messages/vencido.json';
    $msg = "";
    if (file_exists($jsonFile)) {
        $jsonData = json_decode(file_get_contents($jsonFile), true);
        $msg = $jsonData['content'] ?? "";
    }

    while ($row = $result->fetch_assoc()) {
        $nome = $row['nome_res'];
        $buscar = ['/%provedor%/', '/%nomeresumido%/', '/%vencimento%/', '/%valor%/', '/%linhadig%/', '/%copiacola%/', '/%site%/'];
        $substituir = [$provedor, $nome, $row['datavenc'], $row['valor'], $row['linhadig'], urlencode($row['qrcode']), $site];
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
        $dir = "$root/logs/$month/vencido";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            if (file_exists("$root/logs/.ler/modelo/index.php")) copy("$root/logs/.ler/modelo/index.php", "$dir/index.php");
        }

        $logFile = "$dir/vencido_" . date("d-M-Y") . ".log";
        $logData = sprintf("%s;%s;%s;%s;Dia:%d\n", date("d-m-Y"), date("H:i:s"), $nome, $err ?: $response, $dias);
        file_put_contents($logFile, $logData, FILE_APPEND);

        echo "[" . date('H:i:s') . "] Enviado para: $nome | Status: " . ($err ? "Erro: $err" : "OK") . "\n";
        if ($tempomax > 0) sleep(rand($tempomin, $tempomax));
    }
}
$conn->close();
echo "[" . date('Y-m-d H:i:s') . "] Automação Vencidos finalizada.\n";
?>
