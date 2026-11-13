-- =====================================================================
-- MIGRATION: hapus kolom qr_token (fitur barcode dihapus)
--
-- Sejak presensi dilakukan via login mahasiswa + QR rotasi (bukan barcode
-- statis per grup), kolom `guest_groups.qr_token` dan unique key-nya sudah
-- tidak dipakai di manapun. Migrasi ini menghapusnya.
--
-- Idempotent: aman dijalankan berkali-kali.
--
--   mysql -u root -p hadirin_db < database/migration_remove_barcode.sql
-- =====================================================================

SET NAMES utf8mb4;

SET @uq_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guest_groups' AND INDEX_NAME = 'uq_guest_groups_qr_token'
);
SET @sql = IF(@uq_exists > 0,
  'ALTER TABLE `guest_groups` DROP INDEX `uq_guest_groups_qr_token`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guest_groups' AND COLUMN_NAME = 'qr_token'
);
SET @sql = IF(@col_exists > 0,
  'ALTER TABLE `guest_groups` DROP COLUMN `qr_token`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
