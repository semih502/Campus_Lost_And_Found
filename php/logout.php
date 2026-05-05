<?php
/**
 * ============================================================
 *  logout.php  —  Çıkış İşlemi (Session Destroy)
 * ============================================================
 *  Kullanıcının oturumunu sonlandırır.
 *  Dashboard'daki "Logout" butonu bu dosyayı çağırır.
 *
 *  İşlem: session_start() → session verilerini temizle →
 *         session'ı yok et → JSON başarı yanıtı döndür.
 * ============================================================
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// ── Session verilerini temizle ──
// $_SESSION dizisindeki tüm verileri siler.
$_SESSION = [];

// ── Session çerezini (cookie) de temizle ──
// Tarayıcıdaki session çerezini geçmiş tarihle sileriz.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),        // Çerez adı (genellikle PHPSESSID)
        '',                    // Boş değer
        time() - 42000,        // Geçmiş tarih — çerezi sil
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ── Session'ı yok et ──
session_destroy();

echo json_encode([
    'success' => true,
    'message' => 'Oturum başarıyla kapatıldı.'
]);
