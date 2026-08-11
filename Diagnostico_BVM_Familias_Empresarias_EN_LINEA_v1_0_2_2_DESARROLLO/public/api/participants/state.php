<?php
/** Estado actual de la participación en sesión (para reanudar tras recargar). */
require_once dirname(__DIR__, 2) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
bvm_require_method('GET');
$sess = bvm_require_participant_api(false);

$participant = ParticipantRepository::findById($sess['participant_id']);
if (!$participant || (int)$participant['family_id'] !== $sess['family_id']) {
    bvm_participant_logout();
    bvm_json_error('Sesión no válida.', 401);
}
$family = FamilyRepository::findById($sess['family_id']);

bvm_json_response([
    'ok' => true,
    'family' => ['family_name' => $family ? $family['family_name'] : ''],
    'participant' => [
        'name' => $participant['participant_name'],
        'status' => $participant['status'],
        'current_index' => (int)$participant['current_index'],
        'revision' => (int)$participant['revision'],
        'answers' => ResponseRepository::answersArray((int)$participant['id']),
        'external_answers' => (object)ResponseRepository::externalAnswersMap((int)$participant['id']),
    ],
]);
