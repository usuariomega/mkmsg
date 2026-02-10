<?php
if (php_sapi_name() !== 'cli') {
    die("Acesso negado: Este script deve ser executado apenas via CLI.\n");
}

include 'config.php';

// Função para verificar se é feriado nacional
function isFeriado($data) {
    $ano = date('Y', strtotime($data));
    $feriados = [
        "$ano-01-01", // Confraternização Universal
        "$ano-04-21", // Tiradentes
        "$ano-05-01", // Dia do Trabalho
        "$ano-09-07", // Independência do Brasil
        "$ano-10-12", // Nossa Senhora Aparecida
        "$ano-11-02", // Finados
        "$ano-11-15", // Proclamação da República
        "$ano-11-20", // Dia da Consciência Negra
        "$ano-12-25", // Natal
    ];
    
    // Feriados móveis (Páscoa, Carnaval, Corpus Christi)
    $pascoa = date('Y-m-d', easter_date($ano));
    $carnaval = date('Y-m-d', strtotime("-47 days", strtotime($pascoa)));
    $sexta_santa = date('Y-m-d', strtotime("-2 days", strtotime($pascoa)));
    $corpus_christi = date('Y-m-d', strtotime("+60 days", strtotime($pascoa)));
    
    $feriados[] = $carnaval;
    $feriados[] = $sexta_santa;
    $feriados[] = $corpus_christi;
    
    return in_array($data, $feriados);
}

// Função para verificar se é dia útil
function isDiaUtil($data) {
    $fds = (date('N', strtotime($data)) >= 6); // 6 = Sábado, 7 = Domingo
    if ($fds) return false;
    if (isFeriado($data)) return false;
    return true;
}

$conn = new mysqli($servername, $username, $password, $dbname, $port);
if ($conn->connect_error) die("Erro de conexão: " . $conn->connect_error . "\n");

$hoje = date('Y-m-d');

// Se hoje não for dia útil e a opção estiver ativa, não envia nada hoje
if (isset($ignorar_fds_feriado) && $ignorar_fds_feriado == 1 && !isDiaUtil($hoje)) {
    echo "[" . date('Y-m-d H:i:s') . "] Hoje não é dia útil. Envios suspensos.\n";
    exit;
}

// SQL focado em clientes bloqueados
$sql = "SELECT upper(vtab_titulos.nome_res) as nome_res, 
        REGEXP_REPLACE(vtab_titulos.celular,'[( )-]+','') AS celular,
        DATE_FORMAT(vtab_titulos.datavenc,'%d/%m/%y') AS datavenc_fmt, 
        vtab_titulos.valor, vtab_titulos.linhadig, sis_qrpix.qrcode,
        vtab_titulos.bloqueado, vtab_titulos.dias_corte, vtab_titulos.datavenc
        FROM vtab_titulos
        LEFT JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
        WHERE vtab_titulos.bloqueado = 'sim' AND vtab_titulos.cli_ativado = 's'
        AND (vtab_titulos.deltitulo = 0 OR vtab_titulos.deltitulo IS NULL)
        AND vtab_titulos.nome_res IS NOT NULL AND TRIM(vtab_titulos.nome_res) <> ''
        AND vtab_titulos.celular IS NOT NULL AND TRIM(vtab_titulos.celular) <> ''
        GROUP BY vtab_titulos.uuid_lanc ORDER BY nome_res ASC";

$result = $conn->query($sql);
if (!$result) {
    die("Erro na consulta: " . $conn->error . "\n");
}

echo "[" . date('Y-m-d H:i:s') . "] Analisando clientes bloqueados para envio no dia exato...\n";

$jsonFile = __DIR__ . '/db/messages/bloqueado.json';
$msg = "";
if (file_exists($jsonFile)) {
    $jsonData = json_decode(file_get_contents($jsonFile), true);
    $msg = $jsonData['content'] ?? "";
}

while ($row = $result->fetch_assoc()) {
    $dataVenc = $row['datavenc'];
    $dias_corte = (int)$row['dias_corte'];
    
    // Data teórica de envio (Data de Vencimento + Dias de Corte)
    $dataEnvioTeorica = date('Y-m-d', strtotime("+$dias_corte days", strtotime($dataVenc)));
    
    // Data de envio real (ajustada para dias úteis - POSTERGAÇÃO)
    $dataEnvioReal = $dataEnvioTeorica;

    if (isset($ignorar_fds_feriado) && $ignorar_fds_feriado == 1) {
        // Se a data teórica não for útil, precisamos postergar para o PRÓXIMO dia útil
        while (!isDiaUtil($dataEnvioReal)) {
            $dataEnvioReal = date('Y-m-d', strtotime("+1 day", strtotime($dataEnvioReal)));
        }
    }

    // Só envia se a data de envio real for HOJE
    if ($dataEnvioReal !== $hoje) {
        continue;
    }

    $nome = $row['nome_res'];
    $buscar = ['/%provedor%/', '/%nomeresumido%/', '/%vencimento%/', '/%valor%/', '/%linhadig%/', '/%copiacola%/', '/%site%/', '/%dias_corte%/'];
    $substituir = [$provedor, $nome, $row['datavenc_fmt'], $row['valor'], $row['linhadig'], urlencode($row['qrcode'] ?? ''), $site, $dias_corte];
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

    // Logs
    $month = date("Y-m");
    $root = dirname(__FILE__);
    $dir = "$root/logs/$month/bloqueado";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        if (file_exists("$root/logs/.ler/modelo/index.php")) copy("$root/logs/.ler/modelo/index.php", "$dir/index.php");
    }

    $logFile = "$dir/bloqueado_" . date("d-M-Y") . ".log";
    $logData = sprintf("%s;%s;%s;%s;DiasCorte:%d\n", date("d-m-Y"), date("H:i:s"), $nome, $err ?: $response, $dias_corte);
    file_put_contents($logFile, $logData, FILE_APPEND);

    echo "[" . date('H:i:s') . "] Enviado para: $nome | Bloqueio efetivado hoje | Status: " . ($err ? "Erro: $err" : "OK") . "\n";
    
    if (isset($tempomax) && $tempomax > 0) sleep(rand($tempomin, $tempomax));
}

$conn->close();
echo "[" . date('Y-m-d H:i:s') . "] Automação Bloqueados finalizada.\n";
?>
