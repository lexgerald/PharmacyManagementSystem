-- ============================================================
-- Pharmacy Management System (PMS) — Database Schema
-- Engine: MySQL / MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS pharmacy_pms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pharmacy_pms;

-- ------------------------------------------------------------
-- users : staff accounts allowed to operate the system
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(120) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  role          ENUM('admin','pharmacist','technician') NOT NULL DEFAULT 'pharmacist',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- login_otps : one-time passcodes emailed at login (2nd factor)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_otps (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  otp_hash   VARCHAR(255) NOT NULL,   -- HMAC-SHA256 of the code, never the plain code
  expires_at DATETIME     NOT NULL,
  attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_used    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_otp_user (user_id, is_used)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- drugs : inventory of stocked medications
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS drugs (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  barcode        VARCHAR(64)   NOT NULL UNIQUE,
  name           VARCHAR(150)  NOT NULL,
  category       VARCHAR(80)   NOT NULL DEFAULT 'General',
  strength       VARCHAR(50)   NOT NULL DEFAULT '',
  form           ENUM('Tablet','Capsule','Syrup','Injection','Cream','Drops','Inhaler','Other') NOT NULL DEFAULT 'Tablet',
  stock_quantity INT           NOT NULL DEFAULT 0,
  reorder_level  INT           NOT NULL DEFAULT 10,
  expiry_date    DATE          NOT NULL,
  price          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_barcode (barcode),
  INDEX idx_name (name),
  INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- sales : outgoing / dispensation log
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sales (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  drug_id      INT UNSIGNED NOT NULL,
  quantity     INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price   DECIMAL(10,2) NOT NULL,
  total_price  DECIMAL(10,2) NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  sold_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sales_drug FOREIGN KEY (drug_id) REFERENCES drugs(id) ON DELETE CASCADE,
  CONSTRAINT fk_sales_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_sold_at (sold_at)
) ENGINE=InnoDB;

-- ============================================================
-- Sample data
-- ============================================================

-- Default user: username "admin", password "admin123"
-- Default user: username "jkamara", password "admin123"
-- (hash below is bcrypt for 'admin123' — CHANGE THESE PASSWORDS in production!)
-- Update the email addresses below to real inboxes you control before testing OTP login.
INSERT INTO users (username, password_hash, full_name, email, role) VALUES
('admin', '$2b$10$dLo3lLaXJSfX/FEKkhgwpuY3G5uA1Yo/n/X8tejjeg2kPJWj4Y9LK', 'System Administrator', 'admin@pharmos.local', 'admin'),
('jkamara', '$2b$10$dLo3lLaXJSfX/FEKkhgwpuY3G5uA1Yo/n/X8tejjeg2kPJWj4Y9LK', 'Jenneh Kamara', 'jkamara@pharmos.local', 'pharmacist');

INSERT INTO drugs (barcode, name, category, strength, form, stock_quantity, reorder_level, expiry_date, price) VALUES
('8901030875021', 'Paracetamol', 'Analgesic', '500mg', 'Tablet', 240, 50, '2027-03-15', 0.05),
('8901030875038', 'Amoxicillin', 'Antibiotic', '250mg', 'Capsule', 18, 30, '2026-09-01', 0.12),
('8901030875045', 'Ibuprofen', 'NSAID', '400mg', 'Tablet', 120, 40, '2027-01-20', 0.08),
('8901030875052', 'Cough Syrup', 'Respiratory', '100ml', 'Syrup', 6, 15, '2026-08-05', 2.50),
('8901030875069', 'Metformin', 'Antidiabetic', '500mg', 'Tablet', 85, 25, '2027-06-30', 0.10),
('8901030875076', 'Omeprazole', 'Antacid', '20mg', 'Capsule', 0, 20, '2026-12-01', 0.15),
('8901030875083', 'Ceftriaxone Injection', 'Antibiotic', '1g', 'Injection', 40, 10, '2026-10-10', 3.20),
('8901030875090', 'Hydrocortisone Cream', 'Dermatological', '1%', 'Cream', 22, 10, '2027-02-14', 1.75),
('8901030875106', 'Salbutamol Inhaler', 'Respiratory', '100mcg', 'Inhaler', 14, 10, '2026-11-25', 4.90),
('8901030875113', 'Artemether/Lumefantrine', 'Antimalarial', '20/120mg', 'Tablet', 60, 20, '2026-08-18', 1.40);
