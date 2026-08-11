<?php
/**
 * Paso 1 del participante: validar liga (slug) + clave de familia.
 * Si es correcta, la sesión queda autorizada para registrarse o reanudar.
 */
require_once dirname(__DIR__, 2) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
bvm_require_method('POST');
bvm_session_start();
bvm_csrf_require();

$in = bvm_json_input();
$slug = (string)($in['slug'] ?? '');
$code = (string)($in['access_code'] ?? '');

if (!bvm_valid_slug($slug) || trim($code) === '') {
    bvm_json_error('Liga o clave no válidas.', 422);
}

$attemptKey = 'family-key|' . $slug . '|' . ($_SERVER['REMOTE_ADDR'] ?? '');
if (LoginAttemptRepository::isLocked($attemptKey)) {
    bvm_json_error('Demasiados intentos. Espere unos minutos e intente de nuevo.', 429);
}

$family = FamilyRepository::findBySlug($slug);
if (!$family || !FamilyRepository::verifyAccessCode($family, $code)) {
    LoginAttemptRepository::registerFailure($attemptKey);
    bvm_json_error('La clave no corresponde a esta familia. Verifíquela con quien le envió la invitación.', 401);
}
LoginAttemptRepository::clear($attemptKey);

$_SESSION['bvm_family_unlocked'] = (int)$family['id'];
$_SESSION['bvm_family_unlocked_slug'] = (string)$family['public_slug'];

// Informativo para la interfaz: el motivo exacto por el que (no) se puede
// registrar o continuar se calcula con la política central, ANTES de llenar
// el formulario. La validación definitiva ocurre al registrar (transacción
// con la familia bloqueada), nunca aquí.
$policy = ParticipationPolicy::forFamily($family);
$open = $policy->lifecycleReason() === ParticipationPolicy::OK;

bvm_json_response([
    'ok' => true,
    'family' => [
        'family_name' => $family['family_name'],
        'status' => $family['status'],
        'open_for_participation' => $open,
        'closes_at' => $family['closes_at'],
        'accepting_new_registrations' => $policy->canRegisterNewParticipant(),
    ],
    // Contrato con la interfaz: códigos de motivo estructurados + fechas.
    'policy' => $policy->describe(),
]);
