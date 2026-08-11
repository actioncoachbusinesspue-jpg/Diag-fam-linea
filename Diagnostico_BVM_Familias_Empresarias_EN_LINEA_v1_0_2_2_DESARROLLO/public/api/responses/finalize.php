<?php
/**
 * Finalización: exige las 20 respuestas y las 2 externas; bloquea la
 * participación de forma definitiva (solo BVM puede reabrir, con auditoría).
 */
require_once dirname(__DIR__, 2) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
bvm_require_method('POST');
$sess = bvm_require_participant_api();
// Finalizar es un cambio de estado: exige la misma política que guardar.
bvm_require_participation_allowed($sess['family_id'], 'finalize');

$participantId = $sess['participant_id'];
$participant = ParticipantRepository::findById($participantId);
if (!$participant) {
    bvm_json_error('Participación no encontrada.', 404);
}
if ($participant['status'] === 'finalizado') {
    bvm_json_error('Su participación ya fue finalizada.', 423, ['locked' => true]);
}

$answers = ResponseRepository::answersArray($participantId);
$missing = [];
foreach ($answers as $i => $v) {
    if ($v === null) {
        $missing[] = $i + 1;
    }
}
if ($missing) {
    bvm_json_error('Faltan respuestas por contestar: ' . implode(', ', array_map(fn($n) => 'A' . $n, $missing)), 422, ['missing' => $missing]);
}
$external = ResponseRepository::externalAnswersMap($participantId);
foreach (BVM_EXTERNAL_QUESTION_IDS as $ext) {
    if (!isset($external[$ext])) {
        bvm_json_error('Falta responder las dos preguntas finales.', 422);
    }
}

if (!ParticipantRepository::finalize($participantId)) {
    bvm_json_error('No fue posible finalizar. Intente de nuevo.', 500);
}
AuditRepository::log('participant-finalized', null, $sess['family_id'], $participantId);

bvm_json_response(['ok' => true, 'status' => 'finalizado']);
