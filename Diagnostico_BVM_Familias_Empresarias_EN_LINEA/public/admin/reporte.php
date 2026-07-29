<?php
/**
 * Radiografía y reporte integral de una familia real (solo administración).
 * Reutiliza el motor metodológico de referencia con los datos de MySQL:
 * fórmulas, interpretación y reporte de 12 páginas idénticos al maestro.
 */
require_once dirname(__DIR__, 2) . '/private/bootstrap.php';
require_once BVM_PRIVATE_DIR . '/reference_renderer.php';
require_once BVM_PRIVATE_DIR . '/services/FamilyDataService.php';

$user = bvm_require_admin_page();

$familyId = (int)($_GET['id'] ?? 0);
$family = $familyId > 0 ? FamilyRepository::findById($familyId) : null;
if (!$family) {
    http_response_code(404);
    bvm_security_headers(null, true);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Familia no encontrada.\n";
    exit;
}

AuditRepository::log('report-opened', (int)$user['id'], $familyId);
$familyData = FamilyDataService::buildFamilyObject($family);

bvm_render_reference_app('report', $familyData, bvm_base_url() . '/admin/familia.php?id=' . $familyId);
