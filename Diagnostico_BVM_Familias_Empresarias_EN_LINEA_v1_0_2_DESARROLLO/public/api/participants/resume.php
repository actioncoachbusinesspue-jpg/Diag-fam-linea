<?php
/**
 * Continuidad multi-dispositivo: liga + clave de familia (ya validada en
 * sesión) + código personal → recupera la participación.
 */
require_once dirname(__DIR__, 2) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
bvm_require_method('POST');
bvm_session_start();
bvm_csrf_require();

$in = bvm_json_input();
$familyId = (int)($_SESSION['bvm_family_unlocked'] ?? 0);
if ($familyId <= 0) {
    bvm_json_error('Primero ingrese la clave de la familia.', 401);
}
$code = strtoupper(trim((string)($in['personal_code'] ?? '')));
if (!preg_match('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code)) {
    bvm_json_error('El código personal tiene el formato XXXX-XXXX.', 422);
}

$attemptKey = 'personal-code|' . $familyId . '|' . ($_SERVER['REMOTE_ADDR'] ?? '');
if (LoginAttemptRepository::isLocked($attemptKey)) {
    bvm_json_error('Demasiados intentos. Espere unos minutos e intente de nuevo.', 429);
}

$participant = ParticipantRepository::findByPersonalCode($familyId, $code);
if (!$participant) {
    LoginAttemptRepository::registerFailure($attemptKey);
    bvm_json_error('El código no corresponde a ninguna participación de esta familia.', 401);
}
LoginAttemptRepository::clear($attemptKey);

bvm_participant_login((int)$participant['id'], $familyId);
AuditRepository::log('participant-resumed', null, $familyId, (int)$participant['id']);

bvm_json_response([
    'ok' => true,
    'participant' => [
        'name' => $participant['participant_name'],
        'generation' => $participant['generation'],
        'participation_role' => $participant['participation_role'],
        'status' => $participant['status'],
        'current_index' => (int)$participant['current_index'],
        'revision' => (int)$participant['revision'],
        'answers' => ResponseRepository::answersArray((int)$participant['id']),
        'external_answers' => (object)ResponseRepository::externalAnswersMap((int)$participant['id']),
    ],
    'csrf_token' => bvm_csrf_token(),
]);
