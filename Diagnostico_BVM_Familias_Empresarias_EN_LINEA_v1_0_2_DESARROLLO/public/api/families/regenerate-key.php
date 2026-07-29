<?php
require_once dirname(__DIR__, 2) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
bvm_require_method('POST');
$user = bvm_require_admin_api();

$in = bvm_json_input();
$id = (int)($in['id'] ?? 0);
$family = $id > 0 ? FamilyRepository::findById($id) : null;
if (!$family) {
    bvm_json_error('Familia no encontrada.', 404);
}

$code = FamilyRepository::regenerateAccessCode($id);
AuditRepository::log('family-key-regenerated', (int)$user['id'], $id);

// La clave anterior deja de funcionar. La nueva se muestra una sola vez.
bvm_json_response(['ok' => true, 'access_code' => $code]);
