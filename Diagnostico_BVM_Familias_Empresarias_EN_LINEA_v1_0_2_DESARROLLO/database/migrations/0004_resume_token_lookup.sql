-- ============================================================
-- Migración 0004 — versión 1.0.2
-- Recuperación eficiente por código personal.
--
-- Agrega participants.resume_token_lookup_hash: HMAC-SHA256 del código
-- personal normalizado (con APP_KEY). Permite localizar UN candidato por
-- índice y validar después con password_verify sobre resume_token_hash,
-- en lugar de recorrer todos los participantes de la familia.
--
-- La columna queda NULL para participantes existentes (el código personal
-- nunca se guarda en claro, así que no puede reconstruirse). Para esas
-- filas se conserva un fallback que solo revisa registros con
-- resume_token_lookup_hash IS NULL y, tras una reanudación exitosa,
-- completa la columna automáticamente.
--
-- El índice único (family_id, resume_token_lookup_hash) admite múltiples
-- NULL (comportamiento estándar de MySQL/MariaDB), por lo que los datos
-- legados no lo violan.
--
-- ANTES DE APLICAR: respaldar la base desde hPanel/phpMyAdmin
-- (ver docs/MIGRACION_1_0_1_A_1_0_2.md). No elimina ni modifica datos.
-- Aplicar UNA sola vez, en orden, después de 0003.
--
-- Reversa (si fuera necesario):
--   ALTER TABLE participants DROP INDEX uq_resume_lookup_per_family;
--   ALTER TABLE participants DROP COLUMN resume_token_lookup_hash;
-- ============================================================

ALTER TABLE participants
  ADD COLUMN resume_token_lookup_hash CHAR(64) NULL
  AFTER resume_token_hash;

ALTER TABLE participants
  ADD UNIQUE KEY uq_resume_lookup_per_family (family_id, resume_token_lookup_hash);
