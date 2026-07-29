<?php
declare(strict_types=1);

/**
 * security.php — encabezados, generación criptográfica de identificadores,
 * minimización de datos y helpers JSON.
 */

/** Encabezados de seguridad comunes. $csp permite ajustar por página. */
function bvm_security_headers(?string $csp = null, bool $noStore = false): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Frame-Options: DENY');
    if ($csp === null) {
        // Las páginas propias usan CSS/JS propios; los inline se permiten porque
        // la aplicación no incorpora contenido de terceros ni CDNs.
        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
             . "img-src 'self' data:; font-src 'self' data:; connect-src 'self'; "
             . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
    }
    header('Content-Security-Policy: ' . $csp);
    if ($noStore) {
        header('Cache-Control: no-store, max-age=0');
        header('Pragma: no-cache');
    }
}

/** CSP para las páginas que reutilizan el motor de referencia (inline + data:). */
function bvm_csp_reference_app(): string
{
    return "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
         . "img-src 'self' data:; font-src 'self' data:; connect-src 'self'; "
         . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
}

// ---- Generación criptográfica ---------------------------------------------

/** Slug público no predecible para la liga de una familia (minúsculas + dígitos). */
function bvm_random_slug(int $length = 12): string
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789'; // sin caracteres ambiguos
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

/**
 * Clave de familia legible: PREFIJO-XXXX (ej. ROBLES-8K4P).
 * El prefijo deriva del nombre solo para reconocimiento humano;
 * la parte aleatoria es criptográficamente segura.
 */
function bvm_family_access_code(string $familyName): string
{
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $familyName);
    if ($ascii === false) {
        $ascii = $familyName;
    }
    $prefix = strtoupper(preg_replace('/[^A-Za-z]/', '', $ascii) ?: 'BVM');
    $prefix = substr($prefix, 0, 8) ?: 'BVM';
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $rand = '';
    for ($i = 0; $i < 4; $i++) {
        $rand .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $prefix . '-' . $rand;
}

/** Código personal de continuidad: XXXX-XXXX aleatorio, nunca derivado del nombre. */
function bvm_personal_code(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $block = function () use ($alphabet): string {
        $s = '';
        for ($i = 0; $i < 4; $i++) {
            $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $s;
    };
    return $block() . '-' . $block();
}

/** Identificador público corto (participantes). */
function bvm_public_id(string $prefix = 'p'): string
{
    return $prefix . '_' . bin2hex(random_bytes(8));
}

// ---- Minimización de datos -------------------------------------------------

/** HMAC con APP_KEY: permite comparar sin almacenar el dato original. */
function bvm_hmac(string $value): string
{
    return hash_hmac('sha256', $value, (string)bvm_config('app.key', 'bvm'));
}

function bvm_client_ip_hash(): string
{
    return bvm_hmac((string)($_SERVER['REMOTE_ADDR'] ?? 'cli'));
}

// ---- Respuestas JSON -------------------------------------------------------

function bvm_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function bvm_json_response($data, int $status = 200): void
{
    bvm_security_headers(null, true);
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function bvm_json_error(string $message, int $status = 400, array $extra = []): void
{
    bvm_json_response(array_merge(['ok' => false, 'error' => $message], $extra), $status);
}

/** Exige método HTTP. */
function bvm_require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        bvm_json_error('Método no permitido.', 405);
    }
}

/** Escape HTML corto para plantillas PHP. */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
