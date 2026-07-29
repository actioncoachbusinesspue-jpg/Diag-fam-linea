<?php
/**
 * Autosave: guarda UNA respuesta (1-20, valor 1-5) por operación,
 * en transacción, con control de revisión y bloqueo tras finalizar.
 */
require_once dirname(__DIR__, 2) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
bvm_require_method('POST');
$sess = bvm_require_participant_api();

$in = bvm_json_input();
$questionId = $in['question_id'] ?? null;
$value = $in['value'] ?? null;
$revision = $in['revision'] ?? null;
$currentIndex = $in['current_index'] ?? null;

if (!bvm_valid_question_id($questionId)) {
    bvm_json_error('Pregunta no válida.', 422);
}
if (!bvm_valid_answer_value($value)) {
    bvm_json_error('La respuesta debe ser un valor entre 1 y 5.', 422);
}
if (!is_numeric($revision)) {
    bvm_json_error('Falta el número de revisión.', 422);
}
$currentIndex = is_numeric($currentIndex) ? max(0, min(BVM_TOTAL_QUESTIONS, (int)$currentIndex)) : null;

$result = ResponseRepository::saveAnswer(
    $sess['participant_id'],
    (int)$questionId,
    (int)$value,
    (int)$revision,
    $currentIndex
);

if (!empty($result['locked'])) {
    bvm_json_error('Su participación ya fue finalizada y no puede modificarse.', 423, ['locked' => true]);
}
if (!empty($result['conflict'])) {
    bvm_json_error(
        'Otro dispositivo guardó cambios más recientes en esta participación. Recargue para continuar con la versión más actual.',
        409,
        ['conflict' => true, 'revision' => $result['revision']]
    );
}
if (empty($result['ok'])) {
    bvm_json_error('No fue posible guardar. Intente de nuevo.', 500);
}
bvm_json_response(['ok' => true, 'revision' => $result['revision']]);
