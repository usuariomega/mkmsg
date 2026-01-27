<?php

//IP do MK-Auth
$servername    = "127.0.0.1";

//Usuário do banco de dados do do MK-Auth
$username 	   = "nomedousuario";

//Senha do banco de dados do do MK-Auth
$password 	   = "suasenha";

//Nome do banco de dados do do MK-Auth
$dbname		   = "mkradius";

//Nome do seu provedor
$provedor	   = "XYZ";

//Site do seu provedor (OBS: não coloque https://)
$site		   = "www.xyz.com.br";

//IP do MkAuth WhatsApp Send Message
$wsip		   = "127.0.0.1";

//Token do MkAuth WhatsApp Send Message
$token		   = "MEU_TOKEN";

//Ajusta fuso horário do PHP
date_default_timezone_set('America/Sao_Paulo');

//Envio automático: Quantos dias antes do prazo
//Para os títulos no prazo
//Lembre de configurar o cron conforme o tutorial
$diasnoprazo   = 1;

//Envio automático: Quantos dias após vencer
//Para os títulos vencidos
//Lembre de configurar o cron conforme o tutorial
$diasvencido   = 1;

//Envio automático: Quantos dias após pago
//Para os títulos pagos
//Lembre de configurar o cron conforme o tutorial
$diaspago      = 1;

//Tempo de pausa de envio entre os clientes
//Tempo mínimo = 30 segundos, máximmo = 120 segundos
//Valores em segundos
$tempomin      = 10;
$tempomax      = 120;


//Não mexa abaixo!!
//Consultas SQL para buscar os clientes no prazo, vencidos e pagos
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

$cronnoprazo   = "SELECT upper(vtab_titulos.nome_res) as nome_res,
                 REGEXP_REPLACE(vtab_titulos.celular,'[( )-]+','') AS `celular`,
                 DATE_FORMAT(vtab_titulos.datavenc,'%d/%m/%y') AS `datavenc`,
                 vtab_titulos.linhadig, sis_qrpix.qrcode
                 FROM vtab_titulos
                 INNER JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
                 WHERE DATE_FORMAT(datavenc,'%d/%m/%y') = DATE_FORMAT(DATE_ADD(CURRENT_DATE(), INTERVAL +$diasnoprazo DAY),'%d/%m/%y')
                 AND (vtab_titulos.status = 'aberto')
                 AND (vtab_titulos.cli_ativado = 's')
                 ORDER BY nome_res ASC, datavenc ASC;";

$cronvencido   = "SELECT upper(vtab_titulos.nome_res) as nome_res,
                 REGEXP_REPLACE(vtab_titulos.celular,'[( )-]+','') AS `celular`,
                 DATE_FORMAT(vtab_titulos.datavenc,'%d/%m/%y') AS `datavenc`,
                 vtab_titulos.linhadig, sis_qrpix.qrcode
                 FROM vtab_titulos
                 INNER JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
                 WHERE DATE_FORMAT(datavenc,'%d/%m/%y') = DATE_FORMAT(DATE_ADD(CURRENT_DATE(), INTERVAL -$diasvencido DAY),'%d/%m/%y')
                 AND (vtab_titulos.status = 'vencido')
                 AND (vtab_titulos.cli_ativado = 's')
                 ORDER BY nome_res ASC, datavenc ASC;";

$cronpago      = "SELECT upper(vtab_titulos.nome_res) as nome_res,
                 REGEXP_REPLACE(vtab_titulos.celular,'[( )-]+','') AS `celular`,
                 DATE_FORMAT(vtab_titulos.datapag,'%d/%m/%y') AS `datapag`,
                 vtab_titulos.linhadig, sis_qrpix.qrcode
                 FROM vtab_titulos
                 INNER JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
                 WHERE DATE_FORMAT(datapag,'%d/%m/%y') = DATE_FORMAT(DATE_ADD(CURRENT_DATE(), INTERVAL -$diaspago DAY),'%d/%m/%y')
                 AND (vtab_titulos.status = 'pago')
                 AND (vtab_titulos.cli_ativado = 's')
                 ORDER BY nome_res ASC, datapag ASC;";

?>
