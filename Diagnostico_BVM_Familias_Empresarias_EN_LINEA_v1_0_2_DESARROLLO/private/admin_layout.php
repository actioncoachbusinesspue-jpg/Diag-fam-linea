<?php
declare(strict_types=1);

/** admin_layout.php — encabezado/pie compartidos de Administración BVM. */

function bvm_admin_header(array $user, string $title, string $active, array $breadcrumbs = []): void
{
    $base = bvm_base_url();
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="<?= e(bvm_csrf_token()) ?>">
<meta name="bvm-base" content="<?= e($base) ?>">
<title><?= e($title) ?> — Administración BVM</title>
<link rel="stylesheet" href="<?= e($base) ?>/assets/css/bvm-app.css">
</head>
<body class="bvm-public">
<header class="admin-header">
  <div class="inner">
    <div class="title"><small>Administración BVM</small>Diagnóstico BVM para Familias Empresarias</div>
    <nav aria-label="Secciones">
      <a href="<?= e($base) ?>/admin/familias.php" <?= $active === 'familias' ? 'aria-current="page"' : '' ?>>Familias</a>
      <a href="<?= e($base) ?>/admin/importar.php" <?= $active === 'importar' ? 'aria-current="page"' : '' ?>>Importar respaldo</a>
      <a href="<?= e($base) ?>/demostracion.php">Demostración</a>
    </nav>
    <div class="admin-user">
      <span><?= e((string)$user['name']) ?></span>
      <button class="btn btn-quiet" id="logout-btn" type="button" style="color:#E8EBF2;border-color:#3A4A6E;padding:6px 12px;font-size:.82rem;">Cerrar sesión</button>
    </div>
  </div>
</header>
<main class="admin-main">
<?php if ($breadcrumbs): ?>
  <nav class="breadcrumbs" aria-label="Ubicación">
    <?php
    $parts = [];
    foreach ($breadcrumbs as $label => $href) {
        $parts[] = $href ? '<a href="' . e($href) . '">' . e($label) . '</a>' : '<span aria-current="page">' . e($label) . '</span>';
    }
    echo implode(' › ', $parts);
    ?>
  </nav>
<?php endif;
}

/** @param string $extraScript JS propio de la página (se inserta tras bvm-api.js). */
function bvm_admin_footer(string $extraScript = ''): void
{
    $base = bvm_base_url();
    ?>
</main>
<footer class="public-footer">
  <div class="inner">Uso interno BVM. La información de las familias es confidencial: los reportes muestran únicamente resultados agregados.</div>
</footer>
<script src="<?= e($base) ?>/assets/js/bvm-api.js"></script>
<script>
document.getElementById('logout-btn').addEventListener('click', function () {
  BvmApi.post('/api/auth/logout.php', {}).then(function (d) {
    window.location.href = d.redirect || (BvmApi.base() + '/index.php');
  });
});
</script>
<?php if ($extraScript !== ''): ?>
<script>
<?= $extraScript ?>
</script>
<?php endif; ?>
</body>
</html>
<?php
}
