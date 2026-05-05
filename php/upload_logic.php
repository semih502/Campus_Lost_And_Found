<?php
/**
 * ============================================================
 *  upload_logic.php  —  Yeni İlan Ekleme İşlemi
 * ============================================================
 *  Bu dosya, 03_create-ad.html sayfasındaki formdan
 *  AJAX (fetch API) ile gönderilen ilan verilerini ve
 *  (opsiyonel) fotoğrafı işler.
 *
 *  İş Akışı:
 *    1. Session kontrolü — kullanıcı giriş yapmış mı?
 *    2. POST verilerini al ve filtrele (sanitize).
 *    3. Sunucu tarafı doğrulama (validation).
 *    4. Fotoğraf yükleme — MIME tipi ve boyut kontrolü.
 *    5. items tablosuna INSERT (PDO prepared statement).
 *    6. Fotoğraf varsa item_photos tablosuna INSERT.
 *    7. Sonucu JSON formatında döndür.
 *
 *  Veritabanı Tabloları:
 *    items       → Item_ID (PK), User_ID (FK), Title, Description,
 *                   Category_ID (FK), Location_ID (FK), Status_ID (FK)
 *    item_photos → Photo_ID (PK), Item_ID (FK), Photo_Path
 * ============================================================
 */

// ──────────────────────────────────────────────
//  0) Başlangıç Ayarları
// ──────────────────────────────────────────────
session_start();
header('Content-Type: application/json; charset=utf-8');

// Sadece POST isteklerini kabul et.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz istek metodu. Sadece POST kabul edilir.'
    ]);
    exit;
}

// ──────────────────────────────────────────────
//  1) Oturum Güvenliği — Yetkilendirme Kontrolü
// ──────────────────────────────────────────────
// Kullanıcı giriş yapmadan ilan oluşturamaz.
// login_process.php'de session'a kaydedilen user_id'yi kontrol et.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // 401 Unauthorized
    echo json_encode([
        'success' => false,
        'message' => 'Bu işlem için giriş yapmanız gerekmektedir. Lütfen önce giriş yapın.'
    ]);
    exit;
}

// Giriş yapmış kullanıcının ID'sini al.
$userId = $_SESSION['user_id'];

// Veritabanı bağlantısını dahil et.
require_once __DIR__ . '/db_connect.php';

// ──────────────────────────────────────────────
//  2) Form Verilerini Al ve Filtrele (Sanitize)
// ──────────────────────────────────────────────
// trim()           → baştaki ve sondaki boşlukları temizler.
// htmlspecialchars() → XSS saldırılarını önler.
// intval()          → sayısal değerleri güvenli hale getirir.
$title       = trim(htmlspecialchars($_POST['title']       ?? '', ENT_QUOTES, 'UTF-8'));
$description = trim(htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8'));
$category    = intval($_POST['category'] ?? 0);  // Category_ID (FK)
$location    = intval($_POST['location'] ?? 0);  // Location_ID (FK)
$status      = trim($_POST['status']     ?? ''); // 'lost' veya 'found'

// ──────────────────────────────────────────────
//  3) Sunucu Tarafı Doğrulama (Validation)
// ──────────────────────────────────────────────
// "Never trust user input" — frontend doğrulaması
// atlanabilir, bu yüzden sunucuda da kontrol ediyoruz.

// 3a) Zorunlu alan kontrolü
if (empty($title) || empty($description) || $category <= 0 || $location <= 0 || empty($status)) {
    echo json_encode([
        'success' => false,
        'message' => 'Lütfen tüm zorunlu alanları doldurun.'
    ]);
    exit;
}

// 3b) Başlık uzunluk kontrolü — maksimum 100 karakter
if (mb_strlen($title) > 100) {
    echo json_encode([
        'success' => false,
        'message' => 'Başlık en fazla 100 karakter olabilir.'
    ]);
    exit;
}

// 3c) Açıklama uzunluk kontrolü — maksimum 1000 karakter
if (mb_strlen($description) > 1000) {
    echo json_encode([
        'success' => false,
        'message' => 'Açıklama en fazla 1000 karakter olabilir.'
    ]);
    exit;
}

// 3d) Status değeri kontrolü — sadece 'lost' veya 'found'
if (!in_array($status, ['lost', 'found'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz ilan tipi seçildi.'
    ]);
    exit;
}

// ──────────────────────────────────────────────
//  4) Status → Status_ID Eşleştirmesi
// ──────────────────────────────────────────────
// Radyo butonlarından gelen metin değerini
// veritabanındaki statuses tablosunun PK'sına eşle.
//   'lost'  → Status_ID = 1
//   'found' → Status_ID = 2
$statusMap = [
    'lost'  => 1,
    'found' => 2
];
$statusId = $statusMap[$status];

// ──────────────────────────────────────────────
//  5) Fotoğraf Yükleme İşlemi (Opsiyonel)
// ──────────────────────────────────────────────
// Kullanıcı fotoğraf yüklemek zorunda değil.
// Yüklenmemişse $photoPath = null olarak kalır ve
// veritabanına NULL olarak kaydedilir.
$photoPath = null;

// Dosya yüklenmiş mi kontrol et.
// UPLOAD_ERR_OK (0) = dosya başarıyla yüklendi.
if (isset($_FILES['item_photo']) && $_FILES['item_photo']['error'] === UPLOAD_ERR_OK) {

    $file = $_FILES['item_photo'];

    // ── 5a) MIME Tipi Kontrolü ──
    // Sadece izin verilen görsel formatlarını kabul et.
    // finfo_file() fonksiyonu dosyanın gerçek MIME tipini okur
    // (uzantıya güvenmek güvenli değildir).
    $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Desteklenmeyen dosya formatı. Sadece JPEG, PNG ve WebP kabul edilir.'
        ]);
        exit;
    }

    // ── 5b) Dosya Boyutu Kontrolü ──
    // Maksimum 5MB (5 * 1024 * 1024 = 5.242.880 byte)
    $maxFileSize = 5 * 1024 * 1024;

    if ($file['size'] > $maxFileSize) {
        echo json_encode([
            'success' => false,
            'message' => 'Dosya boyutu 5MB\'ı aşamaz.'
        ]);
        exit;
    }

    // ── 5c) Benzersiz Dosya Adı Oluştur ──
    // uniqid() + rastgele sayı ile çakışmaları önle.
    // Orijinal dosya adı kullanılmaz — güvenlik riski (path traversal).
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];
    $extension   = $mimeToExt[$mimeType];
    $newFileName = uniqid('item_', true) . '.' . $extension;
    // Örnek çıktı: item_6830abc1e2f3a4.87654321.jpg

    // ── 5d) Yükleme Dizinini Hazırla ──
    // uploads/ dizini proje kök dizininde oluşturulur.
    // __DIR__ = php/ dizini → dirname(__DIR__) = proje kök dizini
    $uploadDir = dirname(__DIR__) . '/uploads/';

    // Dizin yoksa oluştur (recursive: alt dizinler de dahil).
    // 0755: sahibi okur-yazar-çalıştırır, diğerleri okur-çalıştırır.
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . $newFileName;

    // ── 5e) Dosyayı Kalıcı Konumuna Taşı ──
    // move_uploaded_file() PHP'nin geçici dizininden
    // kalıcı uploads/ dizinine güvenli taşıma yapar.
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        echo json_encode([
            'success' => false,
            'message' => 'Fotoğraf yüklenirken bir hata oluştu. Lütfen tekrar deneyin.'
        ]);
        exit;
    }

    // Veritabanına kaydedilecek göreceli yol (relative path).
    // Frontend bu yol ile görseli görüntüleyebilir.
    $photoPath = 'uploads/' . $newFileName;
}

