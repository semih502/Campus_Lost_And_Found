-- ============================================================
--  Veritabanı Genişletme — Yeni Locations ve Categories
-- ============================================================
--  Bu scripti phpMyAdmin > SQL sekmesinde doğrudan çalıştırın.
--  Mevcut verilere dokunmaz, sadece yeni kayıtlar ekler.
-- ============================================================

-- ──────────────────────────────────────────────
--  Yeni Lokasyonlar (Locations)
-- ──────────────────────────────────────────────
INSERT INTO locations (Location_Name) VALUES
    ('Faculty of Business'),
    ('School of Foreign Languages'),
    ('Social Life Center'),
    ('Faculty of Health Sciences'),
    ('Faculty of Medicine');

-- ──────────────────────────────────────────────
--  Yeni Kategoriler (Categories)
-- ──────────────────────────────────────────────
INSERT INTO categories (Category_Name) VALUES
    ('Clothing'),
    ('Keys & Keychains'),
    ('Glasses'),
    ('Water Bottle & Thermos'),
    ('Wallets & ID Cards');

-- ============================================================
--  Kontrol: Eklenen verileri doğrula
-- ============================================================
-- SELECT * FROM locations;
-- SELECT * FROM categories;
