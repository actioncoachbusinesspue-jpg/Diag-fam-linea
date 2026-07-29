<?php
/**
 * Worker de concurrencia: intenta registrar UN participante y escribe el
 * resultado como JSON en stdout. Lo lanza concurrency_test.php (dos copias
 * simultáneas disputando el último lugar del cupo).
 *
 * Uso: php worker_register.php FAMILY_ID "Nombre Participante"
 * (hereda BVM_CONFIG_FILE del proceso padre)
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/private/bootstrap.php';

$familyId = (int)($argv[1] ?? 0);
$name = (string)($argv[2] ?? 'Sin Nombre');

try {
    $r = ParticipantRepository::register($familyId, $name, 'Primera generación', 'Otro rol patrimonial');
    echo json_encode([
        'ok' => !empty($r['ok']),
        'error' => $r['error'] ?? null,
        'participant_id' => isset($r['participant']['id']) ? (int)$r['participant']['id'] : null,
    ]), "\n";
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'exception']), "\n";
}
