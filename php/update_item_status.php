<?php
/**
 * ============================================================
 *  update_item_status.php  —  İlan Durumu Güncelleme
 * ============================================================
 *  Bir ilanın Status_ID'sini günceller.
 *
 *  POST body: { item_id, action }
 *    action değerleri:
 *      'resolve'  → Status_ID = 3  (Resolved — sahibine teslim edildi)
 *      'reopen'   → Status_ID = 1  (Lost — yeniden aktif)
 *      'reject'   → Status_ID = 4  (Rejected — staff reddetti)
 *
 *  Yetki:
 *    - 'resolve' / 'reopen' : ilanın sahibi veya staff
 *    - 'reject'             : yalnızca staff
 *
 *  Veritabanı:
 *    statuses → 1:Lost | 2:Found | 3:Resolved | 4:Rejected
 * ============================================================
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Bu işlem için giriş yapmanız gerekmektedir.']);
    exit;
}

require_once __DIR__ . '/db_connect.php';

$itemId   = intval($_POST['item_id'] ?? 0);
$action   = trim($_POST['action']   ?? '');
$userId   = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? '';

if ($itemId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz ilan ID.']);
    exit;
}

// Whitelist: sadece izin verilen action değerleri
$allowedActions = ['resolve', 'reopen', 'reject'];
if (!in_array($action, $allowedActions, true)) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz işlem türü.']);
    exit;
}

try {
    // ── İlanı bul ──
    $stmtCheck = $pdo->prepare("
        SELECT Item_ID, User_ID, Status_ID FROM items WHERE Item_ID = :id LIMIT 1
    ");
    $stmtCheck->execute([':id' => $itemId]);
    $item = $stmtCheck->fetch();

    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'İlan bulunamadı.']);
        exit;
    }

    $isOwner = ((int)$item['User_ID'] === (int)$userId);
    $isStaff = ($userType === 'staff');

    // ── Yetki kontrolü ──
    // 'reject' yalnızca staff yetkisindedir.
    if ($action === 'reject' && !$isStaff) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu işlem için staff yetkisi gerekmektedir.']);
        exit;
    }

    // 'resolve' / 'reopen': ilan sahibi veya staff yapabilir.
    if (in_array($action, ['resolve', 'reopen']) && !$isOwner && !$isStaff) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bu işlem için yetkiniz yok.']);
        exit;
    }

    // ── Action → Status_ID eşleştirmesi ──
    $statusMap = [
        'resolve' => 3,   // Resolved
        'reopen'  => 1,   // Lost (yeniden açıldı)
        'reject'  => 4,   // Rejected
    ];
    $newStatusId = $statusMap[$action];

    // ── UPDATE sorgusu ──
    $stmtUpdate = $pdo->prepare("
        UPDATE items SET Status_ID = :status_id WHERE Item_ID = :item_id
    ");
    $stmtUpdate->execute([
        ':status_id' => $newStatusId,
        ':item_id'   => $itemId
    ]);

    $statusNames = [3 => 'Resolved', 1 => 'Lost', 4 => 'Rejected'];
    $messages = [
        'resolve' => 'İlan "Çözümlendi" olarak işaretlendi.',
        'reopen'  => 'İlan yeniden "Kayıp" olarak aktif hale getirildi.',
        'reject'  => 'İlan reddedildi.',
    ];

    echo json_encode([
        'success'     => true,
        'message'     => $messages[$action],
        'new_status'  => $newStatusId,
        'status_name' => $statusNames[$newStatusId]
    ]);

} catch (PDOException $e) {
    error_log("Durum Güncelleme Hatası: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Durum güncellenirken bir hata oluştu.']);
}
