<!DOCTYPE html>
<html lang="pt-br">
<head>
    <title>MK-MSG - Visualizador de Logs</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.datatables.net/v/dt/jq-3.7.0/dt-2.3.6/r-3.0.7/datatables.min.css" rel="stylesheet" integrity="sha384-UfSavg27SLgQJ24zeLvDxB3G1thAA8eUAs6c/q0I2cpoKUcE/l2UMPLCTNBJz00s" crossorigin="anonymous">
    <script src="https://cdn.datatables.net/v/dt/jq-3.7.0/dt-2.3.6/r-3.0.7/datatables.min.js" integrity="sha384-ypQzQNCsxzmYsxOYlbt0ag1ahjGS2Wq8XK1ZYD5xCUV81S+ztC7JHRiTkAS0+WdY" crossorigin="anonymous"></script>
    <script>
        $(document).ready(function () {
            $('#table_id').DataTable({
                order: [[0, 'desc'], [1, 'desc']],
                responsive: true,
                pagingType: 'numbers',
                language: {
                    search: "Buscar:",
                    lengthMenu: "_MENU_",
                    zeroRecords: "Sem registros",
                    emptyTable: "Selecione o dia e clique em abrir.",
                    info: "Página _PAGE_ de _PAGES_",
                    infoEmpty: "Sem registros disponíveis",
                    infoFiltered: "(Filtrados de _MAX_ registros)"
                }
            });
        });
    </script>
    <style>
        body {
            font-family: Consolas, "Trebuchet MS", Arial, Helvetica, sans-serif;
            font-size: 13px;
        }

        .menu {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: space-between;
        }

        .select1 {
            background-color: #ffffff;
            width: 160px;
            height: 50px;
            border: solid 2px #00b32b;
            border-radius: 5px;
            padding: 5px 5px;
            margin-top: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 16px;
            font-family: consolas, sans-serif;
            cursor: pointer;
        }

        .button,
        .submit {
            background-color: #00b32b;
            border: none;
            border-radius: 5px;
            color: white;
            width: 160px;
            height: 50px;
            padding: 5px 5px;
            margin-top: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 16px;
            font-family: consolas, sans-serif;
            cursor: pointer;
        }

        .button2,
        .submit {
            background-color: #003fff;
            border: none;
            border-radius: 5px;
            color: white;
            width: 160px;
            height: 50px;
            padding: 5px 5px;
            margin-top: 10px;
            text-align: center;
            font-size: 16px;
            font-family: consolas, sans-serif;
            cursor: pointer;
        }

        .button3,
        .submit {
            background-color: #395dca;
            border: none;
            border-radius: 5px;
            color: white;
            width: 160px;
            height: 50px;
            padding: 5px 5px;
            margin-top: 10px;
            text-align: center;
            font-size: 16px;
            font-family: consolas, sans-serif;
            cursor: pointer;
        }

        div.dt-container div.dt-layout-cell {
            display: inline;
        }

        div.dt-container .dt-input {
            font-size: 18px;
        }

        .dt-container .dt-length {
            float: left;
            padding-bottom: 10px;
        }

        .dt-container .dt-search {
            float: none;
            text-align: right;
            padding-bottom: 10px;
        }

        table.dataTable thead th {
            text-align: center;
        }

        td {
            text-align: center;
        }

        table.dataTable>tbody>tr>th,
        table.dataTable>tbody>tr>td {
            padding-right: 35px;
        }

        table.dataTable.display tbody tr:hover {
            box-shadow: inset 0 0 0 9999px rgb(13 110 253 / 26%);
        }

        div.dt-container .dt-info,
        div.dt-container .dt-paging {
            text-align: center;
            padding-top: 10px;
        }

        div.dt-container .dt-paging .dt-paging-button {
            padding: 1.5em 2.0em;
        }

        @media screen and (max-width: 767px) {
            div.dt-container div.dt-layout-row:not(.dt-layout-table) {
            display: inline-block;
            }
        }
    </style>
</head>
<body>
    <?php

        $current_dir = basename(dirname($_SERVER['PHP_SELF']));


        function getBtnClass($folder, $current) {
            return ($folder == $current) ? "button2" : "button3";
        }
    ?>
    <form method="get">
        <div class="menu">
            <button class="<?= getBtnClass('noprazo', $current_dir) ?>" onclick="location.href='../noprazo'" type="button">No Prazo</button>
            <button class="<?= getBtnClass('pago', $current_dir) ?>" onclick="location.href='../pago'" type="button">Pagos</button>
            <button class="<?= getBtnClass('vencido', $current_dir) ?>" onclick="location.href='../vencido'" type="button">Vencidos</button>
            <button class="button3" onclick="location.href='../../'" type="button">Voltar</button>

            <select class="select1" name="arquivolog" onchange="this.form.submit()" required>
                <option value="">Selecione o dia</option>
                <?php
                $logs = glob('*.log');
                if ($logs) {
                    rsort($logs);
                    foreach($logs as $filename){
                        $filename = basename($filename);
                        $sel = (isset($_GET['arquivolog']) && $_GET['arquivolog'] == $filename) ? "selected" : "";
                        echo "<option value='$filename' $sel>$filename</option>";
                    }
                }
                ?>
            </select>
        </div>
    </form>

    <table id="table_id" class="display responsive" width="100%">
        <thead>
            <tr>
                <th>Data:</th>
                <th>Hora:</th>
                <th>Nome:</th>
                <th>Resultado:</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (isset($_GET["arquivolog"]) && file_exists($_GET["arquivolog"])) {
                $file = fopen($_GET["arquivolog"], "r");
                while($line = fgets($file)) {
                    $line = trim($line);
                    if (empty($line)) continue;


                    $line = str_replace(['{"status":"sent"}', '{"status":"error"}', '{"status":"token-error"}'],
                                      ['Enviado com sucesso!', 'Erro no envio!', 'Erro de Token!'], $line);


                    $parts = explode(";", $line);

                    list($data, $hora, $nome, $resultado) = array_pad($parts, 4, '');
                    echo "<tr><td>$data</td><td>$hora</td><td>$nome</td><td>$resultado</td></tr>";
                }
                fclose($file);
            }
            ?>
        </tbody>
    </table>
</body>
</html>

