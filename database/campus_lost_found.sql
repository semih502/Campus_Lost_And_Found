-- ============================================================
--  Campus Lost & Found — Veritabanı Kurulum Scripti
-- ============================================================
--  Bu script, projenin ihtiyaç duyduğu tüm tabloları oluşturur
--  ve gerekli başlangıç (seed) verilerini ekler.
--
--  Kullanım:
--    1. XAMPP'te MySQL/MariaDB servisini başlatın
--    2. phpMyAdmin'e gidin (http://localhost/phpmyadmin)
--    3. Sol menüden "New" (Yeni) diyerek "campus_lost_found" 
--       adında bir veritabanı oluşturun
--    4. Bu veritabanını seçin ve "SQL" sekmesine tıklayın
--    5. Aşağıdaki tüm SQL kodunu yapıştırıp "Go" butonuna basın
-- ============================================================

-- Karakter seti ayarı (Türkçe karakter desteği)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ──────────────────────────────────────────────
--  1) users tablosu (Üsttip / Supertype)
-- ──────────────────────────────────────────────
--  Tüm kullanıcıların ortak bilgilerini tutar.
--  User_Type: 'student' veya 'staff'
CREATE TABLE IF NOT EXISTS users (
    User_ID    INT AUTO_INCREMENT PRIMARY KEY,
    Full_Name  VARCHAR(100) NOT NULL,
    Email      VARCHAR(150) NOT NULL UNIQUE,
    Password   VARCHAR(64)  NOT NULL,          -- SHA-256 hash (64 hex karakter)
    User_Type  ENUM('student', 'staff') NOT NULL,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────
--  2) students tablosu (Alttip / Subtype)
-- ──────────────────────────────────────────────
--  Öğrenci kullanıcıların ek bilgisi.
--  User_ID → users tablosuna FK (1:1 ilişki)
CREATE TABLE IF NOT EXISTS students (
    User_ID        INT PRIMARY KEY,
    Student_Number VARCHAR(10) NOT NULL,
    FOREIGN KEY (User_ID) REFERENCES users(User_ID)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────
--  3) staff tablosu (Alttip / Subtype)
-- ──────────────────────────────────────────────
--  Personel kullanıcıların ek bilgisi.
--  User_ID → users tablosuna FK (1:1 ilişki)
CREATE TABLE IF NOT EXISTS staff (
    User_ID      INT PRIMARY KEY,
    Staff_Number VARCHAR(10) NOT NULL,
    FOREIGN KEY (User_ID) REFERENCES users(User_ID)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────
--  4) statuses tablosu (Lookup / Referans)
-- ──────────────────────────────────────────────
--  İlan durumları: Lost (1), Found (2)
CREATE TABLE IF NOT EXISTS statuses (
    Status_ID   INT AUTO_INCREMENT PRIMARY KEY,
    Status_Name VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO statuses (Status_ID, Status_Name) VALUES
    (1, 'Lost'),
    (2, 'Found');

-- ──────────────────────────────────────────────
--  5) categories tablosu (Lookup / Referans)
-- ──────────────────────────────────────────────
--  Eşya kategorileri — HTML select ile eşleşir
CREATE TABLE IF NOT EXISTS categories (
    Category_ID   INT AUTO_INCREMENT PRIMARY KEY,
    Category_Name VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (Category_ID, Category_Name) VALUES
    (1, 'Electronics'),
    (2, 'ID Cards & Wallet'),
    (3, 'Books & Stationery'),
    (4, 'Keys'),
    (5, 'Clothing'),
    (6, 'Keys & Keychains'),
    (7, 'Glasses'),
    (8, 'Water Bottle & Thermos'),
    (9, 'Wallets & ID Cards');

-- ──────────────────────────────────────────────
--  6) locations tablosu (Lookup / Referans)
-- ──────────────────────────────────────────────
--  Kampüs binaları / konumları — HTML select ile eşleşir
CREATE TABLE IF NOT EXISTS locations (
    Location_ID   INT AUTO_INCREMENT PRIMARY KEY,
    Location_Name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO locations (Location_ID, Location_Name) VALUES
    (1, 'Central Library'),
    (2, 'Engineering Faculty'),
    (3, 'Student Center (Cafeteria)'),
    (4, 'Faculty of Business'),
    (5, 'School of Foreign Languages'),
    (6, 'Social Life Center'),
    (7, 'Faculty of Health Sciences'),
    (8, 'Faculty of Medicine');

-- ──────────────────────────────────────────────
--  7) items tablosu (Ana ilan tablosu)
-- ──────────────────────────────────────────────
--  Kullanıcıların oluşturduğu kayıp/bulunmuş eşya ilanları
CREATE TABLE IF NOT EXISTS items (
    Item_ID      INT AUTO_INCREMENT PRIMARY KEY,
    User_ID      INT NOT NULL,
    Title        VARCHAR(100) NOT NULL,
    Description  TEXT NOT NULL,
    Category_ID  INT NOT NULL,
    Location_ID  INT NOT NULL,
    Status_ID    INT NOT NULL,
    Created_At   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (User_ID)     REFERENCES users(User_ID)      ON DELETE CASCADE,
    FOREIGN KEY (Category_ID) REFERENCES categories(Category_ID),
    FOREIGN KEY (Location_ID) REFERENCES locations(Location_ID),
    FOREIGN KEY (Status_ID)   REFERENCES statuses(Status_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────
--  8) item_photos tablosu (İlan görselleri)
-- ──────────────────────────────────────────────
--  Her ilana ait fotoğraflar (1:N ilişki)
CREATE TABLE IF NOT EXISTS item_photos (
    Photo_ID    INT AUTO_INCREMENT PRIMARY KEY,
    Item_ID     INT NOT NULL,
    Photo_Path  VARCHAR(255) NOT NULL,
    Uploaded_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Item_ID) REFERENCES items(Item_ID)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────
--  9) messages tablosu (Kullanıcılar Arası Mesajlaşma)
-- ──────────────────────────────────────────────
--  Kullanıcıların ilan sahipleriyle/bulanlarla iletişim kurması
CREATE TABLE IF NOT EXISTS messages (
    Message_ID  INT AUTO_INCREMENT PRIMARY KEY,
    Sender_ID   INT NOT NULL,
    Receiver_ID INT NOT NULL,
    Item_ID     INT NULL,
    Content     VARCHAR(500) NOT NULL,
    Is_Read     TINYINT(1) DEFAULT 0,
    Created_At  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Sender_ID)   REFERENCES users(User_ID) ON DELETE CASCADE,
    FOREIGN KEY (Receiver_ID) REFERENCES users(User_ID) ON DELETE CASCADE,
    FOREIGN KEY (Item_ID)     REFERENCES items(Item_ID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  Kurulum tamamlandı!
--  Tablolar: users, students, staff, statuses, categories,
--            locations, items, item_photos, messages
-- ============================================================
