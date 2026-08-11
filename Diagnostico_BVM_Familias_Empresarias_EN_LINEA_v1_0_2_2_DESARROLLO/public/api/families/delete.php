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

// Doble confirmación: la interfaz exige escribir el nombre exacto de la familia.
$confirmName = trim((string)($in['confirm_name'] ?? ''));
if ($confirmName !== (string)$family['family_name']) {
    bvm_json_error('Para eliminar definitivamente, escriba el nombre exacto de la familia.', 422);
}

FamilyRepository::hardDelete($id);
AuditRepository::log('family-deleted', (int)$user['id'], $id, null, ['family_name' => $family['family_name']]);
bvm_json_response(['ok' => true]);
