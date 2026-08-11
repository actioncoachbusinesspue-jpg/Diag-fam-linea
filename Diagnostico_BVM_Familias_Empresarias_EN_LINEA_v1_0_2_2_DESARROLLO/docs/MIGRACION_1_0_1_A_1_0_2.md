# Migración 1.0.1 → 1.0.2 — Diagnóstico BVM en línea

Actualización puntual SIN pérdida de datos: conserva usuarios, familias,
participantes, respuestas, resultados, estados, ligas (slugs), claves y
códigos existentes (hashes intactos), reportes y respaldos. Las migraciones
son aditivas (dos columnas y un índice) y reversibles.

Aplique primero en el ambiente de DESARROLLO y, solo tras aprobación,
repita en producción.

## 1. Respaldo de la base de datos (obligatorio)

1. hPanel → phpMyAdmin → seleccionar la base 1.0.1 → **Exportar** →
   formato SQL → guardar como `respaldo_bvm_101_AAAA-MM-DD.sql`.
2. Verifique que el archivo descargado abre y contiene las tablas.

## 2. Respaldo de archivos (obligatorio)

Desde el Administrador de archivos, descargue la carpeta completa de la
instalación 1.0.1 (o comprímala y consérvela con fecha). El ZIP 1.0.1
original se conserva además como punto de restauración.

## 3. Subir el código 1.0.2

1. Suba el contenido de la versión 1.0.2 sobre la instalación (respetando
   la arquitectura elegida en `DEPLOY_HOSTINGER.md` §1).
2. Conserve su `private/config.php` actual (no lo sobrescriba con el
   example) y añádale las claves nuevas tomando como guía
   `private/config.example.php`:
   - `app.timezone` → `'America/Mexico_City'`
   - `app.session_cookie_path` → `'/diagnostico-bvm-online/'` (producción)
     o `'/diagnostico-bvm-online-dev/'` (desarrollo)
   - `security.family_access_random_length` → `6`
   (Si no las añade, la aplicación usa esos mismos valores predeterminados,
   salvo la ruta de cookie, que cae a `/`.)

## 4. Ejecutar las migraciones (en orden, una sola vez)

phpMyAdmin → seleccionar la base → **Importar**:

1. `database/migrations/0003_enforce_participant_limit.sql`
2. `database/migrations/0004_resume_token_lookup.sql`

Notas:

- No importe `database/schema.sql` sobre una base existente.
- Cada archivo debe importarse UNA sola vez; si se re-importa, MySQL
  reporta «Duplicate column name» y no daña los datos.
- Las familias existentes quedan con el límite de cupo ACTIVO
  (comportamiento nuevo predeterminado). Si alguna familia abierta debe
  seguir aceptando registros por encima del número esperado, desactive su
  casilla «Cerrar nuevos registros…» en Administración → familia.
- Los participantes existentes conservan su código personal: la columna
  nueva queda NULL y se completa sola en su siguiente reanudación.

## 5. Health-check

Por terminal:

```
php tools/health-check.php
```

o iniciando sesión BVM y abriendo `admin/salud.php`. Deben aparecer en OK:
las 8 tablas, las columnas 1.0.2, el índice de reanudación y la versión de
esquema «1.0.2 (migraciones 0001–0004)».

## 6. Pruebas posteriores

1. Iniciar sesión BVM; verificar que las familias y avances están intactos.
2. Reanudar una participación existente con su código personal (fallback
   legacy + backfill).
3. Crear una familia de prueba con esperados 2 y límite activo: el tercer
   registro debe rechazarse; eliminarla al terminar.
4. Abrir radiografía y reporte de una familia real: 12 páginas, paridad
   visual intacta.
5. Cerrar sesión y verificar el borrado de la cookie.

## 7. Rollback

1. Restaurar los archivos 1.0.1 desde el respaldo del paso 2 (o el ZIP
   1.0.1).
2. Los datos NO necesitan restauración para volver a 1.0.1: las columnas
   nuevas simplemente no se usan. Si aún así desea revertir el esquema:

   ```sql
   ALTER TABLE participants DROP INDEX uq_resume_lookup_per_family;
   ALTER TABLE participants DROP COLUMN resume_token_lookup_hash;
   ALTER TABLE families DROP COLUMN enforce_participant_limit;
   ```

3. Solo si hubo corrupción de datos: restaurar el SQL del paso 1 desde
   phpMyAdmin (borra los cambios posteriores al respaldo).
