<?php
/**
 * import-local-backup.php — importación por línea de comandos de un respaldo
 * JSON de la versión local (alternativa a la pantalla Administración BVM →
 * Importar respaldo, útil durante una migración asistida por el hosting).
 *
 * Uso: php tools/import-local-backup.php ruta/al/respaldo.json
 *
 * Crea SIEMPRE una familia nueva (estado cerrada). Muestra el resumen y pide
 * confirmación interactiva antes de escribir.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Esta herramienta solo funciona por línea de comandos.\n");
}
if ($argc < 2) {
    exit("Uso: php tools/import-local-backup.php ruta/al/respaldo.json\n");
}
$file = $argv[1];
if (!is_file($file) || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'json') {
    exit("El archivo no existe o no es .json\n");
}
if (filesize($file) > 2 * 1024 * 1024) {
    exit("El archivo excede el tamaño permitido (2 MB).\n");
}

require_once dirname(__DIR__) . '/private/bootstrap.php';
require_once BVM_PRIVATE_DIR . '/services/FamilyDataService.php';

$backup = json_decode((string)file_get_contents($file), true);
if (!is_array($backup) || ($backup['schemaVersion'] ?? null) !== BVM_SCHEMA_VERSION
    || !is_array($backup['family']['participants'] ?? null)) {
    exit("El archivo no es un respaldo válido de la versión local (estructura " . BVM_SCHEMA_VERSION . ").\n");
}

$family = $backup['family'];
$participants = $family['participants'];
$finished = count(array_filter($participants, fn($p) => ($p['status'] ?? '') === 'finalizado'));

echo "Familia:        {$family['familyName']}\n";
echo "Participantes:  " . count($participants) . " ($finished finalizados)\n";
echo "Exportado:      " . ($backup['exportDate'] ?? '—') . "\n";
echo "Cuestionario:   " . ($backup['questionnaireVersion'] ?? '—') . "\n\n";
echo "¿Importar como FAMILIA NUEVA? Escriba SI para confirmar: ";
$answer = trim((string)fgets(STDIN));
if (strtoupper($answer) !== 'SI') {
    exit("Importación cancelada. No se realizó ningún cambio.\n");
}

// Reutiliza la misma validación e inserción transaccional del endpoint web.
$_SERVER['REQUEST_METHOD'] = 'POST';
try {
    $result = Database::transaction(function (PDO $pdo) use ($family, $participants) {
        [$created, $accessCode] = FamilyRepository::create(
            (string)$family['familyName'],
            isset($family['expectedParticipants']) && $family['expectedParticipants'] !== null ? (int)$family['expectedParticipants'] : null,
            null,
            null,
            0
        );
        $familyId = (int)$created['id'];
        FamilyRepository::update($familyId, ['status' => 'cerrada']);
        $now = bvm_now();
        foreach ($participants as $p) {
            if (!bvm_valid_participant_name($p['participantName'] ?? null)) {
                throw new RuntimeException('Participante sin nombre válido.');
            }
            $status = ($p['status'] ?? '') === 'finalizado' ? 'finalizado' : 'en_proceso';
            $ins = $pdo->prepare(
                'INSERT INTO participants
                   (public_id, family_id, participant_name, normalized_name, generation, participation_role,
                    resume_token_hash, status, current_index, revision, created_at, updated_at, completed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)'
            );
            $ins->execute([
                bvm_public_id('p'), $familyId, (string)$p['participantName'],
                bvm_normalize_name((string)$p['participantName']),
                (string)($p['generation'] ?? ''), (string)($p['participationRole'] ?? ''),
                password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                $status, min(BVM_TOTAL_QUESTIONS, (int)($p['currentIndex'] ?? 0)),
                $now, $now, $status === 'finalizado' ? $now : null,
            ]);
            $pid = (int)$pdo->lastInsertId();
            foreach (($p['answers'] ?? []) as $q => $v) {
                if ($v === null) { continue; }
                if (!bvm_valid_answer_value($v)) {
                    throw new RuntimeException('Respuesta fuera de rango.');
                }
                $pdo->prepare('INSERT INTO responses (participant_id, question_id, answer_value, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$pid, (string)($q + 1), (int)$v, $now, $now]);
            }
            foreach (($p['externalAnswers'] ?? []) as $extId => $v) {
                if ($v === null || !in_array($extId, BVM_EXTERNAL_QUESTION_IDS, true)) { continue; }
                $pdo->prepare('INSERT INTO external_responses (participant_id, question_id, answer_value, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$pid, (string)$extId, (int)$v, $now, $now]);
            }
        }
        return ['family_id' => $familyId, 'access_code' => $accessCode];
    });
} catch (Throwable $e) {
    exit("La importación falló y no se realizó ningún cambio: {$e->getMessage()}\n");
}

AuditRepository::log('backup-imported-cli', null, (int)$result['family_id'], null, ['file' => basename($file)]);
echo "\nImportación completada. Familia #{$result['family_id']}.\n";
echo "Clave de acceso nueva (guárdela ahora): {$result['access_code']}\n";
