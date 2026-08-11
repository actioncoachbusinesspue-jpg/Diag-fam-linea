<?php
/** Estado actual de la participación en sesión (para reanudar tras recargar). */
require_once dirname(__DIR__, 2) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
bvm_require_method('GET');
$sess = bvm_require_participant_api(false);

// Aislamiento entre familias (1.0.2.2): la interfaz siempre indica la liga que
// está abierta. Si la sesión pertenece a OTRA familia, no se devuelve ningún
// dato: la sesión anterior se cierra y el flujo de esta liga empieza limpio.
$requestedSlug = (string)($_GET['f'] ?? '');
if ($requestedSlug !== '' && $requestedSlug !== $sess['family_slug']) {
    bvm_participant_logout();
    bvm_json_error('Sesión de participante de otra familia.', 401, ['reason_code' => 'other_family']);
}

$participant = ParticipantRepository::findById($sess['participant_id']);
if (!$participant || (int)$participant['family_id'] !== $sess['family_id']) {
    bvm_participant_logout();
    bvm_json_error('Sesión no válida.', 401);
}
$family = FamilyRepository::findById($sess['family_id']);
// La interfaz necesita la política vigente para decidir si puede seguir
// respondiendo o debe mostrar el aviso específico de bloqueo (1.0.2.2).
$policy = $family ? ParticipationPolicy::forFamily($family)->describe() : null;

bvm_json_response([
    'ok' => true,
    'family' => ['family_name' => $family ? $family['family_name'] : ''],
    'policy' => $policy,
    'participant' => [
        'name' => $participant['participant_name'],
        'status' => $participant['status'],
        'current_index' => (int)$participant['current_index'],
        'revision' => (int)$participant['revision'],
        'answers' => ResponseRepository::answersArray((int)$participant['id']),
        'external_answers' => (object)ResponseRepository::externalAnswersMap((int)$participant['id']),
    ],
]);
