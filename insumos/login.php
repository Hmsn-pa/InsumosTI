<?php
/**
 * login.php — redireciona para front/main.php do plugin no GLPI.
 *
 * SCRIPT_NAME já contém o path web correto resolvido pelo Apache/Nginx,
 * ex: /ti/plugins/insumos/login.php ou /motorista/plugins/insumos/login.php
 * dirname() uma vez = /ti/plugins/insumos/front  — não, precisamos da raiz
 *
 * Estratégia: quebrar no marcador fixo "/plugins/insumos" para pegar o prefixo
 * da instância e montar o path correto sem nenhuma suposição de DOCUMENT_ROOT.
 */

$scriptName = $_SERVER['SCRIPT_NAME'];
// Ex: /ti/plugins/insumos/login.php
// Pega tudo até e incluindo "/plugins/insumos"
$pos = strpos($scriptName, '/plugins/insumos');
if ($pos !== false) {
    $pluginWebPath = substr($scriptName, 0, $pos) . '/plugins/insumos';
} else {
    // Fallback: sobe dois níveis (login.php está na raiz do plugin)
    $pluginWebPath = dirname($scriptName);
}

header('Location: ' . $pluginWebPath . '/front/main.php');
exit;
