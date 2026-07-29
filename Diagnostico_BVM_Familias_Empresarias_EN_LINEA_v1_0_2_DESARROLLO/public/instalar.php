<?php
/**
 * instalar.php — asistente de instalación inicial seguro.
 * - Solo funciona cuando NO existe ningún usuario administrador.
 * - Exige el token de instalación definido en private/config.php.
 * - Se bloquea automáticamente al crear el primer administrador.
 * - No deja credenciales predeterminadas.
 */
require_once dirname(__DIR__) . '/private/bootstrap.php';
bvm_session_start();
bvm_security_headers(null, true);

$alreadyInstalled = false;
$dbError = null;
try {
    $alreadyInstalled = AdminUserRepository::count() > 0;
} catch (Throwable $e) {
    $dbError = 'No fue posible conectar con la base de datos. Verifique private/config.php e importe database/schema.sql.';
}

$errors = [];
$done = false;

if (!$dbError && !$alreadyInstalled && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bvm_csrf_verify()) {
        $errors[] = 'Solicitud rechazada por seguridad. Recargue la página e intente de nuevo.';
    } else {
        $token = (string)($_POST['install_token'] ?? '');
        $name = trim((string)($_POST['name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');
        $configuredToken = (string)bvm_config('app.install_token', '');

        if ($configuredToken === '' || str_starts_with($configuredToken, 'REEMPLACE')) {
            $errors[] = 'El token de instalación no está configurado en private/config.php.';
        } elseif (!hash_equals($configuredToken, $token)) {
            $errors[] = 'Token de instalación incorrecto.';
        }
        if ($name === '' || mb_strlen($name) < 2) {
            $errors[] = 'Indique el nombre de la persona administradora.';
        }
        if (!preg_match('/^[a-zA-Z0-9._@-]{4,120}$/', $username)) {
            $errors[] = 'El usuario debe tener al menos 4 caracteres (letras, números, punto, guion, arroba).';
        }
        if (strlen($password) < 12) {
            $errors[] = 'La contraseña debe tener al menos 12 caracteres.';
        }
        if ($password !== $password2) {
            $errors[] = 'Las contraseñas no coinciden.';
        }
        if (in_array(strtolower($username), ['admin', 'administrador', 'root'], true) && strlen($password) < 16) {
            $errors[] = 'Con un usuario tan común, use una contraseña de al menos 16 caracteres (o mejor, otro usuario).';
        }
        if (!$errors) {
            $id = AdminUserRepository::create($name, $username, $password, 'administrador');
            AuditRepository::log('installer-admin-created', $id);
            $done = true;
        }
    }
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Instalación — Diagnóstico BVM</title>
<link rel="stylesheet" href="assets/css/bvm-app.css">
</head>
<body class="bvm-public">
<main class="auth-card" aria-labelledby="t">
  <p class="brand-kicker">Diagnóstico BVM para Familias Empresarias</p>
  <h1 id="t">Instalación inicial</h1>
<?php if ($dbError): ?>
  <div class="alert alert-error" role="alert"><?= e($dbError) ?></div>
<?php elseif ($alreadyInstalled && !$done): ?>
  <div class="alert alert-info" role="status">
    La instalación ya fue completada y este asistente quedó bloqueado.
    Si necesita otro usuario, solicítelo al administrador actual.
  </div>
  <p><a class="btn btn-secondary" href="index.php">Ir a la página principal</a></p>
<?php elseif ($done): ?>
  <div class="alert alert-success" role="status">
    Cuenta administradora creada correctamente. Este asistente quedó bloqueado.
  </div>
  <p><strong>Importante:</strong> elimine ahora el token de instalación de <code>private/config.php</code>.</p>
  <p><a class="btn btn-primary" href="acceso-bvm.php">Ir al acceso BVM</a></p>
<?php else: ?>
  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error" role="alert"><?= e($err) ?></div>
  <?php endforeach; ?>
  <form method="post" autocomplete="off">
    <?= bvm_csrf_field() ?>
    <label for="install_token">Token de instalación</label>
    <input id="install_token" name="install_token" type="password" required autocomplete="off">
    <label for="name">Nombre completo</label>
    <input id="name" name="name" type="text" required maxlength="120">
    <label for="username">Usuario</label>
    <input id="username" name="username" type="text" required maxlength="120" autocomplete="off">
    <label for="password">Contraseña (mínimo 12 caracteres)</label>
    <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password">
    <label for="password2">Confirmar contraseña</label>
    <input id="password2" name="password2" type="password" required minlength="12" autocomplete="new-password">
    <button class="btn btn-primary" type="submit">Crear cuenta administradora</button>
  </form>
<?php endif; ?>
</main>
</body>
</html>
