<?php

//IP do MK-Auth
$servername = "127.0.0.1";

//Usuário do banco de dados do do MK-Auth
$username = "mkmsglerdb";

//Senha do banco de dados do do MK-Auth
$password = "mkmsgsenhadodb";

//Nome do banco de dados do do MK-Auth
$dbname = "mkradius";

//Nome do seu provedor
$provedor = "XYZ";

//Site do seu provedor (OBS: não coloque https://)
$site = "www.xyz.com.br";

//IP da API do WhatsApp
$wsip = "127.0.0.1";

//Token da API do WhatsApp
$token = "MEU_TOKEN";

//Ajusta fuso horário do PHP
date_default_timezone_set('America/Sao_Paulo');

//Envio automático: Múltiplos dias antes do prazo
//Para os títulos no prazo
//Formato: array de inteiros representando quantos dias antes avisar
//Exemplo: [1, 3, 7] = avisa 1, 3 e 7 dias antes do vencimento
$diasnoprazo = [1, 10];

//Envio automático: Múltiplos dias após vencer
//Para os títulos vencidos
//Formato: array de inteiros representando quantos dias depois avisar
//Exemplo: [1, 10, 15] = avisa 1, 10 e 15 dias depois de vencer
$diasvencido = [1, 10];

//Envio automático: Múltiplos dias após pago
//Para os títulos pagos
//Formato: array de inteiros representando quantos dias depois avisar
//Exemplo: [1, 3, 7] = avisa 1, 3 e 7 dias depois de pagar
$diaspago = [1];

//Horários de envio automático (formato HH:MM)
//O daemon verifica a cada minuto se chegou a hora de enviar
$horario_vencido = "10:00";
$horario_noprazo = "08:00";
$horario_pago = "12:00";

//Tempo de pausa de envio entre os clientes
//Tempo mínimo = 30 segundos, máximmo = 120 segundos
//Valores em segundos
$tempomin = 10;
$tempomax = 90;


//Não mexa abaixo!!
//Consulta SQL para buscar os clientes no prazo (interface manual)
$sqlnoprazo    = "SELECT upper(vtab_titulos.nome_res) as nome_res,
                 REGEXP_REPLACE(vtab_titulos.celular,'[( )-]+','') AS `celular`,
                 DATE_FORMAT(vtab_titulos.datavenc,'%d/%m/%y') AS `datavenc`,
                 vtab_titulos.linhadig, sis_qrpix.qrcode
                 FROM vtab_titulos
                 INNER JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
                 WHERE DATE_FORMAT(datavenc,'%y-%m') = DATE_FORMAT(NOW(),'%y-%m')
                 AND (vtab_titulos.status = 'aberto')
                 AND (vtab_titulos.cli_ativado = 's')
                 ORDER BY nome_res ASC, datavenc ASC;";

?>
