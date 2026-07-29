<?php
require_once dirname(__DIR__, 3) . '/private/bootstrap.php';
bvm_require_method('POST');
bvm_session_start();
bvm_csrf_require();

$in = bvm_json_input();
$username = trim((string)($in['username'] ?? ''));
$password = (string)($in['password'] ?? '');

if ($username === '' || $password === '') {
    bvm_json_error('Usuario o contraseña incorrectos.', 401);
}

// Rate limit por usuario+IP (mensaje genérico: no revela existencia de usuarios).
$attemptKey = 'admin-login|' . strtolower($username) . '|' . ($_SERVER['REMOTE_ADDR'] ?? '');
if (LoginAttemptRepository::isLocked($attemptKey)) {
    AuditRepository::log('login-locked-attempt');
    bvm_json_error('Demasiados intentos. Espere unos minutos e intente de nuevo.', 429);
}

$user = AdminUserRepository::findByUsername($username);
if (!$user || !password_verify($password, (string)$user['password_hash'])) {
    LoginAttemptRepository::registerFailure($attemptKey);
    AuditRepository::log('login-failed');
    bvm_json_error('Usuario o contraseña incorrectos.', 401);
}

// Versión 1.0.2 — Opción A de roles: el rol consultor no está habilitado.
// Un usuario con ese rol (creado manualmente en la base) NO debe entrar:
// sin filtro por family_assignments vería todas las familias y daría una
// falsa sensación de aislamiento.
if (($user['role'] ?? 'administrador') !== 'administrador') {
    AuditRepository::log('login-role-disabled', (int)$user['id']);
    bvm_json_error('Este perfil no está habilitado en la versión actual. Contacte al administrador BVM.', 403);
}

LoginAttemptRepository::clear($attemptKey);
bvm_admin_login((int)$user['id']);
AdminUserRepository::touchLogin((int)$user['id']);
AuditRepository::log('login-success', (int)$user['id']);

bvm_json_response([
    'ok' => true,
    'redirect' => bvm_base_url() . '/admin/index.php',
    'csrf_token' => bvm_csrf_token(),
]);
