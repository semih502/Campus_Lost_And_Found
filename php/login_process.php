<?php
/**
 * ============================================================
 *  login_process.php  —  Kullanıcı Giriş İşlemi
 * ============================================================
 *  Bu dosya, 04_auth.html sayfasındaki giriş formundan
 *  AJAX (fetch API) ile gönderilen e-posta ve şifreyi doğrular.
 *
 *  İş Akışı:
 *    1. POST verilerini al ve filtrele.
 *    2. Sunucu tarafı doğrulama yap.
 *    3. E-posta ile veritabanında kullanıcıyı ara.
 *    4. SHA-256 hash'lenmiş şifreyi karşılaştır.
 *    5. Başarılıysa PHP Session başlat ve kullanıcı bilgilerini
 *       session'a kaydet.
 *    6. Sonucu JSON formatında döndür.
 * ============================================================
 */

// ──────────────────────────────────────────────
//  0) Başlangıç Ayarları
// ──────────────────────────────────────────────
// Session'ı başlat — giriş başarılı olduğunda kullanıcı
// bilgilerini session'a kaydedeceğiz.
session_start();

// Yanıt tipini JSON olarak ayarla.
header('Content-Type: application/json; charset=utf-8');

// Sadece POST isteklerini kabul et.
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
//  1) Form Verilerini Al ve Filtrele
// ──────────────────────────────────────────────
$email    = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
$password = $_POST['password'] ?? '';

// ──────────────────────────────────────────────
//  2) Sunucu Tarafı Doğrulama
// ──────────────────────────────────────────────
// 2a) Boş alan kontrolü
if (empty($email) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'E-posta ve şifre alanları zorunludur.'
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

// ──────────────────────────────────────────────
//  3) Kullanıcıyı Veritabanında Ara
// ──────────────────────────────────────────────
// E-posta adresine göre kullanıcıyı sorgula.
// Prepared statement ile SQL Injection'a karşı güvenli.
try {
    $stmt = $pdo->prepare("
        SELECT User_ID, Full_Name, Email, Password, User_Type
        FROM users
        WHERE Email = :email
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);

    // Kullanıcı bulunamadıysa
    $user = $stmt->fetch();

    if (!$user) {
        // Güvenlik notu: "E-posta bulunamadı" yerine genel bir mesaj
        // vererek, saldırganların hangi e-postaların kayıtlı olduğunu
        // öğrenmesini engelliyoruz (enumeration koruması).
        echo json_encode([
            'success' => false,
            'message' => 'E-posta veya şifre hatalı.'
        ]);
        exit;
    }

    // ──────────────────────────────────────────────
    //  4) Şifre Doğrulama — SHA-256 Karşılaştırma
    // ──────────────────────────────────────────────
    // Kullanıcının girdiği şifreyi SHA-256 ile hash'leyip
    // veritabanındaki hash ile karşılaştır.
    $hashed_input = hash('sha256', $password);

    if ($hashed_input !== $user['Password']) {
        echo json_encode([
            'success' => false,
            'message' => 'E-posta veya şifre hatalı.'
        ]);
        exit;
    }

    // ──────────────────────────────────────────────
    //  5) Giriş Başarılı — Session'a Kaydet
    // ──────────────────────────────────────────────
    // Session hijacking'e karşı yeni bir session ID oluştur.
    // Bu, oturum sabitleme (session fixation) saldırılarını önler.
    session_regenerate_id(true);

    // Kullanıcı bilgilerini session'a kaydet.
    // Bu bilgiler diğer sayfalarda yetkilendirme kontrolü için kullanılır.
    $_SESSION['user_id']   = $user['User_ID'];    // Kullanıcı ID'si
    $_SESSION['full_name'] = $user['Full_Name'];   // Ad Soyad
    $_SESSION['email']     = $user['Email'];       // E-posta
    $_SESSION['user_type'] = $user['User_Type'];   // Rol: student / staff

    // ──────────────────────────────────────────────
    //  6) Başarı Yanıtını JSON Olarak Döndür
    // ──────────────────────────────────────────────
    echo json_encode([
        'success'   => true,
        'message'   => 'Giriş başarılı! Yönlendiriliyorsunuz...',
        'user'      => [
            'id'        => $user['User_ID'],
            'full_name' => $user['Full_Name'],
            'user_type' => $user['User_Type']
        ],
        'redirect'  => '05_dashboard.html' // Frontend bu URL'ye yönlendirecek
    ]);

} catch (PDOException $e) {
    // Beklenmeyen veritabanı hatası
    error_log("Giriş Hatası: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Giriş sırasında bir hata oluştu. Lütfen tekrar deneyin.'
    ]);
}
