<?php
/**
 * «Salir de esta participación» — cierre REAL de la sesión del participante
 * (versión 1.0.2.2).
 *
 * Destruye las variables de sesión del participante y la clave de familia
 * validada, e invalida el contexto de continuidad de esta sesión: para volver
 * a entrar hará falta la clave de la familia y el código personal.
 *
 * NO cierra una sesión administrativa BVM independiente en el mismo navegador.
 */
require_once dirname(__DIR__, 2) . '/bvm_paths.php'; // localizador único de private/
bvm_require_method('POST');
bvm_session_start();
bvm_csrf_require();

$sess = bvm_participant_session();
if ($sess !== null) {
    AuditRepository::log('participant-logout', null, $sess['family_id'], $sess['participant_id']);
}
bvm_participant_logout();

bvm_json_response(['ok' => true, 'csrf_token' => bvm_csrf_token()]);
