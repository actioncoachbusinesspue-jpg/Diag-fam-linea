<?php
/**
 * Pruebas ESPECÍFICAS de MySQL/MariaDB (no expresables en SQLite):
 * ENUM estricto, CHECK, claves foráneas con cascada, unique, utf8mb4
 * de 4 bytes, fechas, rollback y SELECT ... FOR UPDATE (bloqueo real).
 *
 * Se ejecuta desde tests/mysql/run_mysql.sh con BVM_IT_* configurados y la
 * base ya poblada por run_integration.php (que corrió antes en esta base).
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$work = sys_get_temp_dir() . '/bvm_mysqlspec_' . bin2hex(random_bytes(4));
mkdir($work, 0770, true);
$configFile = $work . '/config.php';
file_put_contents($configFile, '<?php return ' . var_export([
    'db' => [
        'driver' => 'mysql',
        'host' => getenv('BVM_IT_HOST') ?: '127.0.0.1',
        'name' => getenv('BVM_IT_DB') ?: 'bvm_it',
        'user' => getenv('BVM_IT_USER') ?: 'root',
        'pass' => getenv('BVM_IT_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'key' => bin2hex(random_bytes(16)), 'env' => 'development',
        'base_url' => 'http://localhost/bvm-test', 'session_name' => 'BVMTEST',
        'session_lifetime_minutes' => 45, 'install_token' => '',
    ],
    'security' => ['max_login_attempts' => 5, 'lockout_minutes' => 15],
], true) . ';');
putenv('BVM_CONFIG_FILE=' . $configFile);

require_once $root . '/private/bootstrap.php';

$failures = 0;
function check(string $name, bool $cond, string $detail = ''): void
{
    global $failures;
    if ($cond) {
        echo "[OK]    $name\n";
    } else {
        $failures++;
        echo "[FALLA] $name" . ($detail ? " — $detail" : '') . "\n";
    }
}

$pdo = Database::pdo();
$now = bvm_now();

// Familia y participante de apoyo
$pdo->exec("INSERT INTO families (public_slug, family_name, access_code_hash, status, created_at, updated_at)
            VALUES ('mysqlspec01', 'Familia MySQL Específica', 'hash', 'abierta', '$now', '$now')");
$famId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO participants (public_id, family_id, participant_name, normalized_name, generation,
            participation_role, resume_token_hash, created_at, updated_at)
            VALUES ('p_mysqlspec01', $famId, 'Persona Uno', 'persona uno', 'Primera generación',
                    'Otro rol patrimonial', 'hash', '$now', '$now')");
$pid = (int)$pdo->lastInsertId();

// 1. ENUM estricto: estado inválido rechazado
try {
    $pdo->exec("UPDATE families SET status = 'estado_invalido' WHERE id = $famId");
    check('ENUM rechaza estado inválido', false, 'aceptó estado_invalido');
} catch (Throwable $e) {
    check('ENUM rechaza estado inválido', true);
}

// 2. CHECK: respuesta fuera de 1-5 rechazada
try {
    $pdo->exec("INSERT INTO responses (participant_id, question_id, answer_value, created_at, updated_at)
                VALUES ($pid, '1', 9, '$now', '$now')");
    check('CHECK rechaza valor 9', false, 'aceptó 9');
} catch (Throwable $e) {
    check('CHECK rechaza valor 9', true);
}

// 3. FK: participante hacia familia inexistente rechazado
try {
    $pdo->exec("INSERT INTO participants (public_id, family_id, participant_name, normalized_name, generation,
                participation_role, resume_token_hash, created_at, updated_at)
                VALUES ('p_fk_bad', 999999999, 'X Y', 'x y', 'Primera generación', 'Otro rol patrimonial', 'h', '$now', '$now')");
    check('FK rechaza familia inexistente', false);
} catch (Throwable $e) {
    check('FK rechaza familia inexistente', true);
}

// 4. UNIQUE: nombre normalizado duplicado por familia rechazado
try {
    $pdo->exec("INSERT INTO participants (public_id, family_id, participant_name, normalized_name, generation,
                participation_role, resume_token_hash, created_at, updated_at)
                VALUES ('p_dup01', $famId, 'Persona Uno', 'persona uno', 'Primera generación', 'Otro rol patrimonial', 'h', '$now', '$now')");
    check('UNIQUE rechaza nombre duplicado', false);
} catch (Throwable $e) {
    check('UNIQUE rechaza nombre duplicado', true);
}

// 5. UNIQUE del lookup 1.0.2: mismo hash en la misma familia rechazado; NULL múltiple permitido
$pdo->exec("UPDATE participants SET resume_token_lookup_hash = 'lookup-fijo' WHERE id = $pid");
try {
    $pdo->exec("INSERT INTO participants (public_id, family_id, participant_name, normalized_name, generation,
                participation_role, resume_token_hash, resume_token_lookup_hash, created_at, updated_at)
                VALUES ('p_lk01', $famId, 'Persona Dos', 'persona dos', 'Primera generación', 'Otro rol patrimonial', 'h', 'lookup-fijo', '$now', '$now')");
    check('UNIQUE del lookup por familia', false, 'aceptó lookup duplicado');
} catch (Throwable $e) {
    check('UNIQUE del lookup por familia', true);
}
$okNulls = true;
try {
    $pdo->exec("INSERT INTO participants (public_id, family_id, participant_name, normalized_name, generation,
                participation_role, resume_token_hash, resume_token_lookup_hash, created_at, updated_at)
                VALUES ('p_null1', $famId, 'Persona Tres', 'persona tres', 'Primera generación', 'Otro rol patrimonial', 'h', NULL, '$now', '$now'),
                       ('p_null2', $famId, 'Persona Cuatro', 'persona cuatro', 'Primera generación', 'Otro rol patrimonial', 'h', NULL, '$now', '$now')");
} catch (Throwable $e) {
    $okNulls = false;
}
check('Índice único admite múltiples NULL (datos legados)', $okNulls);

// 6. utf8mb4 real: caracteres de 4 bytes sobreviven el viaje completo
$emoji = 'Familia Ñandú 🌵 中文';
$st = $pdo->prepare('UPDATE families SET family_name = ? WHERE id = ?');
$st->execute([$emoji, $famId]);
$st = $pdo->prepare('SELECT family_name FROM families WHERE id = ?');
$st->execute([$famId]);
check('utf8mb4 conserva caracteres de 4 bytes', $st->fetchColumn() === $emoji);

// 7. Fechas: DATE y DATETIME conservan valores exactos
$st = $pdo->prepare('UPDATE families SET opens_at = ?, closes_at = ?, report_date = ? WHERE id = ?');
$st->execute(['2026-12-31', '2027-01-05', '2026-07-29', $famId]);
$st = $pdo->prepare('SELECT opens_at, closes_at, report_date FROM families WHERE id = ?');
$st->execute([$famId]);
$row = $st->fetch();
check('Fechas DATE exactas', $row['opens_at'] === '2026-12-31' && $row['closes_at'] === '2027-01-05' && $row['report_date'] === '2026-07-29');

// 8. Transacción con rollback: nada persiste
$before = (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE family_id = $famId")->fetchColumn();
try {
    Database::transaction(function (PDO $p) use ($famId, $now) {
        $p->exec("INSERT INTO participants (public_id, family_id, participant_name, normalized_name, generation,
                  participation_role, resume_token_hash, created_at, updated_at)
                  VALUES ('p_rb01', $famId, 'Persona Rollback', 'persona rollback', 'Primera generación', 'Otro rol patrimonial', 'h', '$now', '$now')");
        throw new RuntimeException('forzar rollback');
    });
} catch (RuntimeException $e) {
    // esperado
}
$after = (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE family_id = $famId")->fetchColumn();
check('Rollback revierte la transacción', $before === $after);

// 9. SELECT ... FOR UPDATE bloquea de verdad: una segunda conexión con
//    NOWAIT recibe error de bloqueo mientras la primera retiene la fila.
$conn2 = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('BVM_IT_HOST') ?: '127.0.0.1', getenv('BVM_IT_DB') ?: 'bvm_it'),
    getenv('BVM_IT_USER') ?: 'root',
    getenv('BVM_IT_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->beginTransaction();
$pdo->query("SELECT * FROM families WHERE id = $famId FOR UPDATE")->fetch();
$locked = false;
try {
    $conn2->beginTransaction();
    $conn2->query("SELECT * FROM families WHERE id = $famId FOR UPDATE NOWAIT")->fetch();
    $conn2->rollBack();
} catch (Throwable $e) {
    $locked = true;
    if ($conn2->inTransaction()) {
        $conn2->rollBack();
    }
}
$pdo->rollBack();
check('FOR UPDATE retiene el bloqueo de la fila', $locked);

// 10. Eliminación en cascada: borrar la familia elimina participantes y respuestas
$st = $pdo->prepare('INSERT INTO responses (participant_id, question_id, answer_value, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
$st->execute([$pid, '2', 3, $now, $now]);
$pdo->exec("DELETE FROM families WHERE id = $famId");
$orphanP = (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE family_id = $famId")->fetchColumn();
$orphanR = (int)$pdo->query("SELECT COUNT(*) FROM responses WHERE participant_id = $pid")->fetchColumn();
check('Cascada elimina participantes y respuestas', $orphanP === 0 && $orphanR === 0);

@unlink($configFile);
@rmdir($work);
echo $failures === 0 ? "\nMYSQL ESPECÍFICO: TODAS LAS PRUEBAS PASARON\n" : "\nMYSQL ESPECÍFICO: $failures FALLAS\n";
exit($failures === 0 ? 0 : 1);
