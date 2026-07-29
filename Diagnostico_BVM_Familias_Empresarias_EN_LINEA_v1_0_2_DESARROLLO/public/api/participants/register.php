<?php
/**
 * Registro de participante (tras validar la clave de familia).
 * Devuelve el código personal de continuidad UNA sola vez.
 */
require_once dirname(__DIR__, 3) . '/private/bootstrap.php';
bvm_require_method('POST');
bvm_session_start();
bvm_csrf_require();

$in = bvm_json_input();
$familyId = (int)($_SESSION['bvm_family_unlocked'] ?? 0);
if ($familyId <= 0) {
    bvm_json_error('Primero ingrese la clave de la familia.', 401);
}
$family = FamilyRepository::findById($familyId);
if (!$family) {
    bvm_json_error('Familia no encontrada.', 404);
}

$name = (string)($in['name'] ?? '');
$generation = (string)($in['generation'] ?? '');
$role = (string)($in['participation_role'] ?? '');
$consent = (bool)($in['consent'] ?? false);

if (!$consent) {
    bvm_json_error('Debe aceptar el aviso de privacidad para continuar.', 422);
}
if (!bvm_valid_participant_name($name)) {
    bvm_json_error('Indique su nombre completo (2 a 120 caracteres).', 422);
}
if (!bvm_valid_generation($generation)) {
    bvm_json_error('Seleccione su generación.', 422);
}
if (!bvm_valid_participation_role($role)) {
    bvm_json_error('Seleccione su rol patrimonial.', 422);
}

// Estado, fechas, cupo y duplicados se validan DENTRO de la transacción con
// la familia bloqueada (FOR UPDATE): dos registros simultáneos no pueden
// exceder el cupo, y la reanudación de participantes existentes nunca se bloquea.
try {
    $result = ParticipantRepository::register($familyId, $name, $generation, $role);
} catch (Throwable $e) {
    bvm_json_error('No fue posible completar el registro. Intente de nuevo.', 500);
}

if (empty($result['ok'])) {
    switch ($result['error'] ?? '') {
        case 'not_found':
            bvm_json_error('Familia no encontrada.', 404);
            break;
        case 'not_open':
            bvm_json_error('Esta familia no está aceptando nuevas participaciones en este momento.', 409);
            break;
        case 'family_full':
            bvm_json_error(
                'Esta aplicación ya alcanzó el número de participantes autorizado. ' .
                'Si ya se registró, utilice su código personal para continuar.',
                409,
                ['family_full' => true]
            );
            break;
        case 'duplicate':
            bvm_json_error(
                'Ya existe una participación registrada con este nombre. ' .
                'Si es usted, use su código personal en "Continuar donde me quedé". ' .
                'Si es otra persona con el mismo nombre, agregue una distinción (por ejemplo, una inicial).',
                409,
                ['duplicate' => true]
            );
            break;
        default:
            bvm_json_error('No fue posible completar el registro. Intente de nuevo.', 500);
    }
}

$participant = $result['participant'];
$personalCode = $result['personal_code'];

bvm_participant_login((int)$participant['id'], $familyId);
AuditRepository::log('participant-registered', null, $familyId, (int)$participant['id']);

bvm_json_response([
    'ok' => true,
    'personal_code' => $personalCode, // se muestra una sola vez
    // true cuando el número esperado opera como referencia y este registro lo superó
    'over_reference' => !empty($result['over_reference']),
    'participant' => [
        'name' => $participant['participant_name'],
        'status' => $participant['status'],
        'current_index' => 0,
        'revision' => 0,
    ],
    'csrf_token' => bvm_csrf_token(),
]);
