<?php
require_once dirname(__DIR__, 3) . '/private/bootstrap.php';
bvm_require_method('POST');
$user = bvm_require_admin_api();

$in = bvm_json_input();
$id = (int)($in['id'] ?? 0);
$family = $id > 0 ? FamilyRepository::findById($id) : null;
if (!$family) {
    bvm_json_error('Familia no encontrada.', 404);
}

$fields = [];

if (array_key_exists('family_name', $in)) {
    if (!bvm_valid_family_name($in['family_name'])) {
        bvm_json_error('Nombre de familia no válido.');
    }
    $fields['family_name'] = trim((string)$in['family_name']);
}
if (array_key_exists('expected_participants', $in)) {
    $v = $in['expected_participants'];
    if ($v === null || $v === '') {
        $fields['expected_participants'] = null;
    } elseif (is_numeric($v) && (int)$v >= 1 && (int)$v <= 500) {
        $fields['expected_participants'] = (int)$v;
    } else {
        bvm_json_error('El número esperado de participantes debe estar entre 1 y 500.');
    }
}
if (array_key_exists('enforce_participant_limit', $in)) {
    $fields['enforce_participant_limit'] = filter_var($in['enforce_participant_limit'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
}
foreach (['opens_at', 'closes_at', 'report_date'] as $dateField) {
    if (array_key_exists($dateField, $in)) {
        $v = $in[$dateField];
        if ($v === null || $v === '') {
            $fields[$dateField] = null;
        } elseif (bvm_valid_date($v)) {
            $fields[$dateField] = $v;
        } else {
            bvm_json_error('Fecha no válida en ' . $dateField . '.');
        }
    }
}
if (array_key_exists('status', $in)) {
    if (!bvm_valid_family_status($in['status'])) {
        bvm_json_error('Estado no válido.');
    }
    $fields['status'] = $in['status'];
    $fields['archived_at'] = $in['status'] === 'archivada' ? bvm_now() : null;
}

if (!$fields) {
    bvm_json_error('No se indicó ningún cambio.');
}

FamilyRepository::update($id, $fields);
AuditRepository::log('family-updated', (int)$user['id'], $id, null, ['fields' => array_keys($fields)]);
bvm_json_response(['ok' => true]);
