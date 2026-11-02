-- =====================================================================
-- MIGRATION: tambah entitas "events" di atas guest_groups
--
-- Jalankan skrip ini SEKALI pada database hadirin_db yang sudah berisi data
-- (hasil import database.sql versi lama). Untuk instalasi baru, cukup
-- import database.sql terbaru — skrip ini tidak diperlukan.
--
--   mysql -u root -p hadirin_db < database/migration_events.sql
--
-- Urutan alur aplikasi setelah migrasi: Event -> Grup Undangan -> Tamu.
-- =====================================================================

SET NAMES utf8mb4;

-- 1. Tabel events
CREATE TABLE IF NOT EXISTS `events` (
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

-- 2. Event default sebagai penampung data grup yang sudah ada (jika belum ada event)
INSERT INTO `events` (`event_name`, `event_date`, `location`, `description`, `status`)
SELECT 'Event Utama', CURDATE(), NULL, 'Event default dibuat otomatis saat migrasi.', 'active'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `events`);

-- 3. Kolom event_id pada guest_groups (sementara nullable untuk backfill)
ALTER TABLE `guest_groups`
  ADD COLUMN `event_id` BIGINT UNSIGNED NULL AFTER `id`;

-- 4. Isi event_id grup lama dengan event default
UPDATE `guest_groups`
  SET `event_id` = (SELECT MIN(`id`) FROM `events`)
  WHERE `event_id` IS NULL;

-- 5. Jadikan NOT NULL + index + foreign key
ALTER TABLE `guest_groups`
  MODIFY COLUMN `event_id` BIGINT UNSIGNED NOT NULL,
  ADD KEY `idx_guest_groups_event_id` (`event_id`),
  ADD CONSTRAINT `fk_guest_groups_event` FOREIGN KEY (`event_id`)
    REFERENCES `events` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
