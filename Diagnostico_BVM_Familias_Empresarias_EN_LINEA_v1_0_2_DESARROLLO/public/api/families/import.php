<?php
/**
 * Importación de respaldos JSON generados por la versión local.
 *
 * Dos fases (nunca importa automáticamente):
 *   phase=preview  → valida y devuelve el resumen para confirmación
 *   phase=commit   → importa en transacción (crear nueva o reemplazar)
 *
 * Solo JSON, tamaño limitado, schemaVersion validado, auditoría y rollback.
 */
require_once dirname(__DIR__, 3) . '/private/bootstrap.php';
require_once BVM_PRIVATE_DIR . '/services/FamilyDataService.php';
bvm_require_method('POST');
$user = bvm_require_admin_api();

const BVM_IMPORT_MAX_BYTES = 2 * 1024 * 1024; // 2 MB: muy por encima de cualquier familia real

$in = bvm_json_input();
$phase = (string)($in['phase'] ?? 'preview');
$backup = $in['backup'] ?? null;

// ---- Validación estricta del respaldo --------------------------------------
function bvm_validate_backup($backup): array
{
    if (!is_array($backup)) {
        return [false, 'El archivo no contiene un respaldo reconocible.'];
    }
    if (strlen(json_encode($backup)) > BVM_IMPORT_MAX_BYTES) {
        return [false, 'El archivo excede el tamaño permitido (2 MB).'];
    }
    if (($backup['appName'] ?? '') !== 'Diagnóstico BVM para Familias Empresarias') {
        return [false, 'El respaldo no corresponde a esta aplicación.'];
    }
    if (($backup['backupType'] ?? '') === 'demo-data') {
        return [false, 'Este archivo pertenece a una demostración y no puede importarse como información real.'];
    }
    $schema = $backup['schemaVersion'] ?? null;
    if (!is_int($schema)) {
        return [false, 'El respaldo no indica una versión de estructura reconocible.'];
    }
    if ($schema > BVM_SCHEMA_VERSION) {
        return [false, "El respaldo fue creado con una versión más reciente (estructura $schema) y no puede leerse."];
    }
    if ($schema < BVM_SCHEMA_VERSION) {
        return [false, "El respaldo pertenece a una versión anterior (estructura $schema) con otro cuestionario; impórtelo primero en la versión local actualizada."];
    }
    $family = $backup['family'] ?? null;
    if (!is_array($family) || !isset($family['familyName']) || !is_array($family['participants'] ?? null)) {
        return [false, 'El respaldo no contiene información de familia válida.'];
    }
    foreach ($family['participants'] as $p) {
        if (!is_array($p) || !bvm_valid_participant_name($p['participantName'] ?? null)) {
            return [false, 'El respaldo contiene participantes sin nombre válido.'];
        }
        $answers = $p['answers'] ?? [];
        if (!is_array($answers) || count($answers) > BVM_TOTAL_QUESTIONS) {
            return [false, 'El respaldo contiene respuestas con estructura no válida.'];
        }
        foreach ($answers as $v) {
            if ($v !== null && !bvm_valid_answer_value($v)) {
                return [false, 'Se encontraron valores de respuesta fuera del rango permitido (1 a 5).'];
            }
        }
        foreach (($p['externalAnswers'] ?? []) as $k => $v) {
            if (!in_array($k, BVM_EXTERNAL_QUESTION_IDS, true) || ($v !== null && !bvm_valid_answer_value($v))) {
                return [false, 'Las preguntas externas del respaldo no son válidas.'];
            }
        }
    }
    return [true, ''];
}

[$valid, $error] = bvm_validate_backup($backup);
if (!$valid) {
    bvm_json_error($error, 422);
}
$familyIn = $backup['family'];
$participantsIn = $familyIn['participants'];
$finishedCount = count(array_filter($participantsIn, fn($p) => ($p['status'] ?? '') === 'finalizado'));

if ($phase === 'preview') {
    bvm_json_response([
        'ok' => true,
        'preview' => [
            'family_name' => $familyIn['familyName'],
            'participants' => count($participantsIn),
            'finished' => $finishedCount,
            'created_at' => $familyIn['createdAt'] ?? null,
            'export_date' => $backup['exportDate'] ?? null,
            'questionnaire_version' => $backup['questionnaireVersion'] ?? null,
            'schema_version' => $backup['schemaVersion'],
            'participant_names' => array_map(fn($p) => $p['participantName'], $participantsIn),
        ],
    ]);
}

if ($phase !== 'commit') {
    bvm_json_error('Fase no reconocida.', 422);
}

$mode = (string)($in['mode'] ?? 'create'); // create | replace
$replaceId = (int)($in['replace_family_id'] ?? 0);
if ($mode === 'replace') {
    $target = $replaceId > 0 ? FamilyRepository::findById($replaceId) : null;
    if (!$target) {
        bvm_json_error('La familia a reemplazar no existe.', 404);
    }
    if (($in['confirm_replace'] ?? '') !== 'REEMPLAZAR') {
        bvm_json_error('El reemplazo requiere confirmación expresa.', 422);
    }
} elseif ($mode !== 'create') {
    bvm_json_error('Modo de importación no reconocido.', 422);
}

