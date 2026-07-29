<?php
require_once __DIR__ . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
bvm_session_start();
bvm_security_headers(null, true);

$slug = (string)($_GET['f'] ?? '');
$slugValid = bvm_valid_slug($slug);
$family = $slugValid ? FamilyRepository::findBySlug($slug) : null;
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="<?= e(bvm_csrf_token()) ?>">
<meta name="bvm-base" content="<?= e(bvm_base_url()) ?>">
<title>Responder el diagnóstico — Diagnóstico BVM</title>
<link rel="stylesheet" href="assets/css/bvm-app.css">
</head>
<body class="bvm-public">
<div class="participant-shell">
  <p class="brand-kicker">Diagnóstico BVM para Familias Empresarias</p>

<?php if (!$family): ?>
  <div class="question-card">
    <h1>Liga de invitación no válida</h1>
    <p>Esta liga no corresponde a ninguna familia participante. Verifique con la persona
       que le compartió la invitación que la dirección esté completa.</p>
    <p>Si desea conocer la herramienta, puede explorar la
       <a href="demostracion.php">demostración con una familia ficticia</a>.</p>
    <a class="btn btn-secondary" href="index.php">Volver al inicio</a>
  </div>
<?php else: ?>
  <div id="app" data-slug="<?= e($slug) ?>"></div>
  <noscript><div class="alert alert-error">Esta herramienta requiere JavaScript.</div></noscript>
<?php endif; ?>
</div>
<footer class="public-footer">
  <div class="inner">Sus respuestas individuales son confidenciales. La familia únicamente conoce resultados agregados.</div>
</footer>
<?php if ($family): ?>
<script src="assets/js/bvm-cuestionario.js"></script>
<script src="assets/js/bvm-api.js"></script>
<script src="assets/js/bvm-participante.js"></script>
<?php endif; ?>
</body>
</html>
