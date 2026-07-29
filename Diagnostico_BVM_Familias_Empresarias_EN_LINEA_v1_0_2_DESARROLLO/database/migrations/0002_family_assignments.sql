-- MIGRACIÓN 0002 — tabla family_assignments (asignación de familias a
-- consultores). Prepara el modelo de roles del prompt maestro; el MVP opera
-- con el administrador principal y no escribe aún en esta tabla.
-- Instalaciones nuevas: schema.sql ya la incluye; esta migración es para
-- bases creadas con el esquema 1.0.0 previo.
CREATE TABLE IF NOT EXISTS family_assignments (
  family_id INT UNSIGNED NOT NULL,
  admin_user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (family_id, admin_user_id),
  CONSTRAINT fk_assignment_family FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
  CONSTRAINT fk_assignment_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
