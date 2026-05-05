<?php
/**
 * ============================================================
 *  db_connect.php  —  Veritabanı Bağlantı Dosyası
 * ============================================================
 *  Bu dosya, MySQL veritabanına PDO (PHP Data Objects) ile
 *  güvenli bir bağlantı sağlar. Projenin tüm PHP dosyaları
 *  bu dosyayı "require_once" ile çağırarak $pdo nesnesini
 *  kullanır.
 *
 *  Kullanım : require_once __DIR__ . '/db_connect.php';
 *  Döndürür : $pdo  → PDO bağlantı nesnesi
 * ============================================================
 */

// ──────────────────────────────────────────────
//  1) Veritabanı Yapılandırma Sabitleri
// ──────────────────────────────────────────────
// Kendi XAMPP / WAMP / sunucu ayarlarınıza göre düzenleyin.
if (!defined('DB_HOST'))    define('DB_HOST', 'localhost');       // MySQL sunucu adresi
if (!defined('DB_NAME'))    define('DB_NAME', 'campus_lost_found'); // Veritabanı adı
if (!defined('DB_USER'))    define('DB_USER', 'root');           // MySQL kullanıcı adı
if (!defined('DB_PASS'))    define('DB_PASS', '');               // MySQL şifresi (XAMPP varsayılan: boş)
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');     // Türkçe karakter desteği için utf8mb4

// ──────────────────────────────────────────────
//  2) PDO Bağlantı Ayarları (DSN)
// ──────────────────────────────────────────────
// DSN (Data Source Name): PDO'nun hangi veritabanına
// nasıl bağlanacağını tanımlayan bağlantı dizesidir.
$dsn = "mysql:host=" . DB_HOST .
       ";dbname=" . DB_NAME .
       ";charset=" . DB_CHARSET;

// ──────────────────────────────────────────────
//  3) PDO Seçenekleri (Options)
// ──────────────────────────────────────────────
$options = [
    // Hata modunu "exception" olarak ayarlıyoruz.
    // Bu sayede SQL hataları PHP Exception olarak fırlatılır
    // ve try-catch ile yakalanabilir.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

    // Sorgu sonuçlarını ilişkisel (associative) dizi olarak döndürür.
    // Örn: $row['Email'] şeklinde erişim sağlar.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // PDO'nun kendi prepared statement emülasyonunu kapatır.
    // Gerçek prepared statement kullanarak SQL Injection'a karşı
    // ek güvenlik katmanı sağlar.
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// ──────────────────────────────────────────────
//  4) Bağlantıyı Oluştur (try-catch ile)
// ──────────────────────────────────────────────
try {
    // PDO nesnesi oluştur — başarılı olursa $pdo değişkeni
    // projenin her yerinde kullanılabilir hale gelir.
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // Bağlantı başarısız olursa:
    // - Gerçek hata mesajını log dosyasına yaz (güvenlik için).
    // - Kullanıcıya genel bir hata mesajı göster.
    error_log("Veritabanı Bağlantı Hatası: " . $e->getMessage());

    // JSON yanıt döndüren dosyalarda tutarlılık için
    // hata durumunda da JSON formatında yanıt ver.
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı bağlantısı kurulamadı. Lütfen daha sonra tekrar deneyin.'
    ]);
    exit; // Bağlantı yoksa devam etmenin anlamı yok — scripti sonlandır.
}
