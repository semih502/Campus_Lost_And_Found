<?php
/**
 * ============================================================
 *  send_message.php — Mesaj Gönder
 * ============================================================
 *  POST ile gelen receiver_id, item_id ve content verilerini
 *  alarak messages tablosuna INSERT eder.
 *  Gönderici: $_SESSION['user_id']
 *
 *  POST body: { receiver_id, item_id, content }
 * ============================================================
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// ── Method kontrolü ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu.']);
    exit;
}

// ── Session kontrolü ──
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Mesaj göndermek için giriş yapmalısınız.']);
    exit;
}

require_once __DIR__ . '/db_connect.php';

// ── Verileri al ve filtrele ──
$senderId   = $_SESSION['user_id'];
$receiverId = intval($_POST['receiver_id'] ?? 0);
$itemId     = intval($_POST['item_id']     ?? 0);
$content    = trim(htmlspecialchars($_POST['content'] ?? '', ENT_QUOTES, 'UTF-8'));

// ── Validation ──
if ($receiverId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz alıcı.']);
    exit;
}

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Mesaj içeriği boş olamaz.']);
    exit;
}

if (mb_strlen($content) > 500) {
    echo json_encode(['success' => false, 'message' => 'Mesaj en fazla 500 karakter olabilir.']);
    exit;
}

// Kendine mesaj gönderme kontrolü
if ($senderId === $receiverId) {
    echo json_encode(['success' => false, 'message' => 'Kendinize mesaj gönderemezsiniz.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO messages (Sender_ID, Receiver_ID, Item_ID, Content)
        VALUES (:sender_id, :receiver_id, :item_id, :content)
    ");
    $stmt->execute([
        ':sender_id'   => $senderId,
        ':receiver_id' => $receiverId,
        ':item_id'     => $itemId > 0 ? $itemId : null,
        ':content'     => $content
    ]);

    echo json_encode([
        'success'    => true,
        'message'    => 'Mesajınız gönderildi!',
        'message_id' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    error_log("Mesaj Gönderim Hatası: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Mesaj gönderilirken bir hata oluştu.'
    ]);
}
