<?php
/**
 * Radiografía y reporte integral de una familia real (solo administración).
 * Reutiliza el motor metodológico de referencia con los datos de MySQL:
 * fórmulas, interpretación y reporte de 12 páginas idénticos al maestro.
 */
require_once dirname(__DIR__) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
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

// 1.0.2.2 — Hallazgo 10: nunca se presenta un reporte definitivo vacío.
// Sin ninguna participación finalizada no hay lectura posible, ni siquiera
// escribiendo la URL a mano.
$participants = ParticipantRepository::listByFamily($familyId);
$finished = 0;
foreach ($participants as $p) {
    if ($p['status'] === 'finalizado') {
        $finished++;
    }
}
$expected = $family['expected_participants'] !== null ? (int)$family['expected_participants'] : null;

if ($finished === 0) {
    bvm_security_headers(null, true);
    require_once BVM_PRIVATE_DIR . '/admin_layout.php';
    bvm_admin_header($user, (string)$family['family_name'], 'familias', [
        'Familias' => 'familias.php',
        (string)$family['family_name'] => 'familia.php?id=' . $familyId,
        'Reporte' => null,
    ]);
    echo '<h1>Reporte no disponible todavía</h1>';
    echo '<div class="alert alert-info" role="status">Reporte disponible cuando exista al menos ' .
         'una participación finalizada.</div>';
    echo '<p class="hint">Esta familia registra ' . count($participants) . ' participaciones, ninguna finalizada. ' .
         'Si desea mostrar la lectura ejecutiva sin datos reales, utilice la demostración con la Familia Horizonte.</p>';
    echo '<p><a class="btn btn-secondary" href="familia.php?id=' . $familyId . '">Volver a la familia</a> ' .
         '<a class="btn btn-quiet" href="' . e(bvm_base_url()) . '/demostracion.php">Abrir la demostración</a></p>';
    bvm_admin_footer();
    exit;
}

AuditRepository::log('report-opened', (int)$user['id'], $familyId);
$familyData = FamilyDataService::buildFamilyObject($family);

// Lectura preliminar: se declara EN PANTALLA cuando aún faltan participaciones
// respecto del número esperado. No altera ningún cálculo ni el documento impreso.
$notice = null;
if ($expected !== null && $finished < $expected) {
    $notice = 'Lectura preliminar con ' . $finished . ' de ' . $expected . ' participaciones finalizadas.';
}

bvm_render_reference_app('report', $familyData, bvm_base_url() . '/admin/familia.php?id=' . $familyId, $notice);
