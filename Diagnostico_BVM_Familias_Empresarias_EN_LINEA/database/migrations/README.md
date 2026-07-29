# Migraciones de base de datos

Política pensada para hosting compartido (Hostinger/phpMyAdmin), sin runner
de migraciones:

1. Los archivos se numeran `NNNN_descripcion.sql` y se aplican **en orden**
   desde phpMyAdmin (o `mysql < archivo.sql`).
2. `0001_esquema_inicial.sql` es idéntico a `database/schema.sql`. Una
   instalación nueva importa cualquiera de los dos (no ambos).
3. Cada cambio futuro del esquema se entrega como una migración nueva
   (`0002_...`, `0003_...`) que transforma el esquema anterior sin perder
   datos, junto con la actualización de `schema.sql` al estado final.
4. Antes de aplicar una migración en producción: hacer respaldo de la base
   desde hPanel (ver DEPLOY_HOSTINGER.md) y probar primero en el ambiente
   `diagnostico-bvm-online-dev/`.
5. Registrar en `CHANGELOG.md` qué migraciones incluye cada versión.

## Historial

| Migración | Versión | Descripción |
|---|---|---|
| 0001_esquema_inicial.sql | 1.0.0 | Esquema completo inicial (admin_users, families, participants, responses, external_responses, audit_events, login_attempts) |
| 0002_family_assignments.sql | 1.0.1 | Tabla de asignación de familias a consultores (modelo de roles preparado; el MVP aún no la usa) |

## Nota sobre seed_demo.sql

No existe un `seed_demo.sql` **a propósito**: la demostración Familia
Horizonte nunca vive en la base de datos. Sus datos ficticios están dentro
del motor de referencia y corren solo en la memoria del navegador, lo que
garantiza el aislamiento exigido (la demo no puede tocar ni contaminar datos
reales). Sembrar la demo en MySQL rompería esa garantía.
