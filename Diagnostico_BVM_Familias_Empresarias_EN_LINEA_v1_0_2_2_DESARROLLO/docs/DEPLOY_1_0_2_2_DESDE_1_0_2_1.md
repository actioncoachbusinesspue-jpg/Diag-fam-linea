# Actualización de 1.0.2.1 a 1.0.2.2 — procedimiento documentado

**Esta guía se documenta, no se ejecuta desde el proyecto.** Nada en este
repositorio despliega automáticamente a producción. La ejecuta el encargado del
hosting, en el orden indicado, después de la aprobación formal de BVM.

> **v1.0.2.2 NO requiere cambios de esquema.** La base de datos actual de
> producción se conserva EXACTAMENTE como está: mismas 8 tablas, mismas
> columnas, mismos índices. No hay migraciones que aplicar.

---

## 0. Qué cambia y qué no

| | |
|---|---|
| Cambia | Archivos PHP, JavaScript y CSS de la aplicación (lista en `CHANGELOG.md`). |
| Cambia | Comportamiento de interfaz: portada, flujo Borrador→Abierta, mensajes de bloqueo, reporte preliminar. |
| Cambia | «Eliminar definitivamente» ahora borra de verdad los datos dependientes. |
| No cambia | Metodología, cuestionario, fórmulas, umbrales, reporte de 12 páginas, paridad numérica. |
| No cambia | Esquema de base de datos (sin migraciones). |
| No cambia | Familias, participantes, respuestas, ligas ni claves existentes. |
| No cambia | `private/config.php`, `APP_KEY`, usuarios administradores. |

Comportamiento nuevo que conviene comunicar al equipo BVM antes de actualizar:
**al crear una familia, «participantes esperados» queda como referencia**; para
que el número bloquee registros hay que marcar expresamente la casilla. Las
familias YA existentes conservan su configuración actual sin ningún cambio.

---

## 1. Respaldo SQL de producción

Desde hPanel → Bases de datos → phpMyAdmin → Exportar (método rápido, SQL):

    diag_bvm_online_PROD_AAAA-MM-DD.sql

Descárguelo y verifique que el archivo no está vacío y que contiene
`CREATE TABLE families`. Sin este respaldo, **no continúe**.

## 2. Respaldo de los archivos de la versión 1.0.2.1

Desde el Administrador de archivos, comprima la carpeta publicada actual:

    diagnostico-bvm-online_1.0.2.1_AAAA-MM-DD.zip

Descárguelo. Este archivo es el que permite volver atrás en minutos
(ver `docs/ROLLBACK_1_0_2_2.md`).

## 3. Confirmar el hash del paquete recibido

Antes de subir nada, compruebe que el ZIP entregado es íntegro:

    sha256sum Diagnostico_BVM_Familias_Empresarias_EN_LINEA_v1_0_2_2.zip

El valor debe coincidir con el registrado en `MANIFEST_SHA256.txt` de la
entrega. Si no coincide, solicite el paquete de nuevo.

## 4. Instalar primero en DESARROLLO

Publique en `public_html/diagnostico-bvm-online-dev/` siguiendo
`DEPLOY_HOSTINGER.md` (estructura A o B, la misma que ya usa producción).

El paquete incluye la plantilla **`private/config.dev.example.php`** ya
preparada para este ambiente: cópiela como `private/config.php` DENTRO de la
instalación dev y complete solo las credenciales. Trae fijados:

- base de datos **dev separada** y usuario dev separado (complételos);
- `APP_KEY` propia de dev (distinta de la de producción);
- `session_name` = `BVMDEVSESSID`;
- `session_cookie_path` = `/diagnostico-bvm-online-dev/`;
- `base_url` del ambiente dev;
- `env` en `production`, para que ningún error PHP se muestre en pantalla.

Añada además, en el `.htaccess` de la carpeta dev, la cabecera

    Header set X-Robots-Tag "noindex, nofollow"

y use **exclusivamente datos ficticios**: no copie familias reales al ambiente
de pruebas.

Pruebe en dev, además del recorrido funcional: estructura A (y B si el hosting
la necesita), health-check, HTTPS, login, creación de familia, participación,
reanudación, reporte y que `/private/`, `/private/config.php`, `/database/` y
`/tools/` respondan 403/404 por URL directa.

