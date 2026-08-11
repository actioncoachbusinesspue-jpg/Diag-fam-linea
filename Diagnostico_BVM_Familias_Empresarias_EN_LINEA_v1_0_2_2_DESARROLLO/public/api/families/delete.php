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

// Doble confirmación: la interfaz muestra primero el alcance exacto de la
// eliminación y exige escribir el nombre completo de la familia.
$confirmName = trim((string)($in['confirm_name'] ?? ''));
if ($confirmName !== (string)$family['family_name']) {
    bvm_json_error('Para eliminar definitivamente, escriba el nombre exacto de la familia.', 422);
}

// Eliminación REAL de la familia y de sus datos dependientes (1.0.2.2).
$deleted = FamilyRepository::hardDelete($id);

// Auditoría: SOLO metadatos no sensibles (nombre y conteos). Bajo la etiqueta
// de «eliminación definitiva» no se conserva ninguna respuesta individual.
AuditRepository::log('family-hard-deleted', (int)$user['id'], null, null, [
    'family_name' => $family['family_name'],
    'deleted_participants' => $deleted['participants'],
    'deleted_responses' => $deleted['responses'],
    'deleted_external_responses' => $deleted['external_responses'],
]);
bvm_json_response(['ok' => true, 'deleted' => $deleted]);