try {
    $result = Database::transaction(function (PDO $pdo) use ($mode, $replaceId, $familyIn, $participantsIn, $user) {
        $now = bvm_now();
        if ($mode === 'replace') {
            // Elimina participaciones actuales (cascade borra respuestas) y conserva liga/clave.
            $st = $pdo->prepare('DELETE FROM participants WHERE family_id = ?');
            $st->execute([$replaceId]);
            $familyId = $replaceId;
            FamilyRepository::update($familyId, [
                'family_name' => (string)$familyIn['familyName'],
                'expected_participants' => isset($familyIn['expectedParticipants']) && $familyIn['expectedParticipants'] !== null
                    ? (int)$familyIn['expectedParticipants'] : null,
                'report_date' => isset($familyIn['reportDate']) && bvm_valid_date((string)$familyIn['reportDate'])
                    ? (string)$familyIn['reportDate'] : null,
            ]);
            $accessCode = null;
        } else {
            [$family, $accessCode] = FamilyRepository::create(
                (string)$familyIn['familyName'],
                isset($familyIn['expectedParticipants']) && $familyIn['expectedParticipants'] !== null
                    ? (int)$familyIn['expectedParticipants'] : null,
                null,
                null,
                (int)$user['id']
            );
            $familyId = (int)$family['id'];
            FamilyRepository::update($familyId, [
                'status' => 'cerrada', // datos históricos: no abre participación por defecto
                'report_date' => isset($familyIn['reportDate']) && bvm_valid_date((string)$familyIn['reportDate'])
                    ? (string)$familyIn['reportDate'] : null,
            ]);
        }

        $imported = 0;
        foreach ($participantsIn as $p) {
            $ins = $pdo->prepare(
                'INSERT INTO participants
                   (public_id, family_id, participant_name, normalized_name, generation, participation_role,
                    resume_token_hash, status, current_index, revision, created_at, updated_at, completed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)'
            );
            $status = ($p['status'] ?? '') === 'finalizado' ? 'finalizado' : 'en_proceso';
            $toDb = function (?string $iso) use ($now): ?string {
                if (!$iso) { return null; }
                $ts = strtotime($iso);
                return $ts === false ? $now : gmdate('Y-m-d H:i:s', $ts);
            };
            $ins->execute([
                bvm_public_id('p'),
                $familyId,
                (string)$p['participantName'],
                bvm_normalize_name((string)$p['participantName']),
                (string)($p['generation'] ?? ''),
                (string)($p['participationRole'] ?? ''),
                // El respaldo local no incluye códigos personales: se genera un
                // hash imposible de adivinar; BVM puede regenerar códigos después.
                password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                $status,
                min(BVM_TOTAL_QUESTIONS, (int)($p['currentIndex'] ?? 0)),
                $toDb($p['createdAt'] ?? null) ?? $now,
                $toDb($p['updatedAt'] ?? null) ?? $now,
                $status === 'finalizado' ? ($toDb($p['completedAt'] ?? null) ?? $now) : null,
            ]);
            $participantId = (int)$pdo->lastInsertId();
            foreach (($p['answers'] ?? []) as $q => $v) {
                if ($v === null) { continue; }
                $rIns = $pdo->prepare(
                    'INSERT INTO responses (participant_id, question_id, answer_value, created_at, updated_at) VALUES (?, ?, ?, ?, ?)'
                );
                $rIns->execute([$participantId, (string)($q + 1), (int)$v, $now, $now]);
            }
            foreach (($p['externalAnswers'] ?? []) as $extId => $v) {
                if ($v === null) { continue; }
                $eIns = $pdo->prepare(
                    'INSERT INTO external_responses (participant_id, question_id, answer_value, created_at, updated_at) VALUES (?, ?, ?, ?, ?)'
                );
                $eIns->execute([$participantId, (string)$extId, (int)$v, $now, $now]);
            }
            $imported++;
        }
        return ['family_id' => $familyId, 'imported' => $imported, 'access_code' => $accessCode];
    });
} catch (Throwable $e) {
    // Rollback automático de la transacción: la base queda como estaba.
    error_log('import_error: ' . $e->getMessage());
    bvm_json_error('La importación falló y no se realizó ningún cambio.', 500);
}

AuditRepository::log('backup-imported', (int)$user['id'], (int)$result['family_id'], null, [
    'mode' => $mode,
    'participants' => $result['imported'],
]);

bvm_json_response([
    'ok' => true,
    'family_id' => $result['family_id'],
    'imported_participants' => $result['imported'],
    'access_code' => $result['access_code'], // solo en modo create
]);
