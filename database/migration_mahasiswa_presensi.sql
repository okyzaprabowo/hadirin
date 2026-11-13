-- =====================================================================
-- MIGRATION: pendaftaran mandiri mahasiswa + presensi 3 sesi
--
-- Menambahkan:
--   1. Role baru 'mahasiswa' pada tabel users, + kolom users.group_id
--      (akun mahasiswa terikat ke satu guest_groups hasil daftar mandiri).
--   2. Tabel session_attendance: presensi PER GRUP untuk 3 sesi
--      (masuk, snack, makan_siang), terpisah dari checkin_at lama yang
--      dipakai alur barcode admin/petugas.
--   3. Backfill: setiap guest_groups yang sudah ada otomatis diberi
--      3 baris session_attendance (belum hadir di ketiganya).
--
-- Idempotent: aman dijalankan berkali-kali.
--
--   mysql -u root -p hadirin_db < database/migration_mahasiswa_presensi.sql
-- =====================================================================

SET NAMES utf8mb4;

-- 1. Role 'mahasiswa' + kolom group_id pada users
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('admin','petugas','mahasiswa') NOT NULL;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'group_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `group_id` BIGINT UNSIGNED NULL AFTER `role`',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'fk_users_group'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `users`
     ADD KEY `idx_users_group_id` (`group_id`),
     ADD CONSTRAINT `fk_users_group` FOREIGN KEY (`group_id`)
       REFERENCES `guest_groups` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Tabel session_attendance (presensi per grup, per sesi)
CREATE TABLE IF NOT EXISTS `session_attendance` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` BIGINT UNSIGNED NOT NULL,
  `session_type` ENUM('masuk','snack','makan_siang') NOT NULL,
  `checked_in_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session_attendance_group_session` (`group_id`, `session_type`),
  KEY `idx_session_attendance_checked_in` (`checked_in_at`),
  CONSTRAINT `fk_session_attendance_group` FOREIGN KEY (`group_id`) REFERENCES `guest_groups` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Backfill 3 baris sesi untuk setiap grup yang sudah ada
INSERT INTO `session_attendance` (`group_id`, `session_type`)
SELECT gg.`id`, s.`session_type`
FROM `guest_groups` gg
CROSS JOIN (
  SELECT 'masuk' AS `session_type`
  UNION ALL SELECT 'snack'
  UNION ALL SELECT 'makan_siang'
) s
WHERE NOT EXISTS (
  SELECT 1 FROM `session_attendance` sa
  WHERE sa.`group_id` = gg.`id` AND sa.`session_type` = s.`session_type`
);
