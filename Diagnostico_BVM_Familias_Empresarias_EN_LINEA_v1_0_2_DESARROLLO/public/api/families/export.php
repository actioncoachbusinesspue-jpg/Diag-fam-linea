<?php
require_once dirname(__DIR__, 3) . '/private/bootstrap.php';
require_once BVM_PRIVATE_DIR . '/services/FamilyDataService.php';
bvm_require_method('GET');
$user = bvm_require_admin_api(false);

$id = (int)($_GET['id'] ?? 0);
$family = $id > 0 ? FamilyRepository::findById($id) : null;
if (!$family) {
    bvm_json_error('Familia no encontrada.', 404);
}

$backup = FamilyDataService::buildBackup($family);
AuditRepository::log('family-exported', (int)$user['id'], $id);

bvm_security_headers(null, true);
header('Content-Type: application/json; charset=utf-8');
$slug = preg_replace('/[^a-z0-9]+/i', '_', (string)$family['family_name']);
header('Content-Disposition: attachment; filename="respaldo_bvm_' . $slug . '_' . gmdate('Ymd') . '.json"');
echo json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
