<?php
require_once dirname(__DIR__) . '/private/bootstrap.php';
bvm_session_start();
bvm_security_headers(null, true);

if (bvm_admin_user() !== null) {
    header('Location: admin/index.php');
    exit;
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="<?= e(bvm_csrf_token()) ?>">
<meta name="bvm-base" content="<?= e(bvm_base_url()) ?>">
<title>Acceso BVM — Diagnóstico BVM</title>
<link rel="stylesheet" href="assets/css/bvm-app.css">
</head>
<body class="bvm-public">
<main class="auth-card" aria-labelledby="t">
  <p class="brand-kicker">Diagnóstico BVM para Familias Empresarias</p>
  <h1 id="t">Acceso BVM</h1>
  <p class="hint">Uso exclusivo del equipo autorizado de BVM. Los participantes no necesitan esta sección: pueden responder desde la liga que recibieron.</p>
  <div id="msg" role="alert"></div>
  <form id="login-form" autocomplete="off">
    <label for="username">Usuario</label>
    <input id="username" name="username" type="text" required autocomplete="username">
    <label for="password">Contraseña</label>
    <input id="password" name="password" type="password" required autocomplete="current-password">
    <div class="form-actions">
      <button class="btn btn-primary" type="submit" id="submit-btn">Iniciar sesión</button>
      <a class="btn btn-quiet" href="index.php">Volver</a>
    </div>
  </form>
</main>
<script src="assets/js/bvm-api.js"></script>
<script>
(function () {
  'use strict';
  var form = document.getElementById('login-form');
  var msg = document.getElementById('msg');
  var btn = document.getElementById('submit-btn');
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    msg.innerHTML = '';
    btn.disabled = true;
    BvmApi.post('/api/auth/login.php', {
      username: document.getElementById('username').value,
      password: document.getElementById('password').value
    }).then(function (data) {
      if (data.ok) {
        window.location.href = data.redirect;
        return;
      }
      btn.disabled = false;
      msg.innerHTML = '<div class="alert alert-error">' + BvmApi.escapeHtml(data.error || 'No fue posible iniciar sesión.') + '</div>';
    }).catch(function () {
      btn.disabled = false;
      msg.innerHTML = '<div class="alert alert-error">Sin conexión. Intente de nuevo.</div>';
    });
  });
})();
</script>
</body>
</html>
