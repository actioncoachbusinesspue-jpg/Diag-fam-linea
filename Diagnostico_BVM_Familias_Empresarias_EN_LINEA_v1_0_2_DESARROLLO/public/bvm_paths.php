<?php
/**
 * bvm_paths.php — localizador ÚNICO de la carpeta privada (versión 1.0.2.1).
 *
 * Todos los puntos de entrada públicos requieren ESTE archivo (por una ruta
 * relativa que solo depende de la estructura interna de la carpeta pública,
 * idéntica en cualquier despliegue) y este archivo resuelve dónde vive
 * private/bootstrap.php. Soporta las dos estructuras documentadas en
 * DEPLOY_HOSTINGER.md sin editar ninguna otra ruta:
 *
 *   ESTRUCTURA A (recomendada) — public/ como raíz del documento:
 *       proyecto/
 *         ├── public/    ← este archivo vive aquí (document root)
 *         └── private/   ← un nivel ARRIBA de esta carpeta
 *
 *   ESTRUCTURA B (subcarpeta estándar de public_html, sin document root
 *   personalizado) — el CONTENIDO de public/ se copia a la carpeta de la
 *   instalación y private/ queda dentro de esa misma carpeta:
 *       public_html/diagnostico-bvm-online/
 *         ├── index.php, admin/, api/, assets/, bvm_paths.php …
 *         ├── private/   ← hermana de este archivo (protegida por .htaccess)
 *         └── tools/
 *
 * También puede fijarse explícitamente con la variable de entorno
 * BVM_PRIVATE_DIR (por ejemplo vía SetEnv en .htaccess) si una instalación
 * necesita una ubicación distinta a las dos anteriores.
 */
declare(strict_types=1);

// Nunca servir este archivo directamente por URL.
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}

(function (): void {
    $candidates = [];
    $env = getenv('BVM_PRIVATE_DIR');
    if (is_string($env) && $env !== '') {
        $candidates[] = rtrim($env, '/');
    }
    $candidates[] = dirname(__DIR__) . '/private'; // Estructura A (recomendada)
    $candidates[] = __DIR__ . '/private';          // Estructura B (subcarpeta estándar)

    foreach ($candidates as $dir) {
        if (is_file($dir . '/bootstrap.php')) {
            require_once $dir . '/bootstrap.php';
            return;
        }
    }
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Instalación incompleta: no se encontró private/bootstrap.php.\n";
    echo "Revise la estructura de carpetas en DEPLOY_HOSTINGER.md (sección 1).\n";
    exit;
})();
