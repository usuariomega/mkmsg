#!/usr/bin/env php
<?php
/**
 * MK-MSG Daemon - Sistema de Automação de Envios
 * 
 * Este daemon roda continuamente em background, verificando
 * se chegou a hora de enviar mensagens automáticas.
 * 
 * Gerenciado pelo Supervisor para garantir alta disponibilidade.
 */

// Configurações do daemon
$root = "/var/www/html/mkmsg";
$configFile = "$root/config.php";
$lockDir = "$root/daemon_locks";

// Criar diretório de locks se não existir
if (!is_dir($lockDir)) {
    mkdir($lockDir, 0755, true);
}

// Função para log
function daemonLog($message, $type = "INFO") {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] [$type] $message\n";
}

// Função para verificar se já executou hoje
function jaExecutouHoje($tipo, $lockDir) {
    $hoje = date('Y-m-d');
    $lockFile = "$lockDir/{$tipo}_{$hoje}.lock";
    return file_exists($lockFile);
}

// Função para marcar como executado
function marcarComoExecutado($tipo, $lockDir) {
    $hoje = date('Y-m-d');
    $lockFile = "$lockDir/{$tipo}_{$hoje}.lock";
    file_put_contents($lockFile, date('Y-m-d H:i:s'));
}

// Função para limpar locks antigos (mais de 7 dias)
function limparLocksAntigos($lockDir) {
    $arquivos = glob("$lockDir/*.lock");
    $agora = time();
    
    foreach ($arquivos as $arquivo) {
        if ($agora - filemtime($arquivo) > 7 * 24 * 60 * 60) {
            unlink($arquivo);
        }
    }
}

// Função para executar o envio
function executarEnvio($tipo, $root, $lockDir) {
    daemonLog("Iniciando envio: $tipo");
    
    // Montar o comando curl para chamar o script de cron
    $script = "$root/cron{$tipo}.php";
    
    if (!file_exists($script)) {
        daemonLog("Script não encontrado: $script", "ERROR");
        return false;
    }
    
    // Executar o script via linha de comando
    $cmd = "php $script posttodos=1 2>&1";
    $output = shell_exec($cmd);
    
    daemonLog("Envio concluído: $tipo");
    daemonLog("Output: " . substr($output, 0, 200) . "...");
    
    // Marcar como executado
    marcarComoExecutado($tipo, $lockDir);
    
    return true;
}

// Função para verificar se está no horário de envio
function estaNoHorario($horarioConfig) {
    $horaAtual = date('H:i');
    $horaConfig = substr($horarioConfig, 0, 5); // Pega apenas HH:MM
    
    return $horaAtual === $horaConfig;
}

daemonLog("=== MK-MSG Daemon Iniciado ===");
daemonLog("Diretório raiz: $root");
daemonLog("Diretório de locks: $lockDir");

// Loop infinito
while (true) {
    try {
        // Carregar configurações
        if (!file_exists($configFile)) {
            daemonLog("Arquivo de configuração não encontrado: $configFile", "ERROR");
            sleep(60);
            continue;
        }
        
        include($configFile);
        
        // Verificar se as variáveis de horário existem
        if (!isset($horario_vencido) || !isset($horario_noprazo) || !isset($horario_pago)) {
            daemonLog("Variáveis de horário não configuradas. Aguardando...", "WARN");
            sleep(60);
            continue;
        }
        
        // Limpar locks antigos a cada hora
        if (date('i') === '00') {
            limparLocksAntigos($lockDir);
        }
        
        // Verificar e executar: VENCIDOS
        if (estaNoHorario($horario_vencido) && !jaExecutouHoje('vencido', $lockDir)) {
            executarEnvio('vencido', $root, $lockDir);
        }
        
        // Verificar e executar: NO PRAZO
        if (estaNoHorario($horario_noprazo) && !jaExecutouHoje('noprazo', $lockDir)) {
            executarEnvio('noprazo', $root, $lockDir);
        }
        
        // Verificar e executar: PAGOS
        if (estaNoHorario($horario_pago) && !jaExecutouHoje('pago', $lockDir)) {
            executarEnvio('pago', $root, $lockDir);
        }
        
        // Aguardar 60 segundos antes da próxima verificação
        sleep(60);
        
    } catch (Exception $e) {
        daemonLog("Erro no daemon: " . $e->getMessage(), "ERROR");
        sleep(60);
    }
}
?>
