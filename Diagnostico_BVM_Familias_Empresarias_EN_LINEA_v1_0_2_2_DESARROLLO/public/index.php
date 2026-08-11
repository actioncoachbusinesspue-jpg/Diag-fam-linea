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
      <p>Si su familia le compartió una invitación, ingrese con la liga y la clave que recibió.
         Responderá 20 afirmaciones de manera individual. No existen respuestas correctas o incorrectas.</p>
      <p class="meta">Duración aproximada: 15 a 20 minutos. Puede pausar y continuar después.</p>
      <a class="btn btn-primary" href="participar.php">Comenzar o continuar</a>
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
</body>
</html>
