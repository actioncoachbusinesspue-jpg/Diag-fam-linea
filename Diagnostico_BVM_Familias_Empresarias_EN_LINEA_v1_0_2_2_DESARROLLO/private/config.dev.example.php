<?php
/**
 * Configuración de DESARROLLO/STAGING — Diagnóstico BVM en línea 1.0.2.2.
 *
 * Plantilla lista para la instalación de pruebas en
 *     public_html/diagnostico-bvm-online-dev/
 * Cópiela como private/config.php DENTRO DE ESA INSTALACIÓN (nunca en producción)
 * y complete únicamente las credenciales.
 *
 * Reglas del ambiente de desarrollo:
 *   · Base de datos y usuario SEPARADOS de producción (nunca la base real).
 *   · Cookie de sesión propia (BVMDEVSESSID + ruta dev): trabajar en dev no
 *     cierra la sesión de producción ni al revés.
 *   · Datos exclusivamente ficticios. No copie familias reales aquí.
 *   · `env` se mantiene en 'production' para que ningún error PHP se muestre
 *     en pantalla, igual que en el ambiente real.
 *
 * Complemento recomendado: añada en el .htaccess de la carpeta dev
 *
 *     Header set X-Robots-Tag "noindex, nofollow"
 *
 * para que el ambiente de pruebas no se indexe.
 */
return [
    'db' => [
        'driver'      => 'mysql',
        'host'        => 'localhost',
        'name'        => 'NOMBRE_DE_LA_BASE_DEV',
        'user'        => 'USUARIO_DE_LA_BASE_DEV',
        'pass'        => 'CONTRASENA_DE_LA_BASE_DEV',
        'charset'     => 'utf8mb4',
        'sqlite_path' => null,
    ],
    'app' => [
        // APP_KEY PROPIA del ambiente dev (distinta de la de producción):
        //   php -r "echo bin2hex(random_bytes(32));"
        'key'                      => 'REEMPLACE_CON_APP_KEY_DEV_64_HEX',
        'env'                      => 'production',
        'base_url'                 => 'https://businessvaluementor.com/diagnostico-bvm-online-dev',
        'timezone'                 => 'America/Mexico_City',
        'session_name'             => 'BVMDEVSESSID',
        'session_cookie_path'      => '/diagnostico-bvm-online-dev/',
        'session_lifetime_minutes' => 45,
        // Token temporal SOLO para crear el primer administrador de dev.
        // Vacíelo en cuanto el instalador quede bloqueado.
        'install_token'            => 'REEMPLACE_CON_TOKEN_TEMPORAL_DEV',
    ],
    'security' => [
        'max_login_attempts' => 5,
        'lockout_minutes'    => 15,
        'family_access_random_length' => 6,
    ],
];
