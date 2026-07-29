<?php
require_once dirname(__DIR__, 3) . '/private/bootstrap.php';
bvm_require_method('GET');
bvm_require_admin_api(false);

$id = (int)($_GET['id'] ?? 0);
$family = $id > 0 ? FamilyRepository::findById($id) : null;
if (!$family) {
    bvm_json_error('Familia no encontrada.', 404);
}

$participants = ParticipantRepository::listByFamily($id);
$finished = 0;
$list = [];
foreach ($participants as $p) {
    if ($p['status'] === 'finalizado') {
        $finished++;
    }
    // Avance sin exponer respuestas individuales.
    $list[] = [
        'id' => (int)$p['id'],
        'public_id' => $p['public_id'],
        'name' => $p['participant_name'],
        'generation' => $p['generation'],
        'participation_role' => $p['participation_role'],
        'status' => $p['status'],
        'answered_count' => ResponseRepository::answeredCount((int)$p['id']),
        'created_at' => $p['created_at'],
        'updated_at' => $p['updated_at'],
        'completed_at' => $p['completed_at'],
    ];
}

$expected = $family['expected_participants'] !== null ? (int)$family['expected_participants'] : null;
$capacity = FamilyRepository::capacity($family, count($participants));

bvm_json_response([
    'ok' => true,
    'family' => [
        'id' => (int)$family['id'],
        'family_name' => $family['family_name'],
        'public_slug' => $family['public_slug'],
        'status' => $family['status'],
        'expected_participants' => $expected,
        'enforce_participant_limit' => $capacity['enforce_participant_limit'],
        'capacity' => $capacity,
        'questionnaire_version' => $family['questionnaire_version'],
        'report_date' => $family['report_date'],
        'opens_at' => $family['opens_at'],
        'closes_at' => $family['closes_at'],
        'created_at' => $family['created_at'],
        'invite_url' => bvm_base_url() . '/participar.php?f=' . $family['public_slug'],
        'registered_count' => count($participants),
        'finished_count' => $finished,
        'progress_pct' => $expected ? (int)round(100 * $finished / max(1, $expected)) : null,
    ],
    'participants' => $list,
]);
