CREATE DATABASE IF NOT EXISTS expedig CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE expedig;

CREATE TABLE directions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL UNIQUE,
  code VARCHAR(30) NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE officers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  position VARCHAR(30) NOT NULL UNIQUE,
  cedula VARCHAR(30) NULL UNIQUE,
  rank_name VARCHAR(80) NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  direction_id BIGINT UNSIGNED NULL,
  department VARCHAR(180) NULL,
  status ENUM('activo','inactivo','jubilado','trasladado') NOT NULL DEFAULT 'activo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_officers_direction FOREIGN KEY (direction_id) REFERENCES directions(id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_officers_name (last_name, first_name),
  INDEX idx_officers_direction (direction_id),
  INDEX idx_officers_rank (rank_name)
) ENGINE=InnoDB;

CREATE TABLE document_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  category VARCHAR(100) NOT NULL DEFAULT 'Seguro privado',
  required_flag TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  officer_id BIGINT UNSIGNED NOT NULL,
  document_type_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL UNIQUE,
  mime_type VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
  size_bytes BIGINT UNSIGNED NOT NULL,
  document_date DATE NULL,
  description VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_documents_officer FOREIGN KEY (officer_id) REFERENCES officers(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_documents_type FOREIGN KEY (document_type_id) REFERENCES document_types(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_documents_officer (officer_id),
  INDEX idx_documents_type (document_type_id),
  INDEX idx_documents_date (document_date)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT UNSIGNED NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_entity (entity_type, entity_id),
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO directions (name, code) VALUES
('Dirección Nacional de Telemática', 'TELEMATICA'),
('Dirección de Recursos Humanos', 'RRHH'),
('Zona Policial de Panamá Oeste', 'ZP-POESTE');

INSERT IGNORE INTO document_types (name, category, required_flag) VALUES
('Solicitud de seguro', 'Seguro privado', 1),
('Inclusión de dependiente', 'Seguro privado', 0),
('Formulario médico', 'Seguro privado', 0),
('Reclamo', 'Seguro privado', 0),
('Actualización de póliza', 'Seguro privado', 0),
('Comprobante de pago', 'Seguro privado', 0),
('Certificación laboral', 'Seguro privado', 0),
('Otro', 'Seguro privado', 0);

INSERT IGNORE INTO officers (position, cedula, rank_name, first_name, last_name, direction_id, department)
SELECT '20854', '8-123-456', 'Subteniente', 'José', 'González', id, 'Departamento de Redes'
FROM directions WHERE code='TELEMATICA' LIMIT 1;
