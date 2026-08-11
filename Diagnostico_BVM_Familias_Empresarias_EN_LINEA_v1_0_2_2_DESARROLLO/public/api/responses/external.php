<?php
/** Guarda una de las dos preguntas externas (nunca modifican el índice). */
require_once dirname(__DIR__, 2) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
bvm_require_method('POST');
$sess = bvm_require_participant_api();
// Misma política que el autosave: sin familia abierta no se guarda nada.
bvm_require_participation_allowed($sess['family_id'], 'save');

$in = bvm_json_input();
$questionId = $in['question_id'] ?? null;
$value = $in['value'] ?? null;
$revision = $in['revision'] ?? null;

if (!bvm_valid_external_id($questionId)) {
    bvm_json_error('Pregunta externa no válida.', 422);
}
if (!bvm_valid_answer_value($value)) {
    bvm_json_error('La respuesta debe ser un valor entre 1 y 5.', 422);
}
if (!is_numeric($revision)) {
    bvm_json_error('Falta el número de revisión.', 422);
}

$result = ResponseRepository::saveExternalAnswer($sess['participant_id'], (string)$questionId, (int)$value, (int)$revision);

if (!empty($result['locked'])) {
    bvm_json_error('Su participación ya fue finalizada y no puede modificarse.', 423, ['locked' => true]);
}
if (!empty($result['conflict'])) {
    bvm_json_error(
        'Otro dispositivo guardó cambios más recientes. Recargue para continuar.',
        409,
        ['conflict' => true, 'revision' => $result['revision']]
    );
}
if (empty($result['ok'])) {
    bvm_json_error('No fue posible guardar. Intente de nuevo.', 500);
}
bvm_json_response(['ok' => true, 'revision' => $result['revision']]);
