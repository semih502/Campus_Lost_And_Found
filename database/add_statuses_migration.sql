-- ============================================================
--  Migration: Yeni Durum Kayıtları Ekle
-- ============================================================
--  campus_lost_found veritabanını seçtikten sonra
--  phpMyAdmin > SQL sekmesinde bu scripti çalıştırın.
--
--  Eklenecek:
--    Status_ID = 3  →  Resolved  (eşya sahibine teslim edildi)
--    Status_ID = 4  →  Rejected  (staff tarafından reddedildi)
--
--  Mevcut kayıtlar korunur (INSERT IGNORE).
-- ============================================================

INSERT IGNORE INTO statuses (Status_ID, Status_Name) VALUES
    (3, 'Resolved'),
    (4, 'Rejected');