Ejecute el health-check por CLI o desde `admin/salud.php`. La primera línea debe
decir:

    [OK   ] app_version   Diagnóstico BVM en línea 1.0.2.2

## 5. Completar el QA de aceptación en desarrollo

Recorra `docs/QA_V1_0_2_2.md` §«QA manual» con la familia ficticia
«Familia QA 1022». No continúe con producción si alguno de los seis casos falla.

## 6. Confirmar que no hay migración

Para esta versión: **no hay migración**. Verifique que el health-check reporte

    [OK   ] db_tables        Las 8 tablas existen
    [OK   ] schema_columns_102 / schema_index_102

y NO ejecute ningún archivo de `database/migrations/`.

## 7. Ventana de actualización

Elija una franja sin participación activa (por ejemplo, temprano por la mañana).
La sustitución de archivos toma minutos, pero un participante a media respuesta
podría recibir un error momentáneo.

Opcional, si BVM lo prefiere: cambie temporalmente a **Cerrada** las familias
activas y vuelva a abrirlas al terminar. La reapertura permite continuar a
quienes tenían respuestas incompletas.

## 8. Sustituir el código

Suba y descomprima el paquete 1.0.2.2 sobre la instalación de producción,
reemplazando los archivos de la aplicación:

- contenido de `public/` (incluye `admin/`, `api/`, `assets/`, `index.php`,
  `participar.php`, `bvm_paths.php`, …);
- carpeta `private/` **excepto `private/config.php`**;
- carpeta `tools/`;
- `database/` y `docs/` (documentación; no se ejecutan solos).

Elimine de la instalación los archivos que ya no existen en 1.0.2.2 solo si el
paquete lo indica; esta versión no elimina ninguno.

## 9. NO sustituir `private/config.php`

El `config.php` de producción contiene credenciales, `APP_KEY`, `base_url`,
`session_name` y `session_cookie_path` propios. **Consérvelo tal cual.**
El paquete incluye únicamente `config.example.php`.

## 10. NO cambiar `APP_KEY`

`APP_KEY` participa en el HMAC de localización de los códigos personales de
continuidad. Si cambia, los participantes **no podrán reanudar** con su código.

## 11. NO restablecer usuarios administradores

No ejecute `instalar.php` en producción: ya está bloqueado y los usuarios
existentes se conservan. No modifique contraseñas.

## 12. NO borrar familias existentes

La actualización no toca ninguna fila de `families`, `participants`,
`responses`, `external_responses` ni `audit_events`.

## 13. NO regenerar slugs ni claves existentes

Las ligas (`public_slug`) y las claves de familia se conservan: las invitaciones
ya enviadas siguen funcionando.

## 14. Health-check

Ejecute de nuevo el health-check en producción. Esperado: `RESULTADO: TODO
CORRECTO` (o «correcto con advertencias» si el hosting no permite alguna
comprobación de permisos). Debe mostrar la versión 1.0.2.2 y el token de
instalación inactivo.

## 15. Smoke test en producción (10 minutos)

1. Portada carga; «Ya recibí una invitación» abre el panel y **no** navega a una
   pantalla de error.
2. `participar.php` sin liga muestra la guía, no un error seco.
3. Demostración abre y muestra la Familia Horizonte.
4. Acceso BVM: iniciar sesión y listar familias.
5. Abrir una familia existente: la liga, el estado y el avance son los de
   siempre; el estado se muestra como etiqueta primaria.
6. Abrir una liga real existente y comprobar que pide la clave (sin registrarse).
7. Abrir el reporte de una familia con participaciones finalizadas y comprobar
   que imprime 12 páginas.
8. `/private/`, `/private/config.php`, `/database/` y `/tools/` responden
   403/404 por URL directa.

## 16. Rollback inmediato si algo falla

No intente reparar en caliente: aplique `docs/ROLLBACK_1_0_2_2.md`. Como esta
versión no cambia el esquema, restaurar los archivos de 1.0.2.1 basta para
volver al estado anterior sin tocar la base de datos.

---

## Resumen para el encargado del hosting

1. Respaldo SQL + respaldo de archivos.
2. Verificar hash del ZIP.
3. Publicar en `-dev`, correr health-check y QA.
4. Con la aprobación de BVM: sustituir el código en producción **sin tocar
   `config.php`, `APP_KEY`, usuarios ni datos**.
5. Health-check + smoke test.
6. Si algo falla: rollback de archivos. La base no se toca en ningún paso.
