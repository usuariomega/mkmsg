<?php
/**
 * MK-MSG - Automação Vencidos (CLI Only)
 */

// Impede execução via navegador
if (php_sapi_name() !== 'cli') {
    die("Acesso negado: Este script deve ser executado apenas via CLI.\n");
}

include 'config.php';

// Garantir que $diasvencido é um array
if (!is_array($diasvencido)) {
    $diasvencido = [$diasvencido];
}

// Conectar ao banco de dados
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { 
    die("Erro de conexão: " . $conn->connect_error . "\n"); 
}

foreach ($diasvencido as $diasvencidoAtual) {
    $queryVencido = "SELECT upper(vtab_titulos.nome_res) as nome_res,
                     REGEXP_REPLACE(vtab_titulos.celular,'[( )-]+','') AS `celular`,
                     DATE_FORMAT(vtab_titulos.datavenc,'%d/%m/%y') AS `datavenc`,
                     vtab_titulos.linhadig, sis_qrpix.qrcode
                     FROM vtab_titulos
                     INNER JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
                     WHERE DATEDIFF(CURRENT_DATE(), vtab_titulos.datavenc) = $diasvencidoAtual
                     AND (vtab_titulos.status = 'vencido')
                     AND (vtab_titulos.cli_ativado = 's')
                     GROUP BY vtab_titulos.uuid_lanc
                     ORDER BY nome_res ASC;";
    
    $result = $conn->query($queryVencido);
    
    if (!$result || $result->num_rows === 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Sem registros para o dia $diasvencidoAtual.\n";
        continue;
    }

    echo "[" . date('Y-m-d H:i:s') . "] Processando Dia $diasvencidoAtual (" . $result->num_rows . " registros)...\n";

    $db = new SQLite3('db/msgdb.sqlite3');
    $msgvencido = $db->querySingle("SELECT msg FROM msgvencido");
    $db->close();

    while ($row = $result->fetch_assoc()) {
        $nome = $row['nome_res'];
        $celular = $row['celular'];
        $datavenc = $row['datavenc'];
        $linhadig = $row['linhadig'];
        $qrcode = $row['qrcode'];

        $buscar = array('/%provedor%/', '/%nomeresumido%/', '/%vencimento%/', '/%linhadig%/', '/%copiacola%/', '/%site%/');
        $substituir = array($provedor, $nome, $datavenc, $linhadig, $qrcode, $site);
        $msgFinal = preg_replace($buscar, $substituir, $msgvencido);

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
        $dir = "$rootPath/logs/$month/vencido";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            if (file_exists("$rootPath/logs/.ler/modelo/index.php")) {
                copy("$rootPath/logs/.ler/modelo/index.php", "$dir/index.php");
            }
        }

        $logFile = "$dir/vencido_" . date("d-M-Y") . ".log";
        $logData = sprintf("%s;%s;%s;%s;Dia:%d\n", date("d-m-Y"), date("H:i:s"), $nome, $err ?: $response, $diasvencidoAtual);
        file_put_contents($logFile, $logData, FILE_APPEND);

        echo "[" . date('H:i:s') . "] Enviado para: $nome | Status: " . ($err ? "Erro: $err" : "OK") . "\n";

        if ($tempomax > 0) { sleep(rand($tempomin, $tempomax)); }
    }
}

$conn->close();
echo "[" . date('Y-m-d H:i:s') . "] Automação Vencidos finalizada.\n";
?>
