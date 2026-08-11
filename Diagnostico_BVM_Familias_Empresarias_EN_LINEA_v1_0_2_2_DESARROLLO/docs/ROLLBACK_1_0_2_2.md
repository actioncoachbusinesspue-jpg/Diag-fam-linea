# Rollback de 1.0.2.2 a 1.0.2.1

Procedimiento de vuelta atrás. **Objetivo: restaurar el servicio en minutos,
sin pérdida de datos.**

## Punto clave

v1.0.2.2 **no cambia el esquema de la base de datos**. Todo lo que hace es
sustituir archivos de aplicación. Por lo tanto:

> El rollback consiste en restaurar los ARCHIVOS de 1.0.2.1.
> **La base de datos NO se restaura y NO se toca.**

Restaurar el respaldo SQL solo sería necesario si, ya con 1.0.2.2 en
producción, alguien hubiera ejecutado una **eliminación definitiva** de una
familia y quisiera recuperarla (esa acción sí borra datos, por diseño y con
doble confirmación). Ese caso se trata aparte, en la sección 4.

---

## 1. Cuándo hacer rollback

Aplíquelo si, después de actualizar, ocurre cualquiera de estas situaciones y
no se resuelve en la propia ventana de actualización:

- la portada, `participar.php`, `acceso-bvm.php` o `admin/` no cargan;
- el health-check reporta `FALLA` en base de datos, tablas o motor de referencia;
- los participantes no pueden registrarse ni continuar en familias que estaban
  Abiertas y dentro de fechas;
- el reporte no abre o no imprime 12 páginas;
- aparecen errores PHP visibles en pantalla.

## 2. Rollback de archivos (5–10 minutos)

1. Descomprima el respaldo `diagnostico-bvm-online_1.0.2.1_AAAA-MM-DD.zip`
   generado en el paso 2 del despliegue.
2. Sustituya el contenido de la carpeta publicada por el del respaldo.
3. **Conserve `private/config.php` de producción** (es el mismo archivo en
   ambas versiones; no lo sobrescriba con ninguna copia de ejemplo).
4. Verifique permisos: carpetas 755, archivos 644, `private/config.php` 640
   o 644 (nunca escribible por todos).
5. Ejecute el health-check. La versión reportada volverá a ser la de 1.0.2.1
   (esa versión no imprime línea `app_version`: su ausencia confirma el
   rollback).
6. Smoke test: portada, demostración, login BVM, una liga real, un reporte.

## 3. Qué NO hacer durante el rollback

- **No** ejecutar `instalar.php`.
- **No** cambiar `APP_KEY` (rompería los códigos personales de continuidad).
- **No** importar el respaldo SQL «por si acaso»: revertiría respuestas
  legítimas capturadas después del despliegue.
- **No** regenerar slugs ni claves de familia.
- **No** borrar la carpeta `private/` completa: contiene `config.php`.

## 4. Caso especial: recuperar una familia eliminada definitivamente

Solo aplica si se usó «Eliminar familia definitivamente» y se quiere recuperar
esa familia. La acción es destructiva por diseño y no tiene deshacer en la
aplicación.

1. **No** restaure el respaldo completo sobre la base viva: perdería el trabajo
   posterior de las demás familias.
2. Restaure el respaldo SQL en una base **temporal** (por ejemplo
   `diag_bvm_restore`).
3. Extraiga de esa base únicamente las filas de la familia afectada
   (`families`, `participants`, `responses`, `external_responses`) y
   reinsértelas en la base de producción, respetando los identificadores
   dependientes.
4. Verifique con el health-check y con el detalle de la familia en
   administración que los conteos coinciden.

Este trabajo lo realiza el encargado de la base de datos, nunca la aplicación.

## 5. Después del rollback

1. Avise a BVM que la versión publicada volvió a ser 1.0.2.1.
2. Envíe el registro de lo ocurrido: qué falló, en qué paso y qué mostró el
   health-check.
3. No repita el despliegue hasta que el equipo de desarrollo confirme la
   corrección y entregue un paquete nuevo con su propio hash.
