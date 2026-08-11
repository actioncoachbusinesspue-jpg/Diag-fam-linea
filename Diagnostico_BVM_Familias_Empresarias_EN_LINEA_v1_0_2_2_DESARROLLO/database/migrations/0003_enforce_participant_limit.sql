-- ============================================================
-- Migración 0003 — versión 1.0.2
-- Control del número de participantes esperados.
--
-- Agrega families.enforce_participant_limit:
--   1 (predeterminado) = expected_participants actúa como límite real:
--       al alcanzarlo se rechazan NUEVOS registros (los ya registrados
--       pueden continuar y finalizar).
--   0 = expected_participants es solo una referencia (meta); se permite
--       el excedente y la administración lo muestra como "excedido".
--
-- Cuando expected_participants es NULL no existe límite en ningún modo.
--
-- ANTES DE APLICAR: respaldar la base desde hPanel/phpMyAdmin
-- (ver docs/MIGRACION_1_0_1_A_1_0_2.md). No elimina ni modifica datos.
-- Aplicar UNA sola vez, en orden, después de 0002.
--
-- Reversa (si fuera necesario):
--   ALTER TABLE families DROP COLUMN enforce_participant_limit;
-- ============================================================

ALTER TABLE families
  ADD COLUMN enforce_participant_limit TINYINT(1) NOT NULL DEFAULT 1
  AFTER expected_participants;
