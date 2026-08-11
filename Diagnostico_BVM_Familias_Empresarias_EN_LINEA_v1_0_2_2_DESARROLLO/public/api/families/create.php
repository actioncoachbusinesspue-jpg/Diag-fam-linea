<?php
require_once dirname(__DIR__, 2) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
bvm_require_method('POST');
$user = bvm_require_admin_api();

$in = bvm_json_input();
$name = (string)($in['family_name'] ?? '');
if (!bvm_valid_family_name($name)) {
    bvm_json_error('Indique un nombre de familia o empresa válido (2 a 150 caracteres).');
}
$expected = $in['expected_participants'] ?? null;
if ($expected !== null && $expected !== '') {
    if (!is_numeric($expected) || (int)$expected < 1 || (int)$expected > 500) {
        bvm_json_error('El número esperado de participantes debe estar entre 1 y 500.');
    }
    $expected = (int)$expected;
} else {
    $expected = null;
}
// 1.0.2.2 — Hallazgo 3: «participantes esperados» es una META/REFERENCIA.
// El predeterminado es FALSE: escribir un número esperado NO activa por sí solo
// el límite. Solo lo activa la casilla expresa «Cerrar nuevos registros al
// alcanzar el número esperado». El backend guarda exactamente lo enviado.
$enforceLimit = filter_var($in['enforce_participant_limit'] ?? false, FILTER_VALIDATE_BOOLEAN);
$opensAt = ($in['opens_at'] ?? '') !== '' ? (string)$in['opens_at'] : null;
$closesAt = ($in['closes_at'] ?? '') !== '' ? (string)$in['closes_at'] : null;
// Mismas reglas de fecha que en la edición (1.0.2.2): 422 y mensaje claro.
if ($opensAt !== null && !bvm_valid_date($opensAt)) {
    bvm_json_error('Fecha de apertura no válida (formato AAAA-MM-DD).', 422);
}
if ($closesAt !== null && !bvm_valid_date($closesAt)) {
    bvm_json_error('Fecha de cierre no válida (formato AAAA-MM-DD).', 422);
}
if ($opensAt !== null && $closesAt !== null && $closesAt < $opensAt) {
    bvm_json_error('La fecha de cierre no puede ser anterior a la de apertura.', 422);
}

[$family, $accessCode] = FamilyRepository::create($name, $expected, $opensAt, $closesAt, (int)$user['id'], $enforceLimit);
AuditRepository::log('family-created', (int)$user['id'], (int)$family['id'], null, [
    'family_name' => $family['family_name'],
]);

bvm_json_response([
    'ok' => true,
    'family' => [
        'id' => (int)$family['id'],
        'family_name' => $family['family_name'],
        'public_slug' => $family['public_slug'],
        'status' => $family['status'],
        'expected_participants' => $expected,
        // La interfaz confirma expresamente el modo de cupo guardado
        // («Cupo: referencia» / «Cupo: límite activo»), tal como se envió.
        'enforce_participant_limit' => (int)$family['enforce_participant_limit'] === 1,
        'accepts_participants' => false, // nace en Borrador
        'invite_url' => bvm_base_url() . '/participar.php?f=' . $family['public_slug'],
    ],
    // La clave se muestra UNA sola vez; solo se guarda su hash.
    'access_code' => $accessCode,
]);
