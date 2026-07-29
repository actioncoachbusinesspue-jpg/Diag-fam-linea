<?php
/**
 * Acciones administrativas sobre un participante:
 *  - regenerate_code: nuevo código personal (se muestra una vez)
 *  - reopen: reapertura excepcional de una participación finalizada
 *            (requiere confirmación expresa; queda en auditoría)
 */
require_once dirname(__DIR__, 2) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
bvm_require_method('POST');
$user = bvm_require_admin_api();

$in = bvm_json_input();
$participantId = (int)($in['participant_id'] ?? 0);
$action = (string)($in['action'] ?? '');

$participant = $participantId > 0 ? ParticipantRepository::findById($participantId) : null;
if (!$participant) {
    bvm_json_error('Participante no encontrado.', 404);
}
$familyId = (int)$participant['family_id'];

if ($action === 'regenerate_code') {
    $code = ParticipantRepository::regeneratePersonalCode($participantId);
    AuditRepository::log('participant-code-regenerated', (int)$user['id'], $familyId, $participantId);
    bvm_json_response(['ok' => true, 'personal_code' => $code]);
}

if ($action === 'reopen') {
    if (($in['confirm'] ?? '') !== 'REABRIR') {
        bvm_json_error('La reapertura requiere confirmación expresa.', 422);
    }
    if ($participant['status'] !== 'finalizado') {
        bvm_json_error('La participación no está finalizada.', 422);
    }
    ParticipantRepository::reopen($participantId);
    AuditRepository::log('participant-reopened', (int)$user['id'], $familyId, $participantId, [
        'authorized_by' => $user['username'],
    ]);
    bvm_json_response(['ok' => true]);
}

bvm_json_error('Acción no reconocida.', 422);
