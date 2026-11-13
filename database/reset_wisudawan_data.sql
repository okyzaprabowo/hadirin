-- =====================================================================
-- RESET DATA WISUDAWAN
--
-- Mengosongkan SEMUA data wisudawan (grup, tamu, presensi, akun
-- mahasiswa) untuk memulai pendaftaran produksi dari nol. TIDAK
-- menghapus: akun admin/petugas, events, presensi_sessions (konfigurasi
-- acara tetap dipertahankan), login_attempts, audit_logs.
--
-- PERINGATAN: operasi ini permanen dan tidak bisa dibatalkan. Pastikan
-- sudah backup (mysqldump) jika masih butuh datanya.
--
--   mysql -u root -p hadirin_db < database/reset_wisudawan_data.sql
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `checkin_logs`;
TRUNCATE TABLE `session_attendance`;
TRUNCATE TABLE `guests`;
TRUNCATE TABLE `guest_groups`;
DELETE FROM `users` WHERE `role` = 'mahasiswa';

SET FOREIGN_KEY_CHECKS = 1;
