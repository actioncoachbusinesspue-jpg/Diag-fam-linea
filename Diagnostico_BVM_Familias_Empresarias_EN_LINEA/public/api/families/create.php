<?php
require_once dirname(__DIR__, 3) . '/private/bootstrap.php';
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
$opensAt = ($in['opens_at'] ?? '') !== '' ? (string)$in['opens_at'] : null;
$closesAt = ($in['closes_at'] ?? '') !== '' ? (string)$in['closes_at'] : null;
if ($opensAt !== null && !bvm_valid_date($opensAt)) {
    bvm_json_error('Fecha de apertura no válida.');
}
if ($closesAt !== null && !bvm_valid_date($closesAt)) {
    bvm_json_error('Fecha de cierre no válida.');
}
if ($opensAt !== null && $closesAt !== null && $closesAt < $opensAt) {
    bvm_json_error('La fecha de cierre no puede ser anterior a la de apertura.');
}

[$family, $accessCode] = FamilyRepository::create($name, $expected, $opensAt, $closesAt, (int)$user['id']);
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
        'invite_url' => bvm_base_url() . '/participar.php?f=' . $family['public_slug'],
    ],
    // La clave se muestra UNA sola vez; solo se guarda su hash.
    'access_code' => $accessCode,
]);
