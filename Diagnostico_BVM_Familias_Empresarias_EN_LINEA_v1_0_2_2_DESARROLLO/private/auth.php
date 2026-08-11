<?php
declare(strict_types=1);

/**
 * auth.php — sesiones PHP seguras, autenticación BVM y sesión de participante.
 */

/**
 * Ruta de la cookie de sesión (app.session_cookie_path).
 * Aísla dev y producción bajo el mismo dominio: cada instalación limita su
 * cookie a su propia carpeta. Normalización: siempre inicia y termina en '/';
 * un valor vacío o inválido cae a '/'.
 */
function bvm_session_cookie_path(): string
{
    $path = trim((string)bvm_config('app.session_cookie_path', '/'));
    if ($path === '' || $path[0] !== '/') {
        return '/';
    }
    if (!str_ends_with($path, '/')) {
        $path .= '/';
    }
    return $path;
}

/** Atributos de la cookie de sesión — un solo lugar para crear Y eliminar. */
function bvm_session_cookie_params(): array
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return [
        'lifetime' => 0,
        'path'     => bvm_session_cookie_path(),
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        // Lax: los participantes llegan desde ligas compartidas (clic externo);
        // Strict rompería la primera navegación con sesión. Toda mutación va
        // protegida además por token CSRF.
        'samesite' => 'Lax',
    ];
}

function bvm_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name((string)bvm_config('app.session_name', 'BVMSESSID'));
    session_set_cookie_params(bvm_session_cookie_params());
    session_start();

    // Expiración por inactividad.
    $lifetime = 60 * (int)bvm_config('app.session_lifetime_minutes', 45);
    $now = time();
    if (isset($_SESSION['bvm_last_activity']) && ($now - (int)$_SESSION['bvm_last_activity']) > $lifetime) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['bvm_last_activity'] = $now;
}

// ---- Administración BVM ----------------------------------------------------

function bvm_admin_login(int $userId): void
{
    bvm_session_start();
    session_regenerate_id(true);
    $_SESSION['bvm_admin_id'] = $userId;
    unset($_SESSION['bvm_csrf']); // token nuevo por sesión autenticada
}

function bvm_admin_id(): ?int
{
    bvm_session_start();
    return isset($_SESSION['bvm_admin_id']) ? (int)$_SESSION['bvm_admin_id'] : null;
}

function bvm_admin_user(): ?array
{
    $id = bvm_admin_id();
    if ($id === null) {
        return null;
    }
    $user = AdminUserRepository::findById($id);
    // Opción A de roles (1.0.2): solo el rol administrador está habilitado.
    // Defensa en profundidad además del bloqueo en login.php.
    if (!$user || !(int)$user['active'] || ($user['role'] ?? 'administrador') !== 'administrador') {
        bvm_logout();
        return null;
    }
    return $user;
}

/** Para páginas HTML de administración: redirige al login si no hay sesión. */
function bvm_require_admin_page(): array
{
    $user = bvm_admin_user();
    if ($user === null) {
        header('Location: ' . bvm_base_url() . '/acceso-bvm.php');
        exit;
    }
    return $user;
}

/** Para APIs de administración: 401 JSON + CSRF obligatorio en mutaciones. */
function bvm_require_admin_api(bool $checkCsrf = true): array
{
    $user = bvm_admin_user();
    if ($user === null) {
        bvm_json_error('Sesión no válida o expirada.', 401);
    }
    if ($checkCsrf) {
        bvm_csrf_require();
    }
    return $user;
}

function bvm_logout(): void
{
    bvm_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        // Eliminar con EXACTAMENTE los mismos atributos con que se creó
        // (nombre, path, secure, httponly, samesite); de lo contrario el
        // navegador conservaría la cookie original.
        $p = bvm_session_cookie_params();
        $p['lifetime'] = 0;
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
    }
    session_destroy();
}

// ---- Sesión de participante ------------------------------------------------

/**
 * Inicia la sesión del participante. Desde 1.0.2.2 queda vinculada SIEMPRE a
 * las tres claves: participante, familia y liga pública (slug). Sin el slug no
 * es posible distinguir la participación de la Familia A de la de la Familia B
 * dentro del mismo navegador.
 */
function bvm_participant_login(int $participantId, int $familyId, string $familySlug): void
{
    bvm_session_start();
    session_regenerate_id(true);
    $_SESSION['bvm_participant_id'] = $participantId;
    $_SESSION['bvm_participant_family_id'] = $familyId;
    $_SESSION['bvm_participant_family_slug'] = $familySlug;
}

function bvm_participant_session(): ?array
{
    bvm_session_start();
    if (!isset($_SESSION['bvm_participant_id'], $_SESSION['bvm_participant_family_id'])) {
        return null;
    }
    return [
        'participant_id' => (int)$_SESSION['bvm_participant_id'],
        'family_id'      => (int)$_SESSION['bvm_participant_family_id'],
        'family_slug'    => (string)($_SESSION['bvm_participant_family_slug'] ?? ''),
    ];
}

/**
 * Contexto de participante vigente para la liga $slug.
 *
 * Al abrir participar.php?f=FAMILIA_B con una sesión de participante de la
 * Familia A, la sesión ANTERIOR se limpia automáticamente: nunca se restauran
 * datos de A dentro del flujo de B. La sesión administrativa de BVM (si la
 * hubiera en el mismo navegador) NO se toca.
 *
 * Devuelve true si tras la operación queda una sesión de participante válida
 * para esta liga.
 */
function bvm_participant_bind_family(string $slug, int $familyId): bool
{
    bvm_session_start();
    $sess = bvm_participant_session();
    $unlocked = (int)($_SESSION['bvm_family_unlocked'] ?? 0);
    $unlockedSlug = (string)($_SESSION['bvm_family_unlocked_slug'] ?? '');

    $participantMatches = $sess !== null
        && $sess['family_id'] === $familyId
        && $sess['family_slug'] === $slug;
    $unlockMatches = $unlocked === 0 || ($unlocked === $familyId && $unlockedSlug === $slug);

    if ($sess !== null && !$participantMatches) {
        bvm_participant_logout();
        return false;
    }
    if (!$unlockMatches) {
        // Clave de OTRA familia validada en esta sesión: se descarta.
        unset($_SESSION['bvm_family_unlocked'], $_SESSION['bvm_family_unlocked_slug']);
    }
    return $participantMatches;
}

function bvm_require_participant_api(bool $checkCsrf = true): array
{
    $sess = bvm_participant_session();
    if ($sess === null) {
        bvm_json_error('Sesión de participante no válida o expirada.', 401);
    }
    if ($checkCsrf) {
        bvm_csrf_require();
    }
    return $sess;
}

/**
 * Cierre REAL de la participación: destruye todas las variables de sesión del
 * participante (identidad, familia, liga y la clave de familia validada) y
 * renueva el identificador de sesión. Nunca cierra una sesión administrativa
 * BVM independiente que exista en el mismo navegador.
 */
function bvm_participant_logout(): void
{
    bvm_session_start();
    unset(
        $_SESSION['bvm_participant_id'],
        $_SESSION['bvm_participant_family_id'],
        $_SESSION['bvm_participant_family_slug'],
        $_SESSION['bvm_family_unlocked'],
        $_SESSION['bvm_family_unlocked_slug']
    );
    // Cambio de contexto de identidad → identificador de sesión nuevo.
    // session_regenerate_id(false) conserva $_SESSION (la sesión admin sigue viva).
    session_regenerate_id(false);
}
