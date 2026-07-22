-- ============================================================
-- Migration: add email-OTP second factor to an existing install
-- Run this only if you already created pharmacy_pms from an
-- earlier version of schema.sql that lacked the `email` column
-- and `login_otps` table.
-- ============================================================

USE pharmacy_pms;

ALTER TABLE users
  ADD COLUMN email VARCHAR(150) NOT NULL DEFAULT '' AFTER full_name;

-- Backfill placeholder emails so the UNIQUE constraint doesn't collide,
-- then update them to real addresses before relying on OTP delivery.
UPDATE users SET email = CONCAT(username, '@pharmos.local') WHERE email = '';

ALTER TABLE users
  ADD UNIQUE KEY uq_users_email (email);

CREATE TABLE IF NOT EXISTS login_otps (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  otp_hash   VARCHAR(255) NOT NULL,
  expires_at DATETIME     NOT NULL,
  attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_used    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_otp_user (user_id, is_used)
) ENGINE=InnoDB;
