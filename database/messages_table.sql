-- ============================================================
--  Messages Tablosu — Kullanıcılar Arası Mesajlaşma
-- ============================================================
--  Bu scripti phpMyAdmin > SQL sekmesinde doğrudan çalıştırın.
--  campus_lost_found veritabanını seçtikten sonra çalıştırın.
-- ============================================================

CREATE TABLE IF NOT EXISTS messages (
    Message_ID   INT AUTO_INCREMENT PRIMARY KEY,
    Sender_ID    INT NOT NULL,                        -- Mesajı gönderen kullanıcı
    Receiver_ID  INT NOT NULL,                        -- Mesajı alan kullanıcı
    Item_ID      INT DEFAULT NULL,                    -- İlgili ilan (opsiyonel — sohbet konusu)
    Content      TEXT NOT NULL,                       -- Mesaj içeriği
    Is_Read      TINYINT(1) DEFAULT 0,                -- 0: okunmadı, 1: okundu
    Created_At   TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Gönderilme zamanı
    FOREIGN KEY (Sender_ID)   REFERENCES users(User_ID) ON DELETE CASCADE,
    FOREIGN KEY (Receiver_ID) REFERENCES users(User_ID) ON DELETE CASCADE,
    FOREIGN KEY (Item_ID)     REFERENCES items(Item_ID)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
