<?php
/**
 * bootstrap.php — punto de entrada común del backend.
 * Carga configuración, define helpers y prepara el entorno.
 */
declare(strict_types=1);

define('BVM_PRIVATE_DIR', __DIR__);
define('BVM_ROOT_DIR', dirname(__DIR__));

// ---- Configuración ---------------------------------------------------------
$bvmConfigFile = getenv('BVM_CONFIG_FILE') ?: (BVM_PRIVATE_DIR . '/config.php');
if (!is_file($bvmConfigFile)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "La aplicación no está configurada todavía (falta private/config.php).\n";
    echo "Consulte DEPLOY_HOSTINGER.md.\n";
    exit;
}
$GLOBALS['BVM_CONFIG'] = require $bvmConfigFile;

function bvm_config(string $path, $default = null)
{
    $parts = explode('.', $path);
    $node = $GLOBALS['BVM_CONFIG'];
    foreach ($parts as $p) {
        if (!is_array($node) || !array_key_exists($p, $node)) {
            return $default;
        }
        $node = $node[$p];
    }
    return $node;
}

// ---- Entorno / errores -----------------------------------------------------
date_default_timezone_set('UTC');
$bvmEnv = bvm_config('app.env', 'production');
if ($bvmEnv === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    // Producción: nunca mostrar errores PHP al usuario.
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

require_once BVM_PRIVATE_DIR . '/database.php';
require_once BVM_PRIVATE_DIR . '/security.php';
require_once BVM_PRIVATE_DIR . '/csrf.php';
require_once BVM_PRIVATE_DIR . '/auth.php';
require_once BVM_PRIVATE_DIR . '/validators.php';
require_once BVM_PRIVATE_DIR . '/cuestionario.php';
require_once BVM_PRIVATE_DIR . '/repositories/AdminUserRepository.php';
require_once BVM_PRIVATE_DIR . '/repositories/LoginAttemptRepository.php';
require_once BVM_PRIVATE_DIR . '/repositories/FamilyRepository.php';
require_once BVM_PRIVATE_DIR . '/repositories/ParticipantRepository.php';
require_once BVM_PRIVATE_DIR . '/repositories/ResponseRepository.php';
require_once BVM_PRIVATE_DIR . '/repositories/AuditRepository.php';

/** URL base pública de la aplicación (sin diagonal final). */
function bvm_base_url(): string
{
    $configured = trim((string)bvm_config('app.base_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    // Autodetección razonable para instalaciones sencillas.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    // Los scripts viven en /public, /public/admin o /public/api/...: subir hasta la raíz pública.
    foreach (['/api/auth', '/api/families', '/api/participants', '/api/responses', '/api/reports', '/admin'] as $suffix) {
        if ($suffix !== '' && str_ends_with($dir, $suffix)) {
            $dir = substr($dir, 0, -strlen($suffix));
            break;
        }
    }
    return $scheme . '://' . $host . $dir;
}

function bvm_now(): string
{
    return gmdate('Y-m-d H:i:s');
}
