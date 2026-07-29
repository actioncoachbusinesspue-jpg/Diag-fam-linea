<?php
/**
 * health-check.php — verificación de instalación.
 * Uso: php tools/health-check.php  (CLI)
 * o cópielo temporalmente a public/ tras el despliegue y bórrelo después.
 */
require_once dirname(__DIR__) . '/private/bootstrap.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    bvm_security_headers(null, true);
    header('Content-Type: text/plain; charset=utf-8');
}

$checks = [];

$checks['php_version'] = [
    'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'detail' => 'PHP ' . PHP_VERSION,
];

try {
    $pdo = Database::pdo();
    $checks['db_connection'] = ['ok' => true, 'detail' => 'Conexión establecida (' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . ')'];
    $tables = ['admin_users', 'families', 'participants', 'responses', 'external_responses', 'audit_events', 'login_attempts'];
    $missing = [];
    foreach ($tables as $t) {
        try {
            $pdo->query("SELECT 1 FROM $t LIMIT 1");
        } catch (Throwable $e) {
            $missing[] = $t;
        }
    }
    $checks['db_schema'] = [
        'ok' => !$missing,
        'detail' => $missing ? ('Faltan tablas: ' . implode(', ', $missing) . ' — importe database/schema.sql') : 'Todas las tablas existen',
    ];
    try {
        $admins = AdminUserRepository::count();
        $checks['admin_account'] = [
            'ok' => $admins > 0,
            'detail' => $admins > 0 ? "Administradores registrados: $admins (instalador bloqueado)" : 'Sin administradores — ejecute instalar.php',
        ];
    } catch (Throwable $e) {
        $checks['admin_account'] = ['ok' => false, 'detail' => 'No fue posible consultar admin_users'];
    }
} catch (Throwable $e) {
    $checks['db_connection'] = ['ok' => false, 'detail' => 'Sin conexión a la base de datos: revise private/config.php'];
}

$appKey = (string)bvm_config('app.key', '');
$appKeyOk = $appKey !== '' && !str_starts_with($appKey, 'REEMPLACE');
$checks['app_key'] = [
    'ok' => $appKeyOk,
    'detail' => $appKeyOk ? 'APP_KEY configurada' : 'APP_KEY sin configurar',
];

$token = (string)bvm_config('app.install_token', '');
$checks['install_token_removed'] = [
    'ok' => $token === '' || str_starts_with($token, 'REEMPLACE'),
    'detail' => ($token === '' || str_starts_with($token, 'REEMPLACE'))
        ? 'Token de instalación no activo'
        : 'ATENCIÓN: el token de instalación sigue en config.php — elimínelo tras instalar',
];

$refApp = BVM_PRIVATE_DIR . '/reference-app/referencia_app.html';
$checks['reference_app'] = [
    'ok' => is_file($refApp),
    'detail' => is_file($refApp) ? 'Motor de referencia disponible' : 'Falta private/reference-app/referencia_app.html',
];

$allOk = true;
foreach ($checks as $name => $c) {
    $allOk = $allOk && $c['ok'];
    printf("[%s] %-22s %s\n", $c['ok'] ? 'OK ' : 'FALLA', $name, $c['detail']);
}
echo $allOk ? "\nRESULTADO: TODO CORRECTO\n" : "\nRESULTADO: REVISAR FALLAS\n";
if ($isCli) {
    exit($allOk ? 0 : 1);
}
