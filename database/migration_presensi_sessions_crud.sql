-- =====================================================================
-- MIGRATION: CRUD "Sesi Presensi" (ganti 3 sesi hardcode jadi dinamis)
--
-- Sebelumnya session_attendance.session_type adalah ENUM tetap
-- ('masuk','snack','makan_siang'). Migrasi ini memindahkannya menjadi
-- tabel `presensi_sessions` yang bisa dikelola penuh (CRUD) oleh admin,
-- per event — jadi admin bisa menambah/mengubah/menonaktifkan area
-- presensi sendiri (tidak terbatas 3 nama tetap).
--
-- Data lama (3 sesi per grup) di-backfill otomatis ke 3 baris default
-- "Absen Masuk" / "Absen Snack" / "Absen Makan Siang" per event, supaya
-- tidak ada riwayat presensi yang hilang.
--
-- Idempotent: aman dijalankan berkali-kali.
--
--   mysql -u root -p hadirin_db < database/migration_presensi_sessions_crud.sql
-- =====================================================================

SET NAMES utf8mb4;

-- 1. Tabel presensi_sessions
CREATE TABLE IF NOT EXISTS `presensi_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_presensi_sessions_event` (`event_id`),
  KEY `idx_presensi_sessions_status` (`status`),
  CONSTRAINT `fk_presensi_sessions_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Seed 3 sesi default untuk setiap event yang belum punya sesi presensi sama sekali
INSERT INTO `presensi_sessions` (`event_id`, `name`, `sort_order`, `status`)
SELECT e.`id`, x.`name`, x.`sort_order`, 'active'
FROM `events` e
CROSS JOIN (
  SELECT 'Absen Masuk' AS `name`, 1 AS `sort_order`
  UNION ALL SELECT 'Absen Snack', 2
  UNION ALL SELECT 'Absen Makan Siang', 3
) x
WHERE NOT EXISTS (SELECT 1 FROM `presensi_sessions` ps WHERE ps.`event_id` = e.`id`);

-- 3. Kolom presensi_session_id pada session_attendance (nullable dulu untuk backfill)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'session_attendance' AND COLUMN_NAME = 'presensi_session_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `session_attendance` ADD COLUMN `presensi_session_id` BIGINT UNSIGNED NULL AFTER `group_id`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Backfill dari session_type lama (jika kolomnya masih ada) ke presensi_session_id baru
SET @old_col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'session_attendance' AND COLUMN_NAME = 'session_type'
);
SET @sql = IF(@old_col_exists > 0,
  'UPDATE `session_attendance` sa
   JOIN `guest_groups` gg ON gg.`id` = sa.`group_id`
   JOIN `presensi_sessions` ps ON ps.`event_id` = gg.`event_id`
     AND ((sa.`session_type` = \'masuk\' AND ps.`name` = \'Absen Masuk\')
       OR (sa.`session_type` = \'snack\' AND ps.`name` = \'Absen Snack\')
       OR (sa.`session_type` = \'makan_siang\' AND ps.`name` = \'Absen Makan Siang\'))
   SET sa.`presensi_session_id` = ps.`id`
   WHERE sa.`presensi_session_id` IS NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Jadikan presensi_session_id NOT NULL
ALTER TABLE `session_attendance`
  MODIFY COLUMN `presensi_session_id` BIGINT UNSIGNED NOT NULL;

-- 6. UNIQUE KEY + FK baru (ditambah DULU, sebelum index lama didrop, supaya
--    index pendukung FK `fk_session_attendance_group` pada group_id tidak
--    pernah hilang di antara dua langkah ALTER)
SET @new_uq_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'session_attendance' AND INDEX_NAME = 'uq_session_attendance_group_presensi'
);
SET @sql = IF(@new_uq_exists = 0,
  'ALTER TABLE `session_attendance` ADD UNIQUE KEY `uq_session_attendance_group_presensi` (`group_id`, `presensi_session_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'session_attendance' AND CONSTRAINT_NAME = 'fk_session_attendance_presensi_session'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `session_attendance`
     ADD CONSTRAINT `fk_session_attendance_presensi_session` FOREIGN KEY (`presensi_session_id`)
       REFERENCES `presensi_sessions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7. Hapus UNIQUE KEY lama (group_id, session_type) — aman sekarang karena
--    index baru di atas sudah menjadi index pendukung FK group_id.
SET @old_uq_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'session_attendance' AND INDEX_NAME = 'uq_session_attendance_group_session'
);
SET @sql = IF(@old_uq_exists > 0,
  'ALTER TABLE `session_attendance` DROP INDEX `uq_session_attendance_group_session`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 8. Hapus kolom session_type lama (ENUM tetap)
SET @sql = IF(@old_col_exists > 0,
  'ALTER TABLE `session_attendance` DROP COLUMN `session_type`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 9. Backfill: pastikan setiap grup yang sudah ada punya baris presensi
--    untuk setiap sesi aktif di event-nya (jaga-jaga jika ada sesi baru
--    ditambahkan admin setelah grup dibuat).
INSERT INTO `session_attendance` (`group_id`, `presensi_session_id`)
SELECT gg.`id`, ps.`id`
FROM `guest_groups` gg
JOIN `presensi_sessions` ps ON ps.`event_id` = gg.`event_id`
WHERE NOT EXISTS (
  SELECT 1 FROM `session_attendance` sa
  WHERE sa.`group_id` = gg.`id` AND sa.`presensi_session_id` = ps.`id`
);
