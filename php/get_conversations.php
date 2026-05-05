<?php
/**
 * ============================================================
 *  get_conversations.php — Konuşma Listesi (Sol Menü)
 * ============================================================
 *  Giriş yapmış kullanıcının mesajlaştığı kişileri,
 *  son mesaj önizlemesiyle birlikte döndürür.
 *
 *  Her konuşma: partner_id, partner_name, last_message,
 *               last_time, item_id, item_title
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

$userId = $_SESSION['user_id'];

try {
    // Her konuşma partneri için en son mesajı bul.
    // Kullanıcının gönderdiği VEYA aldığı mesajları grupla.
    $stmt = $pdo->prepare("
        SELECT 
            partner_id,
            partner_name,
            last_message,
            last_time,
            item_id,
            item_title
        FROM (
            SELECT
                CASE 
                    WHEN m.Sender_ID = :uid1 THEN m.Receiver_ID
                    ELSE m.Sender_ID
                END AS partner_id,
                CASE
                    WHEN m.Sender_ID = :uid2 THEN r.Full_Name
                    ELSE s.Full_Name
                END AS partner_name,
                m.Content    AS last_message,
                m.Created_At AS last_time,
                m.Item_ID    AS item_id,
                i.Title      AS item_title,
                ROW_NUMBER() OVER (
                    PARTITION BY 
                        CASE WHEN m.Sender_ID = :uid3 THEN m.Receiver_ID ELSE m.Sender_ID END
                    ORDER BY m.Created_At DESC
                ) AS rn
            FROM messages m
            INNER JOIN users s ON m.Sender_ID   = s.User_ID
            INNER JOIN users r ON m.Receiver_ID = r.User_ID
            LEFT  JOIN items i ON m.Item_ID     = i.Item_ID
            WHERE m.Sender_ID = :uid4 OR m.Receiver_ID = :uid5
        ) sub
        WHERE rn = 1
        ORDER BY last_time DESC
    ");

    $stmt->execute([
        ':uid1' => $userId,
        ':uid2' => $userId,
        ':uid3' => $userId,
        ':uid4' => $userId,
        ':uid5' => $userId
    ]);

    $conversations = $stmt->fetchAll();

    echo json_encode([
        'success'       => true,
        'conversations' => $conversations,
        'current_user'  => $userId
    ]);

} catch (PDOException $e) {
    error_log("Konuşma Listesi Hatası: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Konuşmalar yüklenirken bir hata oluştu.'
    ]);
}
