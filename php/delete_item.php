<?php
/**
 * ============================================================
 *  delete_item.php  —  İlan Silme İşlemi
 * ============================================================
 *  Giriş yapmış kullanıcının kendi ilanını siler.
 *  Staff (personel) herhangi bir ilanı silebilir.
 *
 *  POST body: { item_id }
 *
 *  İş Akışı:
 *    1. Session kontrolü — giriş yapılmış mı?
 *    2. item_id parametresini doğrula (intval).
 *    3. İlanın var olduğunu ve kullanıcıya ait olduğunu kontrol et.
 *       (Staff ise sahiplik kontrolü atlanır.)
 *    4. DELETE FROM items WHERE Item_ID = :id
 *       (ON DELETE CASCADE → photos ve mesajlar otomatik silinir)
 *    5. JSON yanıt döndür.
 *
 *  Veritabanı: items (Item_ID PK, User_ID FK)
 * ============================================================
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// ── Sadece POST kabul et ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu.']);
    exit;
}

// ── Oturum kontrolü ──
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Bu işlem için giriş yapmanız gerekmektedir.']);
    exit;
}

require_once __DIR__ . '/db_connect.php';

// ── Parametre doğrulama ──
$itemId   = intval($_POST['item_id'] ?? 0);
$userId   = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? '';

if ($itemId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz ilan ID.']);
    exit;
}

try {
    // ── İlanı bul ──
    $stmtCheck = $pdo->prepare("
        SELECT Item_ID, User_ID FROM items WHERE Item_ID = :id LIMIT 1
    ");
    $stmtCheck->execute([':id' => $itemId]);
    $item = $stmtCheck->fetch();

    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'İlan bulunamadı.']);
        exit;
    }

    // ── Yetki kontrolü ──
    // Sadece ilanın sahibi veya staff silebilir.
    if ($item['User_ID'] !== $userId && $userType !== 'staff') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu ilanı silme yetkiniz yok.']);
        exit;
    }

    // ── İlanı sil ──
    // ON DELETE CASCADE sayesinde item_photos ve messages tablosundaki
    // ilgili kayıtlar otomatik olarak silinir.
    $stmtDelete = $pdo->prepare("DELETE FROM items WHERE Item_ID = :id");
    $stmtDelete->execute([':id' => $itemId]);

    echo json_encode([
        'success' => true,
        'message' => 'İlan başarıyla silindi.',
        'redirect' => '05_dashboard.html'
    ]);

} catch (PDOException $e) {
    error_log("İlan Silme Hatası: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'İlan silinirken bir hata oluştu.']);
}
