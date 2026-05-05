<?php
/**
 * ============================================================
 *  get_messages.php — Sohbet Mesaj Geçmişi
 * ============================================================
 *  Seçilen konuşma partnerinin mesaj geçmişini döndürür.
 *  Kullanım: GET php/get_messages.php?partner_id=5
 *
 *  Ayrıca okunmamış mesajları okundu olarak işaretler.
 * ============================================================
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// ── Session kontrolü ──
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Giriş yapmanız gerekmektedir.']);
    exit;
}

require_once __DIR__ . '/db_connect.php';

$userId    = $_SESSION['user_id'];
$partnerId = intval($_GET['partner_id'] ?? 0);

if ($partnerId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz konuşma partneri.']);
    exit;
}

try {
    // ── İki kullanıcı arasındaki tüm mesajları çek ──
    $stmt = $pdo->prepare("
        SELECT 
            m.Message_ID  AS id,
            m.Sender_ID   AS sender_id,
            m.Receiver_ID AS receiver_id,
            m.Content     AS content,
            m.Created_At  AS created_at,
            m.Is_Read     AS is_read,
            s.Full_Name   AS sender_name
        FROM messages m
        INNER JOIN users s ON m.Sender_ID = s.User_ID
        WHERE 
            (m.Sender_ID = :uid1 AND m.Receiver_ID = :pid1)
            OR
            (m.Sender_ID = :pid2 AND m.Receiver_ID = :uid2)
        ORDER BY m.Created_At ASC
    ");
    $stmt->execute([
        ':uid1' => $userId,
        ':pid1' => $partnerId,
        ':pid2' => $partnerId,
        ':uid2' => $userId
    ]);

    $messages = $stmt->fetchAll();

    // ── Okunmamış mesajları okundu olarak işaretle ──
    // Sadece partner'dan bize gelen okunmamış mesajları güncelle.
    $stmtRead = $pdo->prepare("
        UPDATE messages 
        SET Is_Read = 1 
        WHERE Sender_ID = :pid AND Receiver_ID = :uid AND Is_Read = 0
    ");
    $stmtRead->execute([
        ':pid' => $partnerId,
        ':uid' => $userId
    ]);

    echo json_encode([
        'success'      => true,
        'messages'     => $messages,
        'current_user' => $userId
    ]);

} catch (PDOException $e) {
    error_log("Mesaj Geçmişi Hatası: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Mesajlar yüklenirken bir hata oluştu.'
    ]);
}
