<?php
/**
 * salud.php — estado de la instalación (versión 1.0.2).
 *
 * Ruta ADMINISTRATIVA PROTEGIDA: exige sesión BVM activa. Sustituye a la
 * antigua práctica de copiar health-check.php al área pública (prohibida).
 * Si prefiere que ni siquiera exista tras la puesta en marcha, borre este
 * archivo; el verificador por terminal (php tools/health-check.php) sigue
 * disponible.
 */
require_once dirname(__DIR__) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
require_once BVM_PRIVATE_DIR . '/admin_layout.php';
require_once BVM_PRIVATE_DIR . '/health_checks.php';

$user = bvm_require_admin_page();
bvm_security_headers(null, true);

$checks = bvm_health_run();
$overall = bvm_health_overall($checks);

bvm_admin_header($user, 'Estado de la instalación', 'salud', [
    'Familias' => 'familias.php',
    'Estado de la instalación' => null,
]);

$overallText = [
    'ok' => 'Todo correcto',
    'warn' => 'Correcto, con advertencias',
    'fail' => 'Hay fallas que atender',
][$overall];
$overallClass = ['ok' => 'alert-success', 'warn' => 'alert-warning', 'fail' => 'alert-error'][$overall];
$chip = ['ok' => 'OK', 'warn' => 'Advertencia', 'fail' => 'Falla'];
$chipClass = ['ok' => 'cap-disponible', 'warn' => 'cap-cerca_del_limite', 'fail' => 'cap-excedido'];
?>
<h1>Estado de la instalación</h1>
<div class="alert <?= $overallClass ?>" role="status"><strong><?= e($overallText) ?>.</strong>
Verificación de la versión 1.0.2 (esquema, seguridad y configuración).</div>

<section class="panel">
  <div class="table-wrap">
    <table class="bvm-table">
      <thead><tr><th scope="col">Verificación</th><th scope="col">Estado</th><th scope="col">Detalle</th></tr></thead>
      <tbody>
      <?php foreach ($checks as $name => $c): ?>
        <tr>
          <td><code><?= e($name) ?></code></td>
          <td><span class="status-chip <?= $chipClass[$c['status']] ?>"><?= e($chip[$c['status']]) ?></span></td>
          <td><?= e($c['detail']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="hint">La prueba definitiva de <code>private/</code> y <code>database/</code> es abrir su URL directa
  en una ventana privada: debe responder 403 o 404, nunca mostrar contenido.</p>
</section>
<?php
bvm_admin_footer();
