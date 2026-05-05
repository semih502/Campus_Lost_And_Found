<?php
/**
 * ============================================================
 *  get_listings.php — İlanları Listele (Ana Sayfa)
 * ============================================================
 *  items tablosunu categories, locations, statuses ve
 *  item_photos ile JOIN ederek tüm ilanları döndürür.
 *  En yeniler en üstte: ORDER BY Created_At DESC
 *
 *  Yanıt: { "success": true, "listings": [...] }
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

try {
    $stmt = $pdo->query("
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
            -- Fotoğraf yoksa NULL döner (LEFT JOIN)
            (SELECT Photo_Path FROM item_photos 
             WHERE item_photos.Item_ID = i.Item_ID 
             LIMIT 1) AS photo
        FROM items i
        INNER JOIN categories c ON i.Category_ID = c.Category_ID
        INNER JOIN locations  l ON i.Location_ID = l.Location_ID
        INNER JOIN statuses   s ON i.Status_ID   = s.Status_ID
        INNER JOIN users      u ON i.User_ID     = u.User_ID
        ORDER BY i.Created_At DESC
    ");

    $listings = $stmt->fetchAll();

    echo json_encode([
        'success'  => true,
        'listings' => $listings
    ]);

} catch (PDOException $e) {
    error_log("İlan Listeleme Hatası: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'İlanlar yüklenirken bir hata oluştu.'
    ]);
}
