<?php
/**
 * Prueba de integración (SQLite) del backend en línea.
 *
 * Simula el ciclo completo con los datos EXACTOS de Familia Horizonte:
 * crear familia → registrar 5 participantes → autosave respuesta por
 * respuesta → externas → finalizar → exportar en formato de referencia.
 * Verifica además: clave incorrecta, duplicados, conflicto de revisión,
 * bloqueo tras finalizar y aislamiento entre familias.
 *
 * Uso: php tests/integration/run_integration.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$work = sys_get_temp_dir() . '/bvm_it_' . bin2hex(random_bytes(4));
mkdir($work, 0770, true);
$dbPath = $work . '/test.sqlite';

$configFile = $work . '/config.php';
file_put_contents($configFile, '<?php return ' . var_export([
    'db' => ['driver' => 'sqlite', 'sqlite_path' => $dbPath],
    'app' => [
        'key' => bin2hex(random_bytes(16)),
        'env' => 'development',
        'base_url' => 'http://localhost/bvm-test',
        'session_name' => 'BVMTEST',
        'session_lifetime_minutes' => 45,
        'install_token' => 'token-pruebas',
    ],
    'security' => ['max_login_attempts' => 5, 'lockout_minutes' => 15],
], true) . ';');
putenv('BVM_CONFIG_FILE=' . $configFile);

require_once $root . '/private/bootstrap.php';
require_once BVM_PRIVATE_DIR . '/services/FamilyDataService.php';

Database::pdo()->exec(file_get_contents($root . '/tests/fixtures/schema_sqlite.sql'));

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

// Datos EXACTOS de Familia Horizonte (SAMPLE_FAMILY_DATA del maestro).
$horizonte = [
    ['Ricardo Elizondo', 'Primera generación', 'Dirección o liderazgo operativo',
        [5,4,4,4,4, 3,4,3,3,3, 4,4,3,3,3, 3,3,4,3,2], ['ext1' => 4, 'ext2' => 4]],
    ['Mariana Elizondo', 'Segunda generación', 'Consejo, órgano de gobierno o comité familiar',
        [4,4,3,4,3, 3,3,4,3,3, 3,4,3,3,4, 2,3,2,2,3], ['ext1' => 5, 'ext2' => 5]],
    ['Fernando Elizondo', 'Segunda generación', 'Accionista o propietario sin rol operativo',
        [4,4,4,3,4, 3,3,3,4,3, 3,3,4,3,3, 2,2,4,2,2], ['ext1' => 3, 'ext2' => 2]],
    ['Camila Elizondo', 'Tercera generación o posterior', 'Futuro propietario o heredero',
        [4,3,4,4,3, 4,3,3,3,4, 4,3,3,4,3, 3,2,2,3,2], ['ext1' => 4, 'ext2' => 4]],
    ['Diego Elizondo', 'Tercera generación o posterior', 'Futuro propietario o heredero',
        [4,4,3,3,4, 3,4,3,3,3, 3,3,3,3,4, 2,3,2,2,2], ['ext1' => 3, 'ext2' => 3]],
];

// 1. Administrador + familia
$adminId = AdminUserRepository::create('Equipo BVM', 'bvm.pruebas', 'clave-larga-de-prueba-123');
check('Crear administrador', $adminId > 0);
$found = AdminUserRepository::findByUsername('bvm.pruebas');
check('password_verify correcto', password_verify('clave-larga-de-prueba-123', $found['password_hash']));
check('password_verify incorrecto rechazado', !password_verify('otra-clave', $found['password_hash']));

[$family, $accessCode] = FamilyRepository::create('Familia Horizonte', 5, null, null, $adminId);
check('Crear familia', $family !== null && $family['status'] === 'borrador');
check('Slug aleatorio válido', bvm_valid_slug($family['public_slug']));
check('Clave con formato legible', (bool)preg_match('/^[A-Z]+-[A-Z2-9]{4}$/', $accessCode));
check('Clave no almacenada en claro', strpos(json_encode($family), $accessCode) === false);
check('Clave verifica', FamilyRepository::verifyAccessCode($family, $accessCode));
check('Clave incorrecta rechazada', !FamilyRepository::verifyAccessCode($family, 'HORIZONTE-XXXX'));

// Familia B para aislamiento
[$familyB, $codeB] = FamilyRepository::create('Familia Robles', 3, null, null, $adminId);
check('Clave de B no abre A', !FamilyRepository::verifyAccessCode($family, $codeB));

// 2. Apertura y participantes
FamilyRepository::update((int)$family['id'], ['status' => 'abierta']);
$family = FamilyRepository::findById((int)$family['id']);
check('Familia abierta acepta participación', FamilyRepository::isOpenForParticipation($family));

$participantIds = [];
foreach ($horizonte as $i => [$name, $gen, $role, $answers, $ext]) {
    [$p, $personalCode] = ParticipantRepository::create((int)$family['id'], $name, $gen, $role);
    $participantIds[$i] = (int)$p['id'];
    if ($i === 0) {
        check('Código personal formato XXXX-XXXX', (bool)preg_match('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $personalCode));
        check('Código personal solo como hash', strpos(json_encode($p), $personalCode) === false);
        check('Reanudar con código correcto', ParticipantRepository::findByPersonalCode((int)$family['id'], $personalCode)['id'] === $p['id']);
        check('Código no funciona en otra familia', ParticipantRepository::findByPersonalCode((int)$familyB['id'], $personalCode) === null);
    }
}
check('5 participantes registrados', count(ParticipantRepository::listByFamily((int)$family['id'])) === 5);

// Duplicado razonable: mismo nombre normalizado
check('Duplicado detectado (normalización)', ParticipantRepository::findByNormalizedName((int)$family['id'], '  ricardo   ELIZONDO ') !== null);

// 3. Autosave respuesta por respuesta con control de revisión
foreach ($horizonte as $i => [$name, $gen, $role, $answers, $ext]) {
    $pid = $participantIds[$i];
    $revision = 0;
    foreach ($answers as $q => $value) {
        $r = ResponseRepository::saveAnswer($pid, $q + 1, $value, $revision, $q + 1);
        if (empty($r['ok'])) {
            check("Autosave participante $i pregunta " . ($q + 1), false, json_encode($r));
            continue 2;
        }
        $revision = $r['revision'];
    }
    foreach ($ext as $extId => $value) {
        $r = ResponseRepository::saveExternalAnswer($pid, $extId, $value, $revision);
        $revision = $r['revision'] ?? $revision;
    }
}
check('Respuestas guardadas (20 por participante)', ResponseRepository::answeredCount($participantIds[0]) === 20);

// Valor fuera de rango rechazado por CHECK de la base
try {
    Database::pdo()->exec("INSERT INTO responses (participant_id, question_id, answer_value, created_at, updated_at) VALUES ({$participantIds[0]}, '99', 9, '2026-01-01', '2026-01-01')");
    check('Restricción 1-5 en base de datos', false, 'aceptó valor 9');
} catch (Throwable $e) {
    check('Restricción 1-5 en base de datos', true);
}

// Conflicto de revisión: un dispositivo con revisión vieja
$r = ResponseRepository::saveAnswer($participantIds[0], 1, 3, 0, 1);
check('Conflicto multi-dispositivo detectado', !empty($r['conflict']));

// 4. Finalización y bloqueo
foreach ($participantIds as $pid) {
    check("Finalizar participante $pid", ParticipantRepository::finalize($pid));
}
$p0 = ParticipantRepository::findById($participantIds[0]);
$r = ResponseRepository::saveAnswer($participantIds[0], 1, 3, (int)$p0['revision'], 1);
check('Edición tras finalizar bloqueada', !empty($r['locked']));
check('Doble finalización rechazada', !ParticipantRepository::finalize($participantIds[0]));

// 5. Exportación en formato de referencia
FamilyRepository::update((int)$family['id'], ['status' => 'cerrada', 'report_date' => '2026-07-29']);
$family = FamilyRepository::findById((int)$family['id']);
$familyObject = FamilyDataService::buildFamilyObject($family);
check('Formato: 5 participantes finalizados',
    count($familyObject['participants']) === 5
    && count(array_filter($familyObject['participants'], fn($p) => $p['status'] === 'finalizado')) === 5);
check('Formato: respuestas en orden', $familyObject['participants'][0]['answers'] === $horizonte[0][3]);
check('Formato: externas', (array)$familyObject['participants'][0]['externalAnswers'] === $horizonte[0][4]);

$backup = FamilyDataService::buildBackup($family);
check('Respaldo schemaVersion 5', $backup['schemaVersion'] === 5);
check('Respaldo questionnaireVersion', $backup['questionnaireVersion'] === 'BVM-FE-1.2');

$outDir = $root . '/qa-evidence/parity';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}
file_put_contents($outDir . '/family_from_db.json', json_encode($familyObject, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\nObjeto familia (vía base de datos) escrito en qa-evidence/parity/family_from_db.json\n";

// Limpieza
@unlink($dbPath);
@unlink($configFile);
@rmdir($work);

echo $failures === 0 ? "\nINTEGRACIÓN: TODAS LAS PRUEBAS PASARON\n" : "\nINTEGRACIÓN: $failures FALLAS\n";
exit($failures === 0 ? 0 : 1);
