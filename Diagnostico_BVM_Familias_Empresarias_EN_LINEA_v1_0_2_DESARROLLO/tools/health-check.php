<?php
/**
 * health-check.php — verificación de instalación (versión 1.0.2).
 *
 * SOLO por línea de comandos:
 *     php tools/health-check.php
 *
 * NUNCA copie este archivo al área pública. Si no dispone de terminal,
 * use la ruta administrativa protegida por sesión: admin/salud.php
 * (y desactívela después si lo desea, ver DEPLOY_HOSTINGER.md).
 *
 * Código de salida: 0 = todo correcto, 1 = advertencias, 2 = fallas.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Este verificador solo se ejecuta por terminal (php tools/health-check.php).\n";
    echo "Desde el navegador use la página protegida admin/salud.php.\n";
    exit;
}

require_once dirname(__DIR__) . '/private/bootstrap.php';
require_once BVM_PRIVATE_DIR . '/health_checks.php';

$checks = bvm_health_run();
$labels = ['ok' => 'OK   ', 'warn' => 'AVISO', 'fail' => 'FALLA'];
foreach ($checks as $name => $c) {
    printf("[%s] %-24s %s\n", $labels[$c['status']], $name, $c['detail']);
}

$overall = bvm_health_overall($checks);
echo "\nRESULTADO: " . ['ok' => 'TODO CORRECTO', 'warn' => 'CORRECTO CON ADVERTENCIAS', 'fail' => 'REVISAR FALLAS'][$overall] . "\n";
exit(['ok' => 0, 'warn' => 1, 'fail' => 2][$overall]);
