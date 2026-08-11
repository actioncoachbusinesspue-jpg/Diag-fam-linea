<?php
/**
 * Demostración pública con Familia Horizonte (completamente ficticia).
 * Sin contraseña. Aislada de los datos reales: el motor corre con
 * almacenamiento en memoria y sin acceso a la base de datos.
 */
require_once __DIR__ . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
require_once BVM_PRIVATE_DIR . '/reference_renderer.php';
bvm_session_start();

bvm_render_reference_app('demo', null, bvm_base_url() . '/index.php');
