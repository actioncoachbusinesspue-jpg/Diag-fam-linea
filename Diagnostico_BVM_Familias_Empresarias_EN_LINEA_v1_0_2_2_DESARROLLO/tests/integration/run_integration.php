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

// Motor: SQLite por defecto (rápido, local). Con BVM_IT_DRIVER=mysql la MISMA
// suite corre contra MySQL/MariaDB real (tests/mysql/run_mysql.sh la invoca
// sobre una base temporal ya creada con database/schema.sql).
$itDriver = getenv('BVM_IT_DRIVER') ?: 'sqlite';
$dbConfig = $itDriver === 'mysql'
    ? [
        'driver' => 'mysql',
        'host' => getenv('BVM_IT_HOST') ?: '127.0.0.1',
        'name' => getenv('BVM_IT_DB') ?: 'bvm_it',
        'user' => getenv('BVM_IT_USER') ?: 'root',
        'pass' => getenv('BVM_IT_PASS') ?: '',
        'charset' => 'utf8mb4',
      ]
    : ['driver' => 'sqlite', 'sqlite_path' => $dbPath];

$configFile = $work . '/config.php';
file_put_contents($configFile, '<?php return ' . var_export([
    'db' => $dbConfig,
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

if ($itDriver === 'sqlite') {
    Database::pdo()->exec(file_get_contents($root . '/tests/fixtures/schema_sqlite.sql'));
}
echo "[INFO]  Motor de base de datos: " . Database::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";

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

// Opción A de roles (1.0.2): el rol consultor no puede crearse
try {
    AdminUserRepository::create('Consultor Prueba', 'consultor.prueba', 'clave-larga-de-prueba-123', 'consultor');
    check('Rol consultor deshabilitado (Opción A)', false, 'permitió crear un consultor');
} catch (InvalidArgumentException $e) {
    check('Rol consultor deshabilitado (Opción A)', true);
}

[$family, $accessCode] = FamilyRepository::create('Familia Horizonte', 5, null, null, $adminId);
check('Crear familia', $family !== null && $family['status'] === 'borrador');
check('Slug aleatorio válido', bvm_valid_slug($family['public_slug']));
check('Clave con formato legible (6 aleatorios desde 1.0.2)', (bool)preg_match('/^[A-Z]+-[A-Z2-9]{6}$/', $accessCode));
check('Clave no almacenada en claro', strpos(json_encode($family), $accessCode) === false);
check('Clave verifica', FamilyRepository::verifyAccessCode($family, $accessCode));
check('Clave incorrecta rechazada', !FamilyRepository::verifyAccessCode($family, 'HORIZONTE-XXXXXX'));

// Compatibilidad: una clave de 4 caracteres (formato anterior a 1.0.2) sigue verificando
$legacyKey = 'HORIZONTE-8K4P';
Database::pdo()->prepare('UPDATE families SET access_code_hash = ? WHERE id = ?')
    ->execute([password_hash($legacyKey, PASSWORD_DEFAULT), (int)$family['id']]);
$familyLegacy = FamilyRepository::findById((int)$family['id']);
check('Clave antigua de 4 caracteres sigue funcionando', FamilyRepository::verifyAccessCode($familyLegacy, $legacyKey));
// Restaurar la clave nueva para el resto del flujo
Database::pdo()->prepare('UPDATE families SET access_code_hash = ? WHERE id = ?')
    ->execute([password_hash($accessCode, PASSWORD_DEFAULT), (int)$family['id']]);
$family = FamilyRepository::findById((int)$family['id']);

// Regenerar produce el formato nuevo y la longitud configurada se respeta
$regen = FamilyRepository::regenerateAccessCode((int)$family['id']);
check('Clave regenerada usa 6 caracteres aleatorios', (bool)preg_match('/^[A-Z]+-[A-Z2-9]{6}$/', $regen));
$family = FamilyRepository::findById((int)$family['id']);
check('Clave regenerada verifica', FamilyRepository::verifyAccessCode($family, $regen));
check('Longitud aleatoria validada al rango [6,10]', bvm_family_access_random_length() === 6);

// Familia B para aislamiento
// Límite ACTIVO pedido expresamente (desde 1.0.2.2 el predeterminado es referencia).
[$familyB, $codeB] = FamilyRepository::create('Familia Robles', 3, null, null, $adminId, true);
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

// 2b. Cupo de participantes (1.0.2) — sobre Familia B (esperados: 3, límite activo)
FamilyRepository::update((int)$familyB['id'], ['status' => 'abierta']);
$familyB = FamilyRepository::findById((int)$familyB['id']);
check('Familia B con límite activo por solicitud expresa', (int)$familyB['enforce_participant_limit'] === 1);

$bCodes = [];
foreach (['Ana Robles', 'Luis Robles', 'Paula Robles'] as $i => $nameB) {
    $r = ParticipantRepository::register((int)$familyB['id'], $nameB, 'Primera generación', 'Otro rol patrimonial');
    check("Registro B" . ($i + 1) . " dentro del cupo", !empty($r['ok']));
    if (!empty($r['ok'])) {
        $bCodes[$nameB] = ['code' => $r['personal_code'], 'id' => (int)$r['participant']['id']];
    }
}
$r = ParticipantRepository::register((int)$familyB['id'], 'Sofía Robles', 'Segunda generación', 'Otro rol patrimonial');
// 1.0.2.2: el rechazo llega como motivo estructurado de la política central.
check('Cuarto registro rechazado por cupo (capacity_reached)',
    empty($r['ok']) && ($r['error'] ?? '') === 'policy'
    && ($r['reason_code'] ?? '') === ParticipationPolicy::CAPACITY_REACHED);

// La reanudación NUNCA se bloquea por cupo lleno
$ana = ParticipantRepository::findByPersonalCode((int)$familyB['id'], $bCodes['Ana Robles']['code']);
check('Reanudación funciona con cupo lleno', $ana !== null && (int)$ana['id'] === $bCodes['Ana Robles']['id']);

// Con cupo lleno, incluso un duplicado recibe family_full (el mensaje guía a reanudar)
$r = ParticipantRepository::register((int)$familyB['id'], 'ana robles', 'Primera generación', 'Otro rol patrimonial');
check('Duplicado con cupo lleno recibe capacity_reached',
    empty($r['ok']) && ($r['error'] ?? '') === 'policy'
    && ($r['reason_code'] ?? '') === ParticipationPolicy::CAPACITY_REACHED);

// Aumentar el cupo permite un registro más
FamilyRepository::update((int)$familyB['id'], ['expected_participants' => 4]);

// Duplicado con cupo disponible reporta duplicate y NO consume el lugar
$r = ParticipantRepository::register((int)$familyB['id'], 'ana robles', 'Primera generación', 'Otro rol patrimonial');
check('Duplicado reporta duplicate (no consume cupo)', empty($r['ok']) && ($r['error'] ?? '') === 'duplicate');
check('Conteo intacto tras duplicado', count(ParticipantRepository::listByFamily((int)$familyB['id'])) === 3);
$r = ParticipantRepository::register((int)$familyB['id'], 'Sofía Robles', 'Segunda generación', 'Otro rol patrimonial');
check('Aumentar cupo permite nuevo registro', !empty($r['ok']));

// Desactivar el límite permite excedente y lo marca como referencia
FamilyRepository::update((int)$familyB['id'], ['enforce_participant_limit' => 0]);
$r = ParticipantRepository::register((int)$familyB['id'], 'Elena Robles', 'Segunda generación', 'Otro rol patrimonial');
check('Modo referencia permite excedente', !empty($r['ok']) && !empty($r['over_reference']));
$familyB = FamilyRepository::findById((int)$familyB['id']);
$capB = FamilyRepository::capacity($familyB, 5);
check('Capacidad reporta excedido en modo referencia', $capB['state'] === 'excedido' && $capB['mode'] === 'referencia');

// Familia cerrada no acepta registros
FamilyRepository::update((int)$familyB['id'], ['status' => 'cerrada']);
$r = ParticipantRepository::register((int)$familyB['id'], 'Otro Más', 'Primera generación', 'Otro rol patrimonial');
check('Familia cerrada rechaza registro (family_closed)',
    empty($r['ok']) && ($r['error'] ?? '') === 'policy'
    && ($r['reason_code'] ?? '') === ParticipationPolicy::FAMILY_CLOSED);
FamilyRepository::update((int)$familyB['id'], ['status' => 'abierta']);

// expected NULL → sin límite aunque enforce esté activo
[$familyC] = FamilyRepository::create('Familia Cedros', null, null, null, $adminId);
FamilyRepository::update((int)$familyC['id'], ['status' => 'abierta']);
$okAll = true;
for ($i = 1; $i <= 6; $i++) {
    $r = ParticipantRepository::register((int)$familyC['id'], "Persona $i Cedros", 'Primera generación', 'Otro rol patrimonial');
    $okAll = $okAll && !empty($r['ok']);
}
check('Sin expected no existe límite (6 registros)', $okAll);

// 2b-bis. Zona horaria (1.0.2): decisiones en hora local configurada
check('Zona configurada por defecto es America/Mexico_City',
    bvm_configured_timezone()->getName() === 'America/Mexico_City');

// Un día antes de la apertura: cerrado; día de apertura: abierto
check('Cerrado un día antes de la apertura', !bvm_local_date_is_open('2026-08-10', '2026-08-20', '2026-08-09'));
check('Abierto el día de apertura (desde las 00:00 locales)', bvm_local_date_is_open('2026-08-10', '2026-08-20', '2026-08-10'));
// Día de cierre: abierto TODO el día; día siguiente: cerrado
check('Abierto durante todo el día de cierre', bvm_local_date_is_open('2026-08-10', '2026-08-20', '2026-08-20'));
check('Cerrado el día siguiente al cierre', !bvm_local_date_is_open('2026-08-10', '2026-08-20', '2026-08-21'));
// Cambio de año
check('Rango que cruza el año funciona', bvm_local_date_is_open('2026-12-28', '2027-01-05', '2027-01-01'));
check('Cerrado tras el cierre en año nuevo', !bvm_local_date_is_open('2026-12-28', '2027-01-05', '2027-01-06'));
// Fechas NULL no restringen
check('Sin fechas no hay restricción', bvm_local_date_is_open(null, null, '2026-08-10'));

// UTC puede ir un día adelante de México (22:00 en CDMX = 04:00 UTC del día
// siguiente): la fecha local NO debe adelantarse a la de UTC en ese caso.
$utcToday = gmdate('Y-m-d');
$localToday = bvm_local_today();
check('Fecha local nunca va después de la fecha UTC', $localToday <= $utcToday);
$cdmxNow = new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City'));
check('bvm_local_today coincide con America/Mexico_City', $localToday === $cdmxNow->format('Y-m-d'));

// report_date es editorial: se conserva el día tal cual, sin conversión
FamilyRepository::update((int)$familyB['id'], ['report_date' => '2026-01-01']);
$familyB = FamilyRepository::findById((int)$familyB['id']);
$objB = FamilyDataService::buildFamilyObject($familyB);
check('report_date conserva el día (sin conversión de zona)', $objB['reportDate'] === '2026-01-01');

// Timestamps técnicos siguen en UTC
check('bvm_now() persiste en UTC', abs(strtotime(bvm_now() . ' UTC') - time()) < 5);

// 2b-ter. Cookies de sesión aisladas (1.0.2)
check('Sin configurar, la ruta de cookie cae a /', bvm_session_cookie_path() === '/');
$GLOBALS['BVM_CONFIG']['app']['session_cookie_path'] = '/diagnostico-bvm-online-dev';
check('La ruta se normaliza con diagonal final', bvm_session_cookie_path() === '/diagnostico-bvm-online-dev/');
$GLOBALS['BVM_CONFIG']['app']['session_cookie_path'] = 'sin-diagonal-inicial/';
check('Ruta inválida cae a /', bvm_session_cookie_path() === '/');
$GLOBALS['BVM_CONFIG']['app']['session_cookie_path'] = '/diagnostico-bvm-online/';
$sessParams = bvm_session_cookie_params();
check('Cookie HttpOnly + SameSite=Lax + path configurado',
    $sessParams['httponly'] === true && $sessParams['samesite'] === 'Lax'
    && $sessParams['path'] === '/diagnostico-bvm-online/');
$_SERVER['HTTPS'] = 'on';
check('Cookie Secure bajo HTTPS', bvm_session_cookie_params()['secure'] === true);
unset($_SERVER['HTTPS']);
unset($GLOBALS['BVM_CONFIG']['app']['session_cookie_path']);

// 2c. Reanudación indexada por código personal (1.0.2)
$anaRow = ParticipantRepository::findById($bCodes['Ana Robles']['id']);
check('Participante nuevo tiene lookup hash', !empty($anaRow['resume_token_lookup_hash'])
    && $anaRow['resume_token_lookup_hash'] === bvm_resume_token_lookup($bCodes['Ana Robles']['code']));
check('Código incorrecto rechazado', ParticipantRepository::findByPersonalCode((int)$familyB['id'], 'ZZZZ-ZZZZ') === null);

// Participante legado: lookup NULL → fallback acotado + backfill automático
Database::pdo()->exec('UPDATE participants SET resume_token_lookup_hash = NULL WHERE id = ' . $bCodes['Luis Robles']['id']);
$luis = ParticipantRepository::findByPersonalCode((int)$familyB['id'], $bCodes['Luis Robles']['code']);
check('Token legacy (lookup NULL) sigue funcionando', $luis !== null && (int)$luis['id'] === $bCodes['Luis Robles']['id']);
$luisRow = ParticipantRepository::findById($bCodes['Luis Robles']['id']);
check('Tras el acierto legacy se completa el lookup', !empty($luisRow['resume_token_lookup_hash']));

// Regenerar código invalida el anterior y actualiza el lookup
$newCode = ParticipantRepository::regeneratePersonalCode($bCodes['Paula Robles']['id']);
check('Código regenerado funciona', ParticipantRepository::findByPersonalCode((int)$familyB['id'], $newCode) !== null);
check('Código anterior deja de funcionar',
    ParticipantRepository::findByPersonalCode((int)$familyB['id'], $bCodes['Paula Robles']['code']) === null);

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

// ============================================================
// 1.0.2.2 — Política central de participación (Hallazgo 4)
// ============================================================
echo "\n--- Política de participación (fuente única de verdad) ---\n";

[$famPol] = FamilyRepository::create('Familia Politica', 3, null, null, $adminId);
$reload = fn() => FamilyRepository::findById((int)$famPol['id']);

// BORRADOR: nada permitido.
$pol = ParticipationPolicy::forFamily($reload(), 0);
check('Borrador: no registra, no reanuda, no guarda, no finaliza',
    !$pol->canRegisterNewParticipant() && !$pol->canResumeParticipant()
    && !$pol->canSaveResponses() && !$pol->canFinalize());
check('Borrador: motivo family_draft', $pol->reasonCode('register') === ParticipationPolicy::FAMILY_DRAFT);
check('Borrador: mensaje exacto del prompt maestro',
    $pol->message(ParticipationPolicy::FAMILY_DRAFT) === 'Esta aplicación aún no ha sido habilitada por BVM.');

// ABIERTA dentro de fechas: todo permitido.
FamilyRepository::update((int)$famPol['id'], ['status' => 'abierta']);
$pol = ParticipationPolicy::forFamily($reload(), 0);
check('Abierta: todo permitido',
    $pol->canRegisterNewParticipant() && $pol->canResumeParticipant()
    && $pol->canSaveResponses() && $pol->canFinalize());

// ABIERTA con cupo lleno: solo se bloquean los registros NUEVOS.
FamilyRepository::update((int)$famPol['id'], ['enforce_participant_limit' => 1]);
$pol = ParticipationPolicy::forFamily($reload(), 3);
check('Abierta con cupo lleno: no registra pero sí continúa',
    !$pol->canRegisterNewParticipant() && $pol->canResumeParticipant()
    && $pol->canSaveResponses() && $pol->canFinalize());
check('Cupo lleno: motivo capacity_reached',
    $pol->reasonCode('register') === ParticipationPolicy::CAPACITY_REACHED);
check('Cupo lleno: el motivo de continuidad sigue siendo OK',
    $pol->reasonCode('resume') === ParticipationPolicy::OK);
check('Cupo lleno: mensaje exacto',
    $pol->message(ParticipationPolicy::CAPACITY_REACHED) === 'Se alcanzó el número autorizado de participantes.');

// Modo referencia: el número esperado NUNCA bloquea por sí solo.
FamilyRepository::update((int)$famPol['id'], ['enforce_participant_limit' => 0]);
$pol = ParticipationPolicy::forFamily($reload(), 9);
check('Referencia: excedente no bloquea registros', $pol->canRegisterNewParticipant());

// Fechas.
FamilyRepository::update((int)$famPol['id'], [
    'opens_at' => (new DateTimeImmutable('+3 days', bvm_configured_timezone()))->format('Y-m-d'),
    'closes_at' => (new DateTimeImmutable('+9 days', bvm_configured_timezone()))->format('Y-m-d'),
]);
$pol = ParticipationPolicy::forFamily($reload(), 0);
check('Antes de la apertura: motivo not_started y nada permitido',
    $pol->reasonCode('register') === ParticipationPolicy::NOT_STARTED
    && !$pol->canResumeParticipant() && !$pol->canSaveResponses() && !$pol->canFinalize());
FamilyRepository::update((int)$famPol['id'], [
    'opens_at' => (new DateTimeImmutable('-9 days', bvm_configured_timezone()))->format('Y-m-d'),
    'closes_at' => (new DateTimeImmutable('-1 day', bvm_configured_timezone()))->format('Y-m-d'),
]);
$pol = ParticipationPolicy::forFamily($reload(), 0);
check('Después del cierre: motivo ended y nada permitido',
    $pol->reasonCode('register') === ParticipationPolicy::ENDED
    && !$pol->canSaveResponses() && !$pol->canFinalize());
$closesHuman = (new DateTimeImmutable('-1 day', bvm_configured_timezone()))->format('d/m/Y');
check('Mensaje de periodo concluido con la fecha en DD/MM/AAAA',
    $pol->message(ParticipationPolicy::ENDED) === 'El periodo de participación concluyó el ' . $closesHuman . '.');

// Mismo día de apertura y cierre: válido.
$hoy = bvm_local_today();
FamilyRepository::update((int)$famPol['id'], ['opens_at' => $hoy, 'closes_at' => $hoy]);
$pol = ParticipationPolicy::forFamily($reload(), 0);
check('Apertura y cierre el mismo día: participación abierta', $pol->canRegisterNewParticipant());

// CERRADA y ARCHIVADA.
FamilyRepository::update((int)$famPol['id'], ['status' => 'cerrada']);
$pol = ParticipationPolicy::forFamily($reload(), 0);
check('Cerrada: nada permitido y motivo family_closed',
    $pol->reasonCode('register') === ParticipationPolicy::FAMILY_CLOSED
    && !$pol->canResumeParticipant() && !$pol->canSaveResponses() && !$pol->canFinalize());
FamilyRepository::update((int)$famPol['id'], ['status' => 'archivada']);
$pol = ParticipationPolicy::forFamily($reload(), 0);
check('Archivada: nada permitido y motivo family_archived',
    $pol->reasonCode('register') === ParticipationPolicy::FAMILY_ARCHIVED
    && !$pol->canResumeParticipant());

// Reapertura: al volver a Abierta dentro de fechas, se recupera todo.
FamilyRepository::update((int)$famPol['id'], ['status' => 'abierta']);
$pol = ParticipationPolicy::forFamily($reload(), 0);
check('Reapertura: los participantes incompletos pueden continuar',
    $pol->canResumeParticipant() && $pol->canSaveResponses() && $pol->canFinalize());

// ============================================================
// 1.0.2.2 — Archivar conserva / eliminar definitivamente elimina (Hallazgo 8)
// ============================================================
echo "\n--- Archivar vs eliminación definitiva ---\n";

$pdo = Database::pdo();
$countFor = function (int $familyId) use ($pdo): array {
    $q = function (string $sql) use ($pdo, $familyId): int {
        $st = $pdo->prepare($sql);
        $st->execute([$familyId]);
        return (int)$st->fetchColumn();
    };
    return [
        'families' => $q('SELECT COUNT(*) FROM families WHERE id = ?'),
        'participants' => $q('SELECT COUNT(*) FROM participants WHERE family_id = ?'),
        'responses' => $q('SELECT COUNT(*) FROM responses r JOIN participants p ON p.id = r.participant_id WHERE p.family_id = ?'),
        'external' => $q('SELECT COUNT(*) FROM external_responses e JOIN participants p ON p.id = e.participant_id WHERE p.family_id = ?'),
    ];
};

/** Crea una familia abierta con un participante que responde todo. */
$seedFamily = function (string $name) use ($adminId): array {
    [$fam] = FamilyRepository::create($name, 2, null, null, $adminId);
    FamilyRepository::update((int)$fam['id'], ['status' => 'abierta']);
    $reg = ParticipantRepository::register((int)$fam['id'], 'Participante ' . $name, 'Primera generación', 'Otro rol patrimonial');
    $pid = (int)$reg['participant']['id'];
    $rev = 0;
    for ($q = 1; $q <= 20; $q++) {
        $res = ResponseRepository::saveAnswer($pid, $q, ($q % 5) + 1, $rev, $q);
        $rev = (int)$res['revision'];
    }
    foreach (BVM_EXTERNAL_QUESTION_IDS as $ext) {
        $res = ResponseRepository::saveExternalAnswer($pid, $ext, 4, $rev);
        $rev = (int)$res['revision'];
    }
    return [$fam, $pid];
};

[$famArchivar] = $seedFamily('Familia Archivar');
FamilyRepository::archive((int)$famArchivar['id']);
$after = $countFor((int)$famArchivar['id']);
$famArchivar = FamilyRepository::findById((int)$famArchivar['id']);
check('Archivar conserva familia, participantes y respuestas',
    $famArchivar !== null && $famArchivar['status'] === 'archivada'
    && $after['participants'] === 1 && $after['responses'] === 20 && $after['external'] === 2);

[$famBorrar] = $seedFamily('Familia Borrar');
[$famTestigo] = $seedFamily('Familia Testigo');
$before = $countFor((int)$famBorrar['id']);
check('Antes de eliminar existen los datos dependientes',
    $before['participants'] === 1 && $before['responses'] === 20 && $before['external'] === 2);
$deleted = FamilyRepository::hardDelete((int)$famBorrar['id']);
check('hardDelete informa el conteo real eliminado',
    $deleted['participants'] === 1 && $deleted['responses'] === 20 && $deleted['external_responses'] === 2);
$afterDelete = $countFor((int)$famBorrar['id']);
check('Eliminación definitiva: no quedan familia, participantes ni respuestas',
    $afterDelete['families'] === 0 && $afterDelete['participants'] === 0
    && $afterDelete['responses'] === 0 && $afterDelete['external'] === 0);
check('La familia deja de resolverse por id y por liga',
    FamilyRepository::findById((int)$famBorrar['id']) === null
    && FamilyRepository::findBySlug((string)$famBorrar['public_slug']) === null);

// Sin huérfanos en TODA la base (no solo en la familia eliminada).
$orphanParticipants = (int)$pdo->query(
    'SELECT COUNT(*) FROM participants p LEFT JOIN families f ON f.id = p.family_id WHERE f.id IS NULL'
)->fetchColumn();
$orphanResponses = (int)$pdo->query(
    'SELECT COUNT(*) FROM responses r LEFT JOIN participants p ON p.id = r.participant_id WHERE p.id IS NULL'
)->fetchColumn();
$orphanExternal = (int)$pdo->query(
    'SELECT COUNT(*) FROM external_responses e LEFT JOIN participants p ON p.id = e.participant_id WHERE p.id IS NULL'
)->fetchColumn();
check('Sin participaciones ni respuestas huérfanas en la base',
    $orphanParticipants === 0 && $orphanResponses === 0 && $orphanExternal === 0);

$testigo = $countFor((int)$famTestigo['id']);
check('Los datos de otras familias quedan intactos',
    $testigo['families'] === 1 && $testigo['participants'] === 1
    && $testigo['responses'] === 20 && $testigo['external'] === 2);

// Auditoría: solo metadatos no sensibles (nunca respuestas individuales).
$auditRows = $pdo->query("SELECT metadata_json FROM audit_events WHERE event_type = 'family-created'")->fetchAll();
$sinRespuestas = true;
foreach ($auditRows as $row) {
    if (preg_match('/answer|respuesta|"a\\d+"/i', (string)$row['metadata_json'])) {
        $sinRespuestas = false;
    }
}
check('La auditoría no conserva respuestas individuales', $sinRespuestas);

// Limpieza
@unlink($dbPath);
@unlink($configFile);
@rmdir($work);

echo $failures === 0 ? "\nINTEGRACIÓN: TODAS LAS PRUEBAS PASARON\n" : "\nINTEGRACIÓN: $failures FALLAS\n";
exit($failures === 0 ? 0 : 1);
