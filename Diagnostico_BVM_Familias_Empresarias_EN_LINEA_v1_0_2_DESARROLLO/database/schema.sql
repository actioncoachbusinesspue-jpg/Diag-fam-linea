-- ============================================================
-- Diagnóstico BVM para Familias Empresarias — versión en línea
-- Esquema MySQL/MariaDB (utf8mb4). Importar desde phpMyAdmin/hPanel.
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('administrador','consultor') NOT NULL DEFAULT 'administrador',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS families (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_slug VARCHAR(32) NOT NULL,
  family_name VARCHAR(150) NOT NULL,
  access_code_hash VARCHAR(255) NOT NULL,
  expected_participants SMALLINT UNSIGNED NULL,
  questionnaire_version VARCHAR(20) NOT NULL DEFAULT 'BVM-FE-1.2',
  report_date DATE NULL,
  status ENUM('borrador','abierta','cerrada','archivada') NOT NULL DEFAULT 'borrador',
  opens_at DATE NULL,
  closes_at DATE NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  archived_at DATETIME NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_family_slug (public_slug),
  KEY idx_family_status (status),
  CONSTRAINT fk_family_creator FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS participants (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(40) NOT NULL,
  family_id INT UNSIGNED NOT NULL,
  participant_name VARCHAR(120) NOT NULL,
  normalized_name VARCHAR(120) NOT NULL,
  generation VARCHAR(60) NOT NULL,
  participation_type VARCHAR(80) NULL,
  participation_role VARCHAR(80) NOT NULL,
  resume_token_hash VARCHAR(255) NOT NULL,
  status ENUM('en_proceso','finalizado') NOT NULL DEFAULT 'en_proceso',
  current_index SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  revision INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_participant_public_id (public_id),
  UNIQUE KEY uq_participant_name_per_family (family_id, normalized_name),
  KEY idx_participant_family (family_id),
  CONSTRAINT fk_participant_family FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS responses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  participant_id INT UNSIGNED NOT NULL,
  question_id VARCHAR(10) NOT NULL,
  answer_value TINYINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_response (participant_id, question_id),
  CONSTRAINT chk_answer_range CHECK (answer_value BETWEEN 1 AND 5),
  CONSTRAINT fk_response_participant FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS external_responses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  participant_id INT UNSIGNED NOT NULL,
  question_id VARCHAR(10) NOT NULL,
  answer_value TINYINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_external_response (participant_id, question_id),
  CONSTRAINT chk_external_answer_range CHECK (answer_value BETWEEN 1 AND 5),
  CONSTRAINT fk_external_participant FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_user_id INT UNSIGNED NULL,
  family_id INT UNSIGNED NULL,
  participant_id INT UNSIGNED NULL,
  event_type VARCHAR(60) NOT NULL,
  metadata_json TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_audit_event_type (event_type),
  KEY idx_audit_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  identifier_hash CHAR(64) NOT NULL,
  ip_hash CHAR(64) NULL,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (identifier_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Asignación de familias a consultores (modelo preparado para roles; el MVP
-- opera con el administrador principal y no escribe aún en esta tabla).
CREATE TABLE IF NOT EXISTS family_assignments (
  family_id INT UNSIGNED NOT NULL,
  admin_user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (family_id, admin_user_id),
  CONSTRAINT fk_assignment_family FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
  CONSTRAINT fk_assignment_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
