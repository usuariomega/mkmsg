<!DOCTYPE html>
<html lang="pt-br">
<head>
    <title>MK-MSG - Visualizador de Logs</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* ========================================
           VARIÁVEIS CSS (Padronizadas)
           ======================================== */
        :root {
            --primary: #00b32b;
            --primary-hover: #009624;
            --secondary: #003fff;
            --secondary-hover: #0033cc;
            --tertiary: #395dca;
            --tertiary-hover: #2d4aa3;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --bg-body: #f8f9fa;
            --bg-white: #ffffff;
            --border: #dee2e6;
            --radius-md: 8px;
            --transition: all 0.2s ease;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 14px;
            color: var(--text-primary);
            background-color: var(--bg-body);
            line-height: 1.5;
            padding: 20px;
        }

        .container { width: 100%; max-width: 1200px; margin: 0 auto; }

        /* ========================================
           MENU E BOTÕES (IGUAIS AO MENU PRINCIPAL)
           ======================================== */
        .menu {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-white);
            padding: 20px;
            border-radius: var(--radius-md);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .button, .button2, .button3 {
            padding: 12px 24px;
            height: 48px;
            border: 2px solid transparent;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            text-decoration: none;
            color: white;
            font-family: inherit;
        }

        .button { background-color: var(--primary); }
        .button:hover { background-color: var(--primary-hover); transform: translateY(-2px); }
        
        .button2 { background-color: var(--secondary); }
        .button2:hover { background-color: var(--secondary-hover); transform: translateY(-2px); }
        
        .button3 { background-color: var(--tertiary); }
        .button3:hover { background-color: var(--tertiary-hover); transform: translateY(-2px); }

        /* Seletor de Log */
        .select1 {
            height: 48px;
            padding: 0 16px;
            border: 2px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 15px;
            background-color: var(--bg-white);
            color: var(--text-primary);
            transition: var(--transition);
            cursor: pointer;
            width: 230px;
            outline: none;
        }
        .select1:focus { border-color: var(--primary); }

        /* ========================================
           TABELA RESPONSIVA (SEM DATATABLES)
           ======================================== */
        .table-container {
            background: var(--bg-white);
            border-radius: var(--radius-md);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: #f1f3f5;
            padding: 15px;
            text-align: left;
            font-weight: 700;
            border-bottom: 2px solid var(--border);
            color: var(--text-primary);
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f3f5;
            font-size: 13px;
            word-break: break-word; /* Garante que o texto quebre no celular */
        }

        tr:hover { background-color: #f9f9f9; }

        .status-success { color: #28a745; font-weight: 700; }
        .status-error { color: #dc3545; font-weight: 700; }

        /* ========================================
           RESPONSIVIDADE CELULAR
           ======================================== */
        @media (max-width: 768px) {
            body { padding: 10px; }
            
            .menu { flex-direction: column; align-items: stretch; padding: 15px; }
            .button, .button2, .button3, .select1 { width: 100%; }
            
            /* Tabela em modo "Card" para celular */
            table, thead, tbody, th, td, tr { display: block; }
            
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            
            tr { border: 1px solid var(--border); margin-bottom: 10px; border-radius: var(--radius-md); background: white; }
            
            td {
                border: none;
                border-bottom: 1px solid #eee;
                position: relative;
                padding-left: 40%;
                text-align: right;
                min-height: 40px;
                display: flex;
                align-items: center;
                justify-content: flex-end;
            }
            
            td:last-child { border-bottom: none; }
            
            td:before {
                position: absolute;
                left: 15px;
                width: 35%;
                white-space: nowrap;
                text-align: left;
                font-weight: 700;
                color: var(--text-secondary);
                content: attr(data-label);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
            $current_dir = basename(dirname($_SERVER['PHP_SELF']));

            function getBtnClass($folder, $current) {
                return ($folder == $current) ? "button2" : "button3";
            }

            $arquivolog = isset($_GET['arquivolog']) ? $_GET['arquivolog'] : '';
        ?>

        <form method="get">
            <div class="menu">
                <div style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
                    <a href="../noprazo" class="<?= getBtnClass('noprazo', $current_dir) ?>">No Prazo</a>
                    <a href="../pago" class="<?= getBtnClass('pago', $current_dir) ?>">Pagos</a>
                    <a href="../vencido" class="<?= getBtnClass('vencido', $current_dir) ?>">Vencidos</a>
                    <a href="../bloqueado" class="<?= getBtnClass('bloqueado', $current_dir) ?>">Bloqueados</a>
                    <a href="../emmassa" class="<?= getBtnClass('emmassa', $current_dir) ?>">Em massa</a>
                    <a href="../../" class="button3">Voltar</a>
                </div>

                <select class="select1" name="arquivolog" onchange="this.form.submit()" required>
                    <option value="">Selecione o dia</option>
                    <?php
                    $logs = glob('*.log');
                    if ($logs) {
                        rsort($logs);
                        foreach($logs as $filename){
                            $filename = basename($filename);
                            $sel = ($arquivolog == $filename) ? "selected" : "";
                            echo "<option value='$filename' $sel>$filename</option>";
                        }
                    }
                    ?>
                </select>
            </div>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Hora</th>
                        <th>Nome</th>
                        <th>Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($arquivolog && file_exists($arquivolog)) {
                        $file = fopen($arquivolog, "r");
                        $lines = [];
                        while($line = fgets($file)) {
                            $line = trim($line);
                            if (empty($line)) continue;
                            $lines[] = $line;
                        }
                        fclose($file);

                        $lines = array_reverse($lines);

                        foreach($lines as $line) {
                            $line = str_replace(
                                ['{"status":"sent"}', '{"status":"error"}', '{"status":"token-error"}'],
                                ['Enviado com sucesso!', 'Erro no envio!', 'Erro de Token!'], 
                                $line
                            );

                            $parts = explode(";", $line);
                            list($data, $hora, $nome, $resultado) = array_pad($parts, 4, '');

                            $statusClass = (strpos($resultado, 'sucesso') !== false) ? 'status-success' : 'status-error';

                            echo "<tr>
                                    <td data-label='Data'>$data</td>
                                    <td data-label='Hora'>$hora</td>
                                    <td data-label='Nome'>$nome</td>
                                    <td data-label='Resultado' class='$statusClass'>$resultado</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' style='padding: 40px; text-align: center; color: var(--text-secondary);'>Selecione um arquivo de log para visualizar os registros.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

