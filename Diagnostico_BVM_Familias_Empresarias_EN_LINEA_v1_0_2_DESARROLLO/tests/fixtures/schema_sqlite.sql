-- Variante SQLite del esquema — SOLO para pruebas automatizadas locales.
-- La fuente de verdad de producción es database/schema.sql (MySQL/MariaDB).
CREATE TABLE IF NOT EXISTS admin_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'administrador' CHECK (role IN ('administrador','consultor')),
  active INTEGER NOT NULL DEFAULT 1,
  last_login_at TEXT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS families (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  public_slug TEXT NOT NULL UNIQUE,
  family_name TEXT NOT NULL,
  access_code_hash TEXT NOT NULL,
  expected_participants INTEGER NULL,
  enforce_participant_limit INTEGER NOT NULL DEFAULT 1,
  questionnaire_version TEXT NOT NULL DEFAULT 'BVM-FE-1.2',
  report_date TEXT NULL,
  status TEXT NOT NULL DEFAULT 'borrador' CHECK (status IN ('borrador','abierta','cerrada','archivada')),
  opens_at TEXT NULL,
  closes_at TEXT NULL,
  created_by INTEGER NULL REFERENCES admin_users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  archived_at TEXT NULL,
  deleted_at TEXT NULL
);

CREATE TABLE IF NOT EXISTS participants (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  public_id TEXT NOT NULL UNIQUE,
  family_id INTEGER NOT NULL REFERENCES families(id) ON DELETE CASCADE,
  participant_name TEXT NOT NULL,
  normalized_name TEXT NOT NULL,
  generation TEXT NOT NULL,
  participation_type TEXT NULL,
  participation_role TEXT NOT NULL,
  resume_token_hash TEXT NOT NULL,
  resume_token_lookup_hash TEXT NULL,
  status TEXT NOT NULL DEFAULT 'en_proceso' CHECK (status IN ('en_proceso','finalizado')),
  current_index INTEGER NOT NULL DEFAULT 0,
  revision INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  completed_at TEXT NULL,
  UNIQUE (family_id, normalized_name),
  UNIQUE (family_id, resume_token_lookup_hash)
);

CREATE TABLE IF NOT EXISTS responses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  participant_id INTEGER NOT NULL REFERENCES participants(id) ON DELETE CASCADE,
  question_id TEXT NOT NULL,
  answer_value INTEGER NOT NULL CHECK (answer_value BETWEEN 1 AND 5),
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE (participant_id, question_id)
);

CREATE TABLE IF NOT EXISTS external_responses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  participant_id INTEGER NOT NULL REFERENCES participants(id) ON DELETE CASCADE,
  question_id TEXT NOT NULL,
  answer_value INTEGER NOT NULL CHECK (answer_value BETWEEN 1 AND 5),
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE (participant_id, question_id)
);

CREATE TABLE IF NOT EXISTS audit_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_user_id INTEGER NULL,
  family_id INTEGER NULL,
  participant_id INTEGER NULL,
  event_type TEXT NOT NULL,
  metadata_json TEXT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS login_attempts (
  identifier_hash TEXT PRIMARY KEY,
  ip_hash TEXT NULL,
  attempts INTEGER NOT NULL DEFAULT 0,
  locked_until TEXT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS family_assignments (
  family_id INTEGER NOT NULL,
  admin_user_id INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  PRIMARY KEY (family_id, admin_user_id),
  FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
  FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
);
