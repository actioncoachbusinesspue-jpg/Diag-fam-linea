<?php
/**
 * Configuración privada — Diagnóstico BVM para Familias Empresarias (versión en línea).
 *
 * INSTRUCCIONES:
 *  1. Copie este archivo como config.php EN ESTA MISMA CARPETA (private/), fuera de public_html
 *     o protegido por .htaccess.
 *  2. Complete las credenciales de la base de datos creada en hPanel.
 *  3. Genere una APP_KEY segura (por ejemplo: php -r "echo bin2hex(random_bytes(32));").
 *  4. Defina un INSTALL_TOKEN temporal para crear el primer administrador y bórrelo después.
 *
 * NUNCA suba config.php a un repositorio ni lo deje accesible públicamente.
 */
return [
    'db' => [
        // 'mysql' en producción (Hostinger). 'sqlite' solo para pruebas locales.
        'driver'      => 'mysql',
        'host'        => 'localhost',
        'name'        => 'NOMBRE_DE_LA_BASE',
        'user'        => 'USUARIO_DE_LA_BASE',
        'pass'        => 'CONTRASENA_DE_LA_BASE',
        'charset'     => 'utf8mb4',
        // Solo si driver = sqlite (pruebas locales):
        'sqlite_path' => null,
    ],
    'app' => [
        // Clave interna para HMAC de datos minimizados (IP, identificadores).
        'key'                      => 'REEMPLACE_CON_APP_KEY_SEGURA_64_HEX',
        // 'production' oculta errores al usuario; 'development' los muestra.
        'env'                      => 'production',
        // URL base pública SIN diagonal final, ej: https://sudominio.com/diagnostico-bvm-online
        'base_url'                 => '',
        // Zona horaria para decisiones de apertura/cierre y presentación
        // administrativa (identificador IANA). Los timestamps técnicos se
        // guardan siempre en UTC. Si el valor es inválido, la aplicación NO
        // inicia (falla explícita, nunca silenciosa).
        'timezone'                 => 'America/Mexico_City',
        'session_name'             => 'BVMSESSID',
        // Expiración de sesión administrativa por inactividad.
        'session_lifetime_minutes' => 45,
        // Token de instalación: requerido por instalar.php para crear el PRIMER administrador.
        // El instalador queda bloqueado automáticamente cuando ya existe un usuario.
        'install_token'            => 'REEMPLACE_CON_TOKEN_TEMPORAL',
    ],
    'security' => [
        'max_login_attempts' => 5,
        'lockout_minutes'    => 15,
        // Caracteres aleatorios de la clave de familia (PREFIJO-XXXXXX).
        // Rango permitido: 6 a 10. Cualquier valor inválido cae a 6.
        // Las claves de 4 caracteres emitidas antes de 1.0.2 siguen funcionando.
        'family_access_random_length' => 6,
    ],
];
