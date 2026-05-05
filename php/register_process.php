<?php
/**
 * ============================================================
 *  register_process.php  —  Kullanıcı Kayıt İşlemi
 * ============================================================
 *  Bu dosya, 04_auth.html sayfasındaki kayıt formundan
 *  AJAX (fetch API) ile gönderilen verileri işler.
 *
 *  İş Akışı:
 *    1. POST verilerini al ve filtrele (sanitize).
 *    2. Sunucu tarafı doğrulama (validation) yap.
 *    3. E-posta tekrar kontrolü (duplicate check).
 *    4. Şifreyi SHA-256 ile hash'le.
 *    5. PDO Transaction ile:
 *       a) users tablosuna ana kaydı ekle.
 *       b) lastInsertId() ile dönen User_ID'yi kullanarak
 *          students veya staff tablosuna alt-tip kaydını ekle.
 *    6. İşlem sonucunu JSON formatında döndür.
 *
 *  Veritabanı Şeması (Üsttip-Alttip / Supertype-Subtype):
 *    users    → User_ID (PK), Full_Name, Email, Password, User_Type
 *    students → User_ID (PK, FK → users), Student_Number
 *    staff    → User_ID (PK, FK → users), Staff_Number
 * ============================================================
 */

// ──────────────────────────────────────────────
//  0) Başlangıç Ayarları
// ──────────────────────────────────────────────
// Yanıt tipini JSON olarak ayarla — tüm senaryolarda
// (başarılı/başarısız) frontend tutarlı JSON bekler.
header('Content-Type: application/json; charset=utf-8');

// Sadece POST isteklerini kabul et.
// GET veya diğer HTTP metodlarıyla erişimi engelle.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz istek metodu. Sadece POST kabul edilir.'
    ]);
    exit;
}

// Veritabanı bağlantısını dahil et.
require_once __DIR__ . '/db_connect.php';

// ──────────────────────────────────────────────
//  1) Form Verilerini Al ve Filtrele (Sanitize)
// ──────────────────────────────────────────────
// trim()    → başındaki ve sonundaki boşlukları temizler.
// filter_var() → e-posta formatını doğrular.
// htmlspecialchars() → XSS (Cross-Site Scripting) saldırılarını önler.
$full_name  = trim(htmlspecialchars($_POST['full_name']  ?? '', ENT_QUOTES, 'UTF-8'));
$email      = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
$user_type  = trim($_POST['user_type']  ?? '');
$official_id = trim($_POST['official_id'] ?? '');
$password   = $_POST['password']         ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// ──────────────────────────────────────────────
//  2) Sunucu Tarafı Doğrulama (Validation)
// ──────────────────────────────────────────────
// Frontend'de zaten doğrulama var, ama güvenlik için
// aynı kontrolleri sunucu tarafında da yapıyoruz.
// "Never trust user input" prensibi.

// 2a) Boş alan kontrolü
if (empty($full_name) || empty($email) || empty($user_type) || empty($official_id) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Lütfen tüm alanları doldurun.'
    ]);
    exit;
}

// 2b) E-posta format kontrolü
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Geçerli bir e-posta adresi girin.'
    ]);
    exit;
}

// 2c) Kullanıcı tipi kontrolü — sadece "student" veya "staff" kabul et
if (!in_array($user_type, ['student', 'staff'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz kullanıcı tipi seçildi.'
    ]);
    exit;
}

// 2d) Öğrenci/Personel numarası — tam olarak 10 rakam olmalı
if (!preg_match('/^\d{10}$/', $official_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Numara tam olarak 10 haneli olmalıdır.'
    ]);
    exit;
}

// 2e) Şifre uzunluk kontrolü — minimum 8 karakter
if (strlen($password) < 8) {
    echo json_encode([
        'success' => false,
        'message' => 'Şifre en az 8 karakter olmalıdır.'
    ]);
    exit;
}

// 2f) Şifre eşleşme kontrolü
if ($password !== $password_confirm) {
    echo json_encode([
        'success' => false,
        'message' => 'Şifreler eşleşmiyor.'
    ]);
    exit;
}

// ──────────────────────────────────────────────
//  3) E-posta Tekrar Kontrolü (Duplicate Check)
// ──────────────────────────────────────────────
// Aynı e-posta ile daha önce kayıt olunmuş mu?
// Prepared statement ile SQL Injection'a karşı güvenli sorgu.
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE Email = :email");
$stmt->execute([':email' => $email]);

if ($stmt->fetchColumn() > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Bu e-posta adresi zaten kayıtlı.'
    ]);
    exit;
}

// ──────────────────────────────────────────────
//  4) Şifreleme — SHA-256
// ──────────────────────────────────────────────
// Proje gereksinimi olarak SHA-256 algoritması kullanılıyor.
// hash() fonksiyonu ile şifre tek yönlü olarak şifrelenir.
// Not: Üretim ortamında password_hash(PASSWORD_BCRYPT) tercih edilir.
$hashed_password = hash('sha256', $password);

// ──────────────────────────────────────────────
//  5) Veritabanına Kayıt — PDO Transaction
// ──────────────────────────────────────────────
// Transaction sayesinde iki INSERT işlemi "ya hep ya hiç"
// (atomik) olarak çalışır. Biri başarısız olursa diğeri
// de geri alınır (rollback). Bu, veritabanı tutarlılığını korur.
try {
    // Transaction'ı başlat
    $pdo->beginTransaction();

    // ── 5a) users tablosuna ana kaydı ekle ──
    // Üsttip (supertype) tablosu: tüm kullanıcıların ortak bilgileri
    $stmtUser = $pdo->prepare("
        INSERT INTO users (Full_Name, Email, Password, User_Type)
        VALUES (:full_name, :email, :password, :user_type)
    ");
    $stmtUser->execute([
        ':full_name' => $full_name,
        ':email'     => $email,
        ':password'  => $hashed_password,
        ':user_type' => $user_type
    ]);

    // Yeni eklenen kullanıcının otomatik artan ID'sini al.
    // Bu ID, alt-tip tablosunda FK olarak kullanılacak.
    $newUserId = $pdo->lastInsertId();

    // ── 5b) Alt-tip tablosuna kaydı ekle ──
    // Kullanıcının seçtiği tipe göre ilgili tabloya ekleme yap.
    if ($user_type === 'student') {
        // Öğrenci → students tablosuna ekle
        $stmtSub = $pdo->prepare("
            INSERT INTO students (User_ID, Student_Number)
            VALUES (:user_id, :student_number)
        ");
        $stmtSub->execute([
            ':user_id'        => $newUserId,
            ':student_number' => $official_id
        ]);
    } else {
        // Personel → staff tablosuna ekle
        $stmtSub = $pdo->prepare("
            INSERT INTO staff (User_ID, Staff_Number)
            VALUES (:user_id, :staff_number)
        ");
        $stmtSub->execute([
            ':user_id'      => $newUserId,
            ':staff_number' => $official_id
        ]);
    }

    // Her iki INSERT de başarılı → Transaction'ı onayla (commit).
    $pdo->commit();

    // ── 5c) Başarı yanıtını JSON olarak döndür ──
    echo json_encode([
        'success' => true,
        'message' => 'Hesabınız başarıyla oluşturuldu! Şimdi giriş yapabilirsiniz.'
    ]);

} catch (PDOException $e) {
    // Hata oluştu → yapılan tüm değişiklikleri geri al.
    $pdo->rollBack();

    // Hata detayını sunucu loguna yaz (kullanıcıya gösterme — güvenlik).
    error_log("Kayıt Hatası: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.'
    ]);
}
