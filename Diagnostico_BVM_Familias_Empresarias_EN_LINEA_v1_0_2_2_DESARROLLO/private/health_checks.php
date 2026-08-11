<?php
declare(strict_types=1);

/**
 * health_checks.php — verificaciones de instalación (versión 1.0.2.2).
 *
 * Biblioteca compartida por:
 *   - tools/health-check.php   (CLI: php tools/health-check.php)
 *   - public/admin/salud.php   (ruta administrativa protegida por sesión)
 *
 * Cada verificación devuelve: ['status' => 'ok'|'warn'|'fail', 'detail' => ...]
 * Nunca expone credenciales, rutas privadas completas, SQL ni stack traces.
 */

const BVM_HEALTH_SCHEMA_TABLES = [
    'admin_users', 'families', 'participants', 'responses',
    'external_responses', 'audit_events', 'login_attempts', 'family_assignments',
];

function bvm_health_run(): array
{
    $checks = [];
    $env = (string)bvm_config('app.env', 'production');
    $isProduction = $env === 'production';

    // 0. Versión instalada: permite confirmar en segundos qué código está
    //    publicado tras una actualización (1.0.2.2).
    $checks['app_version'] = [
        'status' => 'ok',
        'detail' => 'Diagnóstico BVM en línea ' . (defined('BVM_APP_VERSION') ? BVM_APP_VERSION : 'sin versión'),
    ];

    // 1. PHP mínimo
    $checks['php_version'] = [
        'status' => version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'fail',
        'detail' => 'PHP ' . PHP_VERSION . ' (mínimo requerido: 8.1)',
    ];

    // 2. Extensión PDO
    $checks['pdo_extension'] = [
        'status' => extension_loaded('pdo') ? 'ok' : 'fail',
        'detail' => extension_loaded('pdo') ? 'Extensión PDO disponible' : 'Falta la extensión PDO',
    ];

    // 3. Driver MySQL en producción
    $driver = (string)bvm_config('db.driver', 'mysql');
    if ($isProduction) {
        $mysqlOk = $driver === 'mysql' && extension_loaded('pdo_mysql');
        $checks['mysql_driver'] = [
            'status' => $mysqlOk ? 'ok' : 'fail',
            'detail' => $mysqlOk
                ? 'Driver MySQL/MariaDB configurado y pdo_mysql cargado'
                : ($driver !== 'mysql'
                    ? "En producción db.driver debe ser 'mysql' (actual: $driver)"
                    : 'Falta la extensión pdo_mysql'),
        ];
    } else {
        $checks['mysql_driver'] = [
            'status' => $driver === 'mysql' ? 'ok' : 'warn',
            'detail' => "Ambiente $env con driver $driver"
                . ($driver !== 'mysql' ? ' (SQLite solo es válido para pruebas locales)' : ''),
        ];
    }

    // 4-9. Base de datos: conexión, tablas, columnas, índices, motor, charset
    $pdo = null;
    try {
        $pdo = Database::pdo();
        $checks['db_connection'] = [
            'status' => 'ok',
            'detail' => 'Conexión establecida (' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . ')',
        ];
    } catch (Throwable $e) {
        $checks['db_connection'] = [
            'status' => 'fail',
            'detail' => 'Sin conexión a la base de datos: revise las credenciales en private/config.php',
        ];
    }

    if ($pdo !== null) {
        $isMysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

        // 5. Las ocho tablas
        $missing = [];
        foreach (BVM_HEALTH_SCHEMA_TABLES as $t) {
            try {
                $pdo->query("SELECT 1 FROM $t LIMIT 1");
            } catch (Throwable $e) {
                $missing[] = $t;
            }
        }
        $checks['db_tables'] = [
            'status' => $missing ? 'fail' : 'ok',
            'detail' => $missing
                ? 'Faltan tablas: ' . implode(', ', $missing) . ' — importe database/schema.sql o aplique las migraciones'
                : 'Las 8 tablas existen (incluida family_assignments)',
        ];

        // 6. Columnas nuevas de 1.0.2
        $missingCols = [];
        foreach ([['families', 'enforce_participant_limit'], ['participants', 'resume_token_lookup_hash']] as [$table, $col]) {
            try {
                $pdo->query("SELECT $col FROM $table LIMIT 1");
            } catch (Throwable $e) {
                $missingCols[] = "$table.$col";
            }
        }
        $checks['schema_columns_102'] = [
            'status' => $missingCols ? 'fail' : 'ok',
            'detail' => $missingCols
                ? 'Faltan columnas 1.0.2: ' . implode(', ', $missingCols) . ' — aplique migraciones 0003 y 0004'
                : 'Columnas 1.0.2 presentes (enforce_participant_limit, resume_token_lookup_hash)',
        ];

        // 7. Índice nuevo de 1.0.2
        $indexOk = false;
        try {
            if ($isMysql) {
                $st = $pdo->query("SHOW INDEX FROM participants WHERE Key_name = 'uq_resume_lookup_per_family'");
                $indexOk = $st !== false && $st->fetch() !== false;
            } else {
                $st = $pdo->query("PRAGMA index_list('participants')");
                foreach ($st->fetchAll() as $row) {
                    $name = $row['name'] ?? '';
                    if ((int)($row['unique'] ?? 0) === 1) {
                        $info = $pdo->query("PRAGMA index_info('" . $name . "')")->fetchAll();
                        $cols = array_map(fn($r) => $r['name'] ?? '', $info);
                        if (in_array('resume_token_lookup_hash', $cols, true)) {
                            $indexOk = true;
                            break;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $indexOk = false;
        }
        $checks['schema_index_102'] = [
            'status' => $indexOk ? 'ok' : 'fail',
            'detail' => $indexOk
                ? 'Índice único de reanudación presente (family_id, resume_token_lookup_hash)'
                : 'Falta el índice uq_resume_lookup_per_family — aplique la migración 0004',
        ];

        // 8-9. InnoDB y utf8mb4 (solo aplican a MySQL/MariaDB)
        if ($isMysql) {
            try {
                $st = $pdo->prepare(
                    'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' .
                    implode(',', array_fill(0, count(BVM_HEALTH_SCHEMA_TABLES), '?')) . ')'
                );
                $st->execute(BVM_HEALTH_SCHEMA_TABLES);
                $badEngine = [];
                $badCharset = [];
                foreach ($st->fetchAll() as $row) {
                    if (strcasecmp((string)$row['ENGINE'], 'InnoDB') !== 0) {
                        $badEngine[] = $row['TABLE_NAME'];
                    }
                    if (stripos((string)$row['TABLE_COLLATION'], 'utf8mb4') !== 0) {
                        $badCharset[] = $row['TABLE_NAME'];
                    }
                }
                $checks['db_engine'] = [
                    'status' => $badEngine ? 'fail' : 'ok',
                    'detail' => $badEngine ? 'Tablas sin InnoDB: ' . implode(', ', $badEngine) : 'Todas las tablas usan InnoDB',
                ];
                $checks['db_charset'] = [
                    'status' => $badCharset ? 'fail' : 'ok',
                    'detail' => $badCharset ? 'Tablas sin utf8mb4: ' . implode(', ', $badCharset) : 'Todas las tablas usan utf8mb4',
                ];
            } catch (Throwable $e) {
                $checks['db_engine'] = ['status' => 'warn', 'detail' => 'No fue posible verificar el motor de tablas'];
                $checks['db_charset'] = ['status' => 'warn', 'detail' => 'No fue posible verificar el charset'];
            }
        } else {
            $checks['db_engine'] = ['status' => 'warn', 'detail' => 'Motor no MySQL: InnoDB no aplica (solo pruebas locales)'];
            $checks['db_charset'] = ['status' => 'warn', 'detail' => 'Motor no MySQL: utf8mb4 no aplica (solo pruebas locales)'];
        }

        // 20. Existencia de administrador
        try {
            $admins = AdminUserRepository::count();
            $checks['admin_account'] = [
                'status' => $admins > 0 ? 'ok' : 'fail',
                'detail' => $admins > 0
                    ? "Administradores registrados: $admins (instalador bloqueado)"
                    : 'Sin administradores — ejecute instalar.php',
            ];
        } catch (Throwable $e) {
            $checks['admin_account'] = ['status' => 'fail', 'detail' => 'No fue posible consultar admin_users'];
        }
    }

    // 10. APP_KEY configurada y con longitud suficiente
    $appKey = (string)bvm_config('app.key', '');
    if ($appKey === '' || str_starts_with($appKey, 'REEMPLACE')) {
        $checks['app_key'] = ['status' => 'fail', 'detail' => 'APP_KEY sin configurar'];
    } elseif (strlen($appKey) < 32) {
        $checks['app_key'] = ['status' => 'fail', 'detail' => 'APP_KEY demasiado corta (mínimo 32 caracteres; use 64 hex)'];
    } else {
        $checks['app_key'] = ['status' => 'ok', 'detail' => 'APP_KEY configurada con longitud suficiente'];
    }

    // 11. Zona horaria válida (si fuera inválida, bootstrap ya habría detenido la app)
    $tzName = (string)bvm_config('app.timezone', 'America/Mexico_City');
    $tzValid = in_array($tzName, DateTimeZone::listIdentifiers(), true);
    $checks['timezone'] = [
        'status' => $tzValid ? 'ok' : 'fail',
        'detail' => $tzValid ? "Zona horaria: $tzName" : "Zona horaria inválida: $tzName",
    ];

    // 12. base_url
    $baseUrl = trim((string)bvm_config('app.base_url', ''));
    if ($baseUrl === '') {
        $checks['base_url'] = [
            'status' => $isProduction ? 'fail' : 'warn',
            'detail' => 'base_url vacía (en producción debe configurarse explícitamente)',
        ];
    } else {
        $host = (string)(parse_url($baseUrl, PHP_URL_HOST) ?? '');
        $scheme = (string)(parse_url($baseUrl, PHP_URL_SCHEME) ?? '');
        $isLocal = in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
        if ($isLocal && $isProduction) {
            $checks['base_url'] = ['status' => 'fail', 'detail' => 'base_url apunta a localhost en producción'];
        } elseif ($isProduction && $scheme !== 'https') {
            $checks['base_url'] = ['status' => 'fail', 'detail' => 'base_url debe usar HTTPS en producción'];
        } else {
            $checks['base_url'] = [
                'status' => $isLocal ? 'warn' : 'ok',
                'detail' => 'base_url configurada' . ($isLocal ? ' (localhost: solo válido en desarrollo)' : ' con ' . $scheme),
            ];
        }
    }

    // 13. session_name
    $sessionName = (string)bvm_config('app.session_name', '');
    $checks['session_name'] = [
        'status' => ($sessionName !== '' && preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $sessionName)) ? 'ok' : 'fail',
        'detail' => $sessionName !== '' ? "session_name: $sessionName" : 'session_name vacío',
    ];

    // 14. session_cookie_path (y coherencia con base_url en producción)
    $rawPath = (string)bvm_config('app.session_cookie_path', '/');
    $effectivePath = bvm_session_cookie_path();
    if ($rawPath !== '' && ($rawPath[0] !== '/' )) {
        $checks['session_cookie_path'] = [
            'status' => 'warn',
            'detail' => "session_cookie_path «$rawPath» inválida; se usará '/' (la cookie será visible en todo el dominio)",
        ];
    } else {
        $status = 'ok';
        $detail = "Ruta de cookie: $effectivePath";
        if ($isProduction && $baseUrl !== '') {
            $basePath = rtrim((string)(parse_url($baseUrl, PHP_URL_PATH) ?? ''), '/') . '/';
            if ($effectivePath === '/') {
                $status = 'warn';
                $detail .= ' (en producción se recomienda limitarla a la carpeta de la instalación, ej. ' . $basePath . ')';
            } elseif ($basePath !== '/' && $effectivePath !== $basePath) {
                $status = 'warn';
                $detail .= " — no coincide con la ruta de base_url ($basePath)";
            }
        }
        $checks['session_cookie_path'] = ['status' => $status, 'detail' => $detail];
    }

    // 15. install_token eliminado
    $token = (string)bvm_config('app.install_token', '');
    $tokenInactive = $token === '' || str_starts_with($token, 'REEMPLACE');
    $checks['install_token_removed'] = [
        'status' => $tokenInactive ? 'ok' : 'fail',
        'detail' => $tokenInactive
            ? 'Token de instalación no activo'
            : 'El token de instalación sigue en config.php — elimínelo tras instalar',
    ];

    // 16. Motor de referencia presente
    $refApp = BVM_PRIVATE_DIR . '/reference-app/referencia_app.html';
    $checks['reference_app'] = [
        'status' => is_file($refApp) ? 'ok' : 'fail',
        'detail' => is_file($refApp) ? 'Motor de referencia disponible' : 'Falta private/reference-app/referencia_app.html',
    ];

    // 17-18. private/ y database/ no accesibles públicamente.
    // Desde el servidor solo puede verificarse la protección declarada; la
    // prueba definitiva es una petición HTTP externa (debe devolver 403/404),
    // documentada en DEPLOY_HOSTINGER.md y la lista de verificación.
    $checks['private_protected'] = bvm_health_dir_protection(BVM_PRIVATE_DIR, 'private/');
    $checks['database_protected'] = bvm_health_dir_protection(BVM_ROOT_DIR . '/database', 'database/');

    // 19. Versión de esquema (derivada de las migraciones aplicadas)
    if ($pdo !== null) {
        $schemaVersion = ($checks['db_tables']['status'] === 'ok')
            ? (($checks['schema_columns_102']['status'] === 'ok' && $checks['schema_index_102']['status'] === 'ok')
                ? '1.0.2 (migraciones 0001–0004)'
                : '1.0.1 o anterior — faltan migraciones 0003/0004')
            : 'incompleta';
        $checks['schema_version'] = [
            'status' => str_starts_with($schemaVersion, '1.0.2') ? 'ok' : 'fail',
            'detail' => 'Esquema: ' . $schemaVersion,
        ];
    }

    // 21. Permisos mínimos: config.php sin escritura universal
    $configFile = getenv('BVM_CONFIG_FILE') ?: (BVM_PRIVATE_DIR . '/config.php');
    if (is_file($configFile)) {
        $perms = fileperms($configFile) & 0o777;
        $worldWritable = ($perms & 0o002) !== 0;
        $checks['config_permissions'] = [
            'status' => $worldWritable ? 'fail' : 'ok',
            'detail' => $worldWritable
                ? 'config.php tiene escritura universal — corrija a 640 o 600'
                : sprintf('Permisos de config.php: %o', $perms),
        ];
    }

    return $checks;
}

/** Protección declarada de una carpeta sensible (17-18). */
function bvm_health_dir_protection(string $dir, string $label): array
{
    if (!is_dir($dir)) {
        return ['status' => 'warn', 'detail' => "La carpeta $label no existe en esta instalación"];
    }
    // Fuera del área pública (carpeta 'public' no es ancestro): protección estructural.
    $publicDir = realpath(BVM_ROOT_DIR . '/public');
    $realDir = realpath($dir);
    if ($publicDir !== false && $realDir !== false && !str_starts_with($realDir . '/', $publicDir . '/')) {
        $ht = is_file($dir . '/.htaccess');
        return [
            'status' => 'ok',
            'detail' => "$label está fuera de la carpeta pública del proyecto"
                . ($ht ? ' y además tiene .htaccess de bloqueo' : '')
                . '. Verifique también por URL directa (debe responder 403/404).',
        ];
    }
    $htaccess = $dir . '/.htaccess';
    if (is_file($htaccess) && stripos((string)@file_get_contents($htaccess), 'Require all denied') !== false) {
        return [
            'status' => 'warn',
            'detail' => "$label depende de .htaccess (Require all denied). Confirme por URL directa que responde 403 y "
                . 'considere moverla fuera de public_html (arquitectura recomendada).',
        ];
    }
    return [
        'status' => 'fail',
        'detail' => "$label no tiene protección verificable (.htaccess ausente o sin 'Require all denied')",
    ];
}

/** Resumen global: FALLA si hay cualquier 'fail'; ADVERTENCIA si solo hay 'warn'. */
function bvm_health_overall(array $checks): string
{
    $overall = 'ok';
    foreach ($checks as $c) {
        if ($c['status'] === 'fail') {
            return 'fail';
        }
        if ($c['status'] === 'warn') {
            $overall = 'warn';
        }
    }
    return $overall;
}
