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
// Persistencia técnica SIEMPRE en UTC; las decisiones y la presentación local
// usan la zona configurada (app.timezone) mediante los helpers de más abajo.
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

// ---- Tiempo ----------------------------------------------------------------
// Regla única de la aplicación:
//   * Timestamps técnicos (created_at, updated_at, completed_at, last_login_at,
//     locked_until, audit_events) se guardan en UTC → bvm_now_utc().
//   * Las decisiones de apertura/cierre y la presentación administrativa usan
//     la zona configurada (predeterminado America/Mexico_City).
//   * report_date es una fecha editorial (DATE): nunca se convierte de zona.

/** Zona horaria configurada y validada. Si es inválida, la aplicación no inicia en silencio. */
function bvm_configured_timezone(): DateTimeZone
{
    static $tz = null;
    if ($tz instanceof DateTimeZone) {
        return $tz;
    }
    $name = (string)bvm_config('app.timezone', 'America/Mexico_City');
    try {
        $tz = new DateTimeZone($name);
    } catch (Throwable $e) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Configuración inválida: app.timezone «{$name}» no es una zona horaria reconocida.\n";
        echo "Use un identificador IANA, por ejemplo America/Mexico_City.\n";
        exit;
    }
    return $tz;
}

/** Timestamp técnico en UTC (formato SQL). */
function bvm_now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** Alias histórico: TODOS los timestamps técnicos persisten en UTC. */
function bvm_now(): string
{
    return bvm_now_utc();
}

/** Fecha de "hoy" en la zona configurada (para apertura/cierre y fechas editoriales). */
function bvm_local_today(): string
{
    return (new DateTimeImmutable('now', bvm_configured_timezone()))->format('Y-m-d');
}

/** Convierte un timestamp UTC de la base a la zona configurada. */
function bvm_utc_to_local(?string $utcDateTime): ?DateTimeImmutable
{
    if ($utcDateTime === null || $utcDateTime === '') {
        return null;
    }
    try {
        $dt = new DateTimeImmutable($utcDateTime, new DateTimeZone('UTC'));
    } catch (Throwable $e) {
        return null;
    }
    return $dt->setTimezone(bvm_configured_timezone());
}

/** Presentación local legible de un timestamp UTC de la base ('' si es NULL). */
function bvm_format_local(?string $utcDateTime, string $format = 'Y-m-d H:i'): string
{
    $local = bvm_utc_to_local($utcDateTime);
    return $local === null ? '' : $local->format($format);
}

/**
 * ¿"Hoy" local está dentro del rango [opensAt, closesAt]?
 * opens_at abre a las 00:00:00 locales y closes_at incluye TODO su día
 * (hasta 23:59:59 locales). Fechas NULL no restringen.
 */
function bvm_local_date_is_open(?string $opensAt, ?string $closesAt, ?string $todayLocal = null): bool
{
    $today = $todayLocal ?? bvm_local_today();
    if ($opensAt !== null && $opensAt !== '' && substr($opensAt, 0, 10) > $today) {
        return false;
    }
    if ($closesAt !== null && $closesAt !== '' && substr($closesAt, 0, 10) < $today) {
        return false;
    }
    return true;
}
