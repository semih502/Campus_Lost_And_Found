<?php
/**
 * ============================================================
 *  get_item_details.php — İlan Detayı
 * ============================================================
 *  URL'den gelen ?id=X parametresine göre ilgili ilanın
 *  tüm bilgilerini (kategori, konum, durum, sahibi, fotoğraf)
 *  JSON olarak döndürür.
 *
 *  Kullanım: GET php/get_item_details.php?id=5
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

// ── Parametre kontrolü ──
$itemId = intval($_GET['id'] ?? 0);

if ($itemId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz ilan ID.'
    ]);
    exit;
}

try {
    // ── İlan bilgilerini çek ──
    $stmt = $pdo->prepare("
        SELECT 
            i.Item_ID       AS id,
            i.Title         AS title,
            i.Description   AS description,
            i.Created_At    AS created_at,
            i.User_ID       AS user_id,
            c.Category_Name AS category,
            l.Location_Name AS location,
            s.Status_Name   AS status,
            u.Full_Name     AS owner_name,
            u.Created_At    AS member_since
        FROM items i
        INNER JOIN categories c ON i.Category_ID = c.Category_ID
        INNER JOIN locations  l ON i.Location_ID = l.Location_ID
        INNER JOIN statuses   s ON i.Status_ID   = s.Status_ID
        INNER JOIN users      u ON i.User_ID     = u.User_ID
        WHERE i.Item_ID = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        echo json_encode([
            'success' => false,
            'message' => 'İlan bulunamadı.'
        ]);
        exit;
    }

    // ── Fotoğrafları çek (1:N ilişki) ──
    $stmtPhotos = $pdo->prepare("
        SELECT Photo_Path AS path
        FROM item_photos
        WHERE Item_ID = :id
        ORDER BY Uploaded_At ASC
    ");
    $stmtPhotos->execute([':id' => $itemId]);
    $item['photos'] = $stmtPhotos->fetchAll();

    echo json_encode([
        'success' => true,
        'item'    => $item
    ]);

} catch (PDOException $e) {
    error_log("İlan Detay Hatası: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'İlan detayları yüklenirken bir hata oluştu.'
    ]);
}
