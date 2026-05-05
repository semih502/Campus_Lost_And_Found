<?php
/**
 * ============================================================
 *  check_session.php  —  Oturum Kontrolü
 * ============================================================
 *  Bu dosya, kullanıcının aktif bir oturumu olup olmadığını
 *  kontrol eder. Dashboard ve diğer korumalı sayfalar bu
 *  dosyayı AJAX ile çağırarak yetkilendirme kontrolü yapar.
 *
 *  Başarılı yanıt (oturum var):
 *    { "status": "success", "user": { "id", "full_name", "user_type" } }
 *
 *  Başarısız yanıt (oturum yok):
 *    { "status": "error", "message": "..." }
 * ============================================================
 */

// ──────────────────────────────────────────────
//  Başlangıç Ayarları
// ──────────────────────────────────────────────
session_start();
header('Content-Type: application/json; charset=utf-8');

// ──────────────────────────────────────────────
//  Oturum Kontrolü
// ──────────────────────────────────────────────
// login_process.php'de session'a kaydedilen user_id'yi kontrol et.
// Varsa kullanıcı giriş yapmıştır, yoksa yetkisiz erişimdir.
if (isset($_SESSION['user_id'])) {
    // ✅ Oturum aktif — kullanıcı bilgilerini döndür
    echo json_encode([
        'status' => 'success',
        'user'   => [
            'id'        => $_SESSION['user_id'],
            'full_name' => $_SESSION['full_name'],
            'user_type' => $_SESSION['user_type']
        ]
    ]);
} else {
    // ❌ Oturum yok — yetkisiz erişim
    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Oturum bulunamadı. Lütfen giriş yapın.'
    ]);
}
