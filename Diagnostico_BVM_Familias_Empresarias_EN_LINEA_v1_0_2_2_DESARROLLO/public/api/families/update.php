<?php
require_once dirname(__DIR__, 2) . '/bvm_paths.php'; // localizador único de private/ (v1.0.2.1)
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
// 1.0.2.2 — Hallazgo 7: las fechas se validan SIEMPRE en el servidor, también
// al editar. La validación HTML del navegador no es una garantía.
foreach (['opens_at', 'closes_at', 'report_date'] as $dateField) {
    if (array_key_exists($dateField, $in)) {
        $v = $in[$dateField];
        if ($v === null || $v === '') {
            $fields[$dateField] = null;
        } elseif (bvm_valid_date($v)) {
            $fields[$dateField] = $v;
        } else {
            bvm_json_error('Fecha no válida en ' . $dateField . ' (formato AAAA-MM-DD).', 422);
        }
    }
}

// Coherencia del periodo: cierre >= apertura. Se compara el resultado FINAL de
// la edición, combinando lo enviado con lo ya guardado (editar solo una de las
// dos fechas no puede dejar un periodo imposible).
//   · apertura sin cierre: válido      · cierre sin apertura: válido
//   · mismo día: válido                · cierre anterior a apertura: 422
// report_date es una fecha editorial y NO interviene en esta regla.
$finalOpens = array_key_exists('opens_at', $fields)
    ? $fields['opens_at']
    : ($family['opens_at'] !== null ? substr((string)$family['opens_at'], 0, 10) : null);
$finalCloses = array_key_exists('closes_at', $fields)
    ? $fields['closes_at']
    : ($family['closes_at'] !== null ? substr((string)$family['closes_at'], 0, 10) : null);
if ($finalOpens !== null && $finalCloses !== null && $finalCloses < $finalOpens) {
    bvm_json_error('La fecha de cierre no puede ser anterior a la de apertura.', 422);
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
