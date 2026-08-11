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
 * Longitud de la parte aleatoria de la clave de familia.
 * Configurable en security.family_access_random_length; se valida al rango
 * [6, 10] y cualquier valor inválido cae de forma segura a 6.
 */
function bvm_family_access_random_length(): int
{
    $configured = bvm_config('security.family_access_random_length', 6);
    if (!is_numeric($configured)) {
        return 6;
    }
    $n = (int)$configured;
    return ($n >= 6 && $n <= 10) ? $n : 6;
}

/**
 * Clave de familia legible: PREFIJO-XXXXXX (ej. ROBLES-8K4P7M).
 * El prefijo deriva del nombre solo para reconocimiento humano;
 * la parte aleatoria (6 caracteres desde 1.0.2, sin ambiguos) es
 * criptográficamente segura (random_int). Las claves de 4 caracteres
 * emitidas por versiones anteriores siguen funcionando: la verificación
 * es contra el hash almacenado, sin requisito de formato.
 */
function bvm_family_access_code(string $familyName): string
{
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $familyName);
    if ($ascii === false) {
        $ascii = $familyName;
    }
    // Omite palabras genéricas para que "Familia Robles" produzca ROBLES-XXXXXX.
    $stop = ['familia', 'family', 'empresa', 'grupo', 'casa', 'de', 'del', 'la', 'las', 'los', 'y'];
    $words = preg_split('/[^A-Za-z]+/', $ascii, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $candidates = array_values(array_filter($words, fn($w) => !in_array(strtolower($w), $stop, true)));
    $base = $candidates[0] ?? ($words[0] ?? 'BVM');
    $prefix = substr(strtoupper($base), 0, 8) ?: 'BVM';
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $length = bvm_family_access_random_length();
    $rand = '';
    for ($i = 0; $i < $length; $i++) {
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

/**
 * HMAC de localización del código personal (participants.resume_token_lookup_hash).
 * Permite encontrar UN candidato por índice; la validación final sigue siendo
 * password_verify contra resume_token_hash. Normalización: trim + mayúsculas,
 * conservando el guion tal como se muestra al participante (XXXX-XXXX).
 */
function bvm_resume_token_lookup(string $code): string
{
    return hash_hmac('sha256', strtoupper(trim($code)), (string)bvm_config('app.key', 'bvm'));
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
