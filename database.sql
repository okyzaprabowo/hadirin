-- =====================================================================
-- HADIRIN — Smart Guest Management & QR Check-in System
-- Database schema for MySQL 8.x / MariaDB (InnoDB, utf8mb4)
--
-- Import via phpMyAdmin or:
--   mysql -u root -p < database.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS hadirin_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE hadirin_db;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- TABLE: users
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `role` ENUM('admin','petugas') NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TABLE: events
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_name` VARCHAR(150) NOT NULL,
  `event_date` DATE NULL,
  `location` VARCHAR(200) NULL,
  `description` TEXT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_events_status` (`status`),
  KEY `idx_events_name` (`event_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TABLE: guest_groups
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `guest_groups`;
CREATE TABLE `guest_groups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `group_name` VARCHAR(150) NOT NULL,
  `contact_name` VARCHAR(150) NULL,
  `contact_phone` VARCHAR(30) NULL,
  `quota` INT UNSIGNED NOT NULL,
  `qr_token` VARCHAR(100) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_guest_groups_qr_token` (`qr_token`),
  KEY `idx_guest_groups_event_id` (`event_id`),
  KEY `idx_guest_groups_status` (`status`),
  KEY `idx_guest_groups_name` (`group_name`),
  CONSTRAINT `fk_guest_groups_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_guest_groups_quota` CHECK (`quota` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TABLE: guests
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `guests`;
CREATE TABLE `guests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` BIGINT UNSIGNED NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `notes` TEXT NULL,
  `checkin_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_guests_group_id` (`group_id`),
  KEY `idx_guests_name` (`full_name`),
  KEY `idx_guests_checkin_at` (`checkin_at`),
  CONSTRAINT `fk_guests_group` FOREIGN KEY (`group_id`) REFERENCES `guest_groups` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TABLE: checkin_logs
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `checkin_logs`;
CREATE TABLE `checkin_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guest_id` BIGINT UNSIGNED NULL,
  `group_id` BIGINT UNSIGNED NULL,
  `scanned_by` BIGINT UNSIGNED NOT NULL,
  `action` ENUM('checkin','rejected') NOT NULL,
  `message` VARCHAR(255) NULL,
  `scanned_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_checkin_logs_guest` (`guest_id`),
  KEY `idx_checkin_logs_group` (`group_id`),
  KEY `idx_checkin_logs_scanned_by` (`scanned_by`),
  KEY `idx_checkin_logs_scanned_at` (`scanned_at`),
  CONSTRAINT `fk_checkin_logs_guest` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_checkin_logs_group` FOREIGN KEY (`group_id`) REFERENCES `guest_groups` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_checkin_logs_user` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TABLE: audit_logs
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(100) NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `description` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user` (`user_id`),
  KEY `idx_audit_logs_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TABLE: login_attempts
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL,
  `successful` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_username_time` (`username`, `attempted_at`),
  KEY `idx_login_attempts_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA — dummy guest groups for testing (no users are seeded here)
-- NOTE: no admin/petugas account is inserted via SQL on purpose.
--       Run `php database/seed_admin.php` after import to create the
--       first admin account interactively. See README.md.
-- =====================================================================

INSERT INTO `events`
  (`event_name`, `event_date`, `location`, `description`, `status`)
VALUES
  ('Pernikahan Budi & Siti', DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'Gedung Graha Wisata',
   'Resepsi pernikahan dan syukuran keluarga', 'active');

INSERT INTO `guest_groups`
  (`event_id`, `group_name`, `contact_name`, `contact_phone`, `quota`, `qr_token`, `status`, `notes`)
VALUES
  (1, 'Keluarga Budi Santoso', 'Budi Santoso', '081234567890', 4,
   '7f3a9c2e1b8d4f6a0c5e7b9d1f3a5c7e9b1d3f5a7c9e1b3d5f7a9c1e3b5d7f9a', 'active',
   'Tamu VIP, kursi depan'),
  (1, 'Rekan Kerja PT Maju Bersama', 'Dewi Lestari', '081298765432', 6,
   '2e4b6d8f0a2c4e6b8d0f2a4c6e8b0d2f4a6c8e0b2d4f6a8c0e2b4d6f8a0c2e4b', 'active',
   NULL),
  (1, 'Komunitas Alumni 2015', 'Rizal Hakim', NULL, 5,
   '9d1f3b5a7c9e1b3d5f7a9c1e3b5d7f9a1c3e5b7d9f1a3c5e7b9d1f3a5c7e9b1d', 'active',
   'Reuni angkatan 2015');

INSERT INTO `guests` (`group_id`, `full_name`, `phone`, `notes`) VALUES
  (1, 'Budi Santoso', '081234567890', NULL),
  (1, 'Siti Aminah', NULL, NULL),
  (1, 'Andi Santoso', NULL, NULL),
  (1, 'Rina Santoso', NULL, NULL),
  (2, 'Dewi Lestari', '081298765432', NULL),
  (2, 'Agus Setiawan', NULL, NULL),
  (2, 'Fitriani', NULL, NULL),
  (2, 'Hendra Gunawan', NULL, NULL),
  (3, 'Rizal Hakim', NULL, NULL),
  (3, 'Maya Puspita', NULL, NULL),
  (3, 'Tono Wijaya', NULL, 'Datang dari luar kota');

-- =====================================================================
-- REFERENCE QUERIES (used as the basis for the PHP data-access layer)
-- =====================================================================

-- Statistik dashboard
-- SELECT
--   (SELECT COUNT(*) FROM guest_groups) AS total_groups,
--   (SELECT COUNT(*) FROM guests) AS total_guests,
--   (SELECT COUNT(*) FROM guests WHERE checkin_at IS NOT NULL) AS total_checked_in,
--   (SELECT COUNT(*) FROM guests WHERE checkin_at IS NULL) AS total_not_checked_in,
--   (SELECT COUNT(*) FROM guest_groups WHERE status = 'active') AS total_active_qr;

-- Jumlah tamu per grup
-- SELECT group_id, COUNT(*) AS total_guests
-- FROM guests GROUP BY group_id;

-- Jumlah hadir per grup
-- SELECT group_id, COUNT(*) AS total_present
-- FROM guests WHERE checkin_at IS NOT NULL GROUP BY group_id;

-- Jumlah belum hadir per grup
-- SELECT group_id, COUNT(*) AS total_absent
-- FROM guests WHERE checkin_at IS NULL GROUP BY group_id;

-- Riwayat check-in
-- SELECT cl.*, g.full_name AS guest_name, gg.group_name, u.full_name AS officer_name
-- FROM checkin_logs cl
-- LEFT JOIN guests g ON g.id = cl.guest_id
-- LEFT JOIN guest_groups gg ON gg.id = cl.group_id
-- JOIN users u ON u.id = cl.scanned_by
-- ORDER BY cl.scanned_at DESC;

-- Laporan tamu
-- SELECT g.full_name, gg.group_name,
--        IF(g.checkin_at IS NULL, 'Belum Hadir', 'Sudah Hadir') AS status,
--        g.checkin_at
-- FROM guests g JOIN guest_groups gg ON gg.id = g.group_id
-- ORDER BY gg.group_name, g.full_name;

-- Validasi QR token
-- SELECT * FROM guest_groups WHERE qr_token = ? AND status = 'active' LIMIT 1;
