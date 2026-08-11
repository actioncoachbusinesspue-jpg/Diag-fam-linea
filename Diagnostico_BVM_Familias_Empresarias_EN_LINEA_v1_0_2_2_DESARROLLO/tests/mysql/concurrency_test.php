<?php
/**
 * Concurrencia REAL del cupo sobre MySQL: dos procesos PHP disputan el
 * último lugar de una familia con límite activo. Exactamente uno debe
 * registrarse; el otro debe recibir capacity_reached. Repite la disputa varias
 * veces para reducir la probabilidad de un falso positivo por azar.
 *
 * Requiere BVM_IT_HOST/DB/USER/PASS (los exporta tests/mysql/run_mysql.sh).
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$worker = __DIR__ . '/worker_register.php';

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

// Bootstrap propio (misma base que la suite): crea la familia de la disputa.
$work = sys_get_temp_dir() . '/bvm_conc_' . bin2hex(random_bytes(4));
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

$adminId = AdminUserRepository::create('Admin Concurrencia', 'admin.conc.' . bin2hex(random_bytes(3)), 'clave-larga-de-prueba-123');

const ROUNDS = 5;
for ($round = 1; $round <= ROUNDS; $round++) {
    // Familia nueva por ronda: esperados 3, dos lugares ya ocupados.
    [$family] = FamilyRepository::create("Familia Disputa $round " . bin2hex(random_bytes(3)), 3, null, null, $adminId, true);
    $familyId = (int)$family['id'];
    FamilyRepository::update($familyId, ['status' => 'abierta']);
    ParticipantRepository::register($familyId, "Ocupante Uno R$round", 'Primera generación', 'Otro rol patrimonial');
    ParticipantRepository::register($familyId, "Ocupante Dos R$round", 'Primera generación', 'Otro rol patrimonial');

    // Dos procesos disputan el ÚLTIMO lugar simultáneamente.
    $procs = [];
    foreach (['Aspirante A', 'Aspirante B'] as $i => $name) {
        $cmd = [PHP_BINARY, $worker, (string)$familyId, "$name R$round"];
        $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[$i] = [$procs[$i], $pipes];
    }
    $results = [];
    foreach ($procs as [$p, $pipes]) {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        proc_close($p);
        $decoded = json_decode(trim($out), true);
        if (!is_array($decoded)) {
            check("Ronda $round: worker respondió JSON", false, trim($err . ' ' . $out));
            continue 2;
        }
        $results[] = $decoded;
    }

    $winners = array_filter($results, fn($r) => !empty($r['ok']));
    $full = array_filter(
        $results,
        fn($r) => empty($r['ok']) && ($r['reason_code'] ?? '') === ParticipationPolicy::CAPACITY_REACHED
    );
    check("Ronda $round: exactamente un ganador", count($winners) === 1, json_encode($results));
    check("Ronda $round: el otro recibe capacity_reached", count($full) === 1, json_encode($results));

    $count = count(ParticipantRepository::listByFamily($familyId));
    check("Ronda $round: el conteo final es 3 (nunca 4)", $count === 3, (string)$count);
}

@unlink($configFile);
@rmdir($work);
echo $failures === 0 ? "\nCONCURRENCIA: TODAS LAS RONDAS PASARON\n" : "\nCONCURRENCIA: $failures FALLAS\n";
exit($failures === 0 ? 0 : 1);
