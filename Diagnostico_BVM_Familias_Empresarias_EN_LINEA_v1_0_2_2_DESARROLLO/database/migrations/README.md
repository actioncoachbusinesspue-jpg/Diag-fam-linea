# Migraciones de base de datos

Política pensada para hosting compartido (Hostinger/phpMyAdmin), sin runner
de migraciones:

1. Los archivos se numeran `NNNN_descripcion.sql` y se aplican **en orden**
   desde phpMyAdmin (o `mysql < archivo.sql`).
2. Una instalación NUEVA importa únicamente `database/schema.sql` (estado
   final, ya incluye 0003 y 0004). Una instalación EXISTENTE aplica solo las
   migraciones que le falten, en orden. Nunca ambos caminos a la vez.
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
| 0003_enforce_participant_limit.sql | 1.0.2 | `families.enforce_participant_limit`: el número esperado puede actuar como límite real de registros o solo como referencia |
| 0004_resume_token_lookup.sql | 1.0.2 | `participants.resume_token_lookup_hash` + índice único por familia: reanudación por código personal vía índice (con fallback para datos previos) |

## Nota sobre seed_demo.sql

No existe un `seed_demo.sql` **a propósito**: la demostración Familia
Horizonte nunca vive en la base de datos. Sus datos ficticios están dentro
del motor de referencia y corren solo en la memoria del navegador, lo que
garantiza el aislamiento exigido (la demo no puede tocar ni contaminar datos
reales). Sembrar la demo en MySQL rompería esa garantía.
