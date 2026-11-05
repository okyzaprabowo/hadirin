-- =====================================================================
-- MIGRATION: kuota grup menjadi 3 + tambah tamu "Ayah" dan "Ibu"
--
-- Setiap grup (1 peserta wisuda) diubah kuotanya menjadi 3 orang, lalu
-- ditambahkan 2 tamu baru: "Ayah <nama peserta>" dan "Ibu <nama peserta>",
-- sehingga total tamu per grup = peserta + ayah + ibu = 3 (sesuai kuota).
--
-- Skrip ini AMAN dijalankan berkali-kali (idempotent): baris "Ayah"/"Ibu"
-- hanya ditambahkan jika belum ada tamu dengan nama persis sama di grup
-- tersebut.
--
--   mysql -u root -p hadirin_db < database/migration_family_guests.sql
-- =====================================================================

SET NAMES utf8mb4;

-- 1. Kuota setiap grup menjadi 3
UPDATE `guest_groups` SET `quota` = 3;

-- 2. Tambah tamu "Ayah <nama peserta>"
INSERT INTO `guests` (`group_id`, `full_name`, `created_at`, `updated_at`)
SELECT gg.`id`, CONCAT('Ayah ', gg.`contact_name`), NOW(), NOW()
FROM `guest_groups` gg
WHERE gg.`contact_name` IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `guests` g
    WHERE g.`group_id` = gg.`id`
      AND g.`full_name` = CONCAT('Ayah ', gg.`contact_name`)
  );

-- 3. Tambah tamu "Ibu <nama peserta>"
INSERT INTO `guests` (`group_id`, `full_name`, `created_at`, `updated_at`)
SELECT gg.`id`, CONCAT('Ibu ', gg.`contact_name`), NOW(), NOW()
FROM `guest_groups` gg
WHERE gg.`contact_name` IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `guests` g
    WHERE g.`group_id` = gg.`id`
      AND g.`full_name` = CONCAT('Ibu ', gg.`contact_name`)
  );
