<?php
require_once __DIR__ . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
bvm_session_start();
bvm_security_headers();
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diagnóstico BVM para Familias Empresarias</title>
<link rel="stylesheet" href="assets/css/bvm-app.css">
</head>
<body class="bvm-public">
<div class="public-shell">
  <header class="public-hero">
    <p class="brand-kicker">BVM · Familias Empresarias</p>
    <h1>Diagnóstico BVM para Familias Empresarias</h1>
    <p>Una radiografía ejecutiva de las capacidades de su familia empresaria:
       relaciones, gobierno, desarrollo del patrimonio y continuidad.</p>
  </header>

  <div class="path-grid">
    <section class="path-card primary" aria-labelledby="p1">
      <span class="tag">Para participantes</span>
      <h2 id="p1">Responder el diagnóstico</h2>
      <p>Para responder, utilice la liga de invitación que BVM le compartió para su familia.
         Cada familia cuenta con una liga y una clave propias.</p>
      <p class="meta">Duración aproximada: 15 a 20 minutos. Puede pausar y continuar después.</p>
      <!-- 1.0.2.2: esta página NO conoce la familia, así que nunca envía al
           participante a participar.php sin liga (pantalla de error). -->
      <button type="button" class="btn btn-primary" id="invite-help-open"
              aria-expanded="false" aria-controls="invite-help">Ya recibí una invitación</button>
      <p class="meta" style="margin-top:10px;">
        <a href="demostracion.php">Conocer primero la demostración</a>
      </p>

      <div id="invite-help" class="invite-help" role="group" aria-labelledby="invite-help-t" hidden>
        <h3 id="invite-help-t" tabindex="-1">Abra la liga completa que recibió</h3>
        <p>La participación solo puede iniciarse desde la liga propia de su familia.
           Búsquela en el correo o mensaje que le envió BVM y ábrala tal como se la
           compartieron: tiene esta forma.</p>
        <p><code><?= e(bvm_base_url()) ?>/participar.php?f=<span aria-hidden="true">…</span></code></p>
        <p>Al abrirla se le pedirá la <strong>clave de la familia</strong> que acompaña a la
           invitación. Con ella podrá registrarse o continuar donde se quedó.</p>
        <p class="meta">Si no encuentra la liga o la clave, solicítelas a su contacto en BVM:
           por seguridad, esta página no puede buscarlas ni enviarlas.</p>
        <button type="button" class="btn btn-quiet" id="invite-help-close">Entendido</button>
      </div>
    </section>

    <section class="path-card" aria-labelledby="p2">
      <span class="tag">Demostración sin datos reales</span>
      <h2 id="p2">Conocer la radiografía BVM</h2>
      <p>Explore, mediante la Familia Horizonte —un caso completamente ficticio—, cómo BVM integra
         las percepciones individuales y las convierte en una lectura ejecutiva accionable.</p>
      <p class="meta">Duración estimada: 8 a 12 minutos. Puede salir en cualquier momento.</p>
      <a class="btn btn-secondary" href="demostracion.php">Iniciar demostración guiada</a>
    </section>

    <section class="path-card" aria-labelledby="p3">
      <span class="tag">Uso interno</span>
      <h2 id="p3">Acceso BVM</h2>
      <p>Opción exclusiva del equipo autorizado de BVM para administrar familias,
         consultar avances y generar el reporte integral.</p>
      <p class="meta">Requiere usuario y contraseña.</p>
      <a class="btn btn-quiet" href="acceso-bvm.php" aria-label="Acceso BVM (requiere usuario y contraseña)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><rect x="5.5" y="10.5" width="13" height="9" rx="1.6"/><path d="M8.2 10.5V7.6a3.8 3.8 0 0 1 7.6 0v2.9"/></svg>
        Acceso BVM
      </a>
    </section>
  </div>
</div>
<footer class="public-footer">
  <div class="inner">
    Las respuestas individuales son confidenciales: la familia solo conoce resultados agregados.
    La demostración utiliza exclusivamente información ficticia.
  </div>
</footer>
<script>
(function () {
  'use strict';
  var open = document.getElementById('invite-help-open');
  var close = document.getElementById('invite-help-close');
  var panel = document.getElementById('invite-help');
  if (!open || !panel) { return; }
  function setOpen(isOpen) {
    panel.hidden = !isOpen;
    open.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (isOpen) { panel.querySelector('h3').focus(); } else { open.focus(); }
  }
  open.addEventListener('click', function () { setOpen(panel.hidden); });
  if (close) { close.addEventListener('click', function () { setOpen(false); }); }
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && !panel.hidden) { setOpen(false); }
  });
})();
</script>
</body>
</html>