// ──────────────────────────────────────────────
//  6) Veritabanına Kayıt — items + item_photos
// ──────────────────────────────────────────────
// PDO Transaction ile iki tablo atomik olarak güncellenir.
// Biri başarısız olursa diğeri de geri alınır (rollback).
try {
    $pdo->beginTransaction();

    // ── 6a) items tablosuna INSERT ──
    // User_ID: giriş yapmış kullanıcının session'daki ID'si.
    // Status_ID: radyo butonundan gelen değerin eşleştirilmiş hali.
    // Category_ID, Location_ID: select dropdown'lardan gelen FK değerleri.
    $stmtItem = $pdo->prepare("
        INSERT INTO items (User_ID, Title, Description, Category_ID, Location_ID, Status_ID)
        VALUES (:user_id, :title, :description, :category_id, :location_id, :status_id)
    ");
    $stmtItem->execute([
        ':user_id'     => $userId,
        ':title'       => $title,
        ':description' => $description,
        ':category_id' => $category,
        ':location_id' => $location,
        ':status_id'   => $statusId
    ]);

    // Yeni eklenen ilanın ID'sini al.
    $newItemId = $pdo->lastInsertId();

    // ── 6b) Fotoğraf varsa item_photos tablosuna INSERT ──
    // item_photos tablosu, items ile 1:N ilişkisindedir.
    // İleride birden fazla fotoğraf desteği eklenebilir.
    if ($photoPath !== null) {
        $stmtPhoto = $pdo->prepare("
            INSERT INTO item_photos (Item_ID, Photo_Path)
            VALUES (:item_id, :photo_path)
        ");
        $stmtPhoto->execute([
            ':item_id'    => $newItemId,
            ':photo_path' => $photoPath
        ]);
    }

    // Her iki INSERT de başarılı → Transaction'ı onayla.
    $pdo->commit();

    // ── 6c) Başarı yanıtını döndür ──
    echo json_encode([
        'success'  => true,
        'message'  => 'İlanınız başarıyla yayınlandı!',
        'item_id'  => $newItemId,
        'redirect' => '05_dashboard.html'
    ]);

} catch (PDOException $e) {
    // Hata oluştu → tüm değişiklikleri geri al.
    $pdo->rollBack();

    // Yüklenen dosyayı temizle (orphan file bırakma).
    if ($photoPath !== null && file_exists(dirname(__DIR__) . '/' . $photoPath)) {
        unlink(dirname(__DIR__) . '/' . $photoPath);
    }

    // Hata detayını log dosyasına yaz.
    error_log("İlan Ekleme Hatası: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'İlan eklenirken bir hata oluştu. Lütfen tekrar deneyin.'
    ]);
}
