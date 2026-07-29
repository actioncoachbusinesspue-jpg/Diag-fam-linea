<?php
require_once dirname(__DIR__, 3) . '/private/bootstrap.php';
require_once BVM_PRIVATE_DIR . '/services/FamilyDataService.php';
bvm_require_method('GET');
bvm_require_admin_api(false);

$id = (int)($_GET['id'] ?? 0);
$family = $id > 0 ? FamilyRepository::findById($id) : null;
if (!$family) {
    bvm_json_error('Familia no encontrada.', 404);
}

$participants = ParticipantRepository::listByFamily($id);
$finished = count(array_filter($participants, fn($p) => $p['status'] === 'finalizado'));
$expected = $family['expected_participants'] !== null ? (int)$family['expected_participants'] : null;

bvm_json_response([
    'ok' => true,
    'family' => FamilyDataService::buildFamilyObject($family, $participants),
    'meta' => [
        'finished_count' => $finished,
        'registered_count' => count($participants),
        'expected_participants' => $expected,
        'family_status' => $family['status'],
        // "Lectura preliminar" cuando no procede mostrar el reporte como definitivo.
        'preliminary' => $finished === 0
            || $family['status'] === 'abierta'
            || ($expected !== null && $finished < $expected),
    ],
]);
