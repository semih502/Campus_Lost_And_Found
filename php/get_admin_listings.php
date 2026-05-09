<?php
/**
 * ============================================================
 *  get_admin_listings.php  —  Admin Paneli İlan Verileri
 * ============================================================
 *  07_admin.html için ilanları döndürür.
 *  Yalnızca staff kullanıcıları erişebilir.
 *
 *  GET parametresi (opsiyonel):
 *    ?status=lost      → Status_ID = 1
 *    ?status=found     → Status_ID = 2
 *    ?status=resolved  → Status_ID = 3
 *    ?status=rejected  → Status_ID = 4
 *    (parametre yoksa tüm ilanlar)
 *
 *  Yanıt:
 *  {
 *    "success": true,
 *    "listings": [...],
 *    "stats": { "lost", "found", "resolved", "rejected", "total" }
 *  }
 * ============================================================
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// ── Oturum ve staff yetki kontrolü ──
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Giriş yapmanız gerekmektedir.']);
    exit;
}

if ($_SESSION['user_type'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Bu sayfaya erişim için staff yetkisi gerekmektedir.']);
    exit;
}

require_once __DIR__ . '/db_connect.php';

try {
    // ── İstatistikler ──
    $stmtStats = $pdo->query("
        SELECT
            SUM(CASE WHEN Status_ID = 1 THEN 1 ELSE 0 END) AS lost_count,
            SUM(CASE WHEN Status_ID = 2 THEN 1 ELSE 0 END) AS found_count,
            SUM(CASE WHEN Status_ID = 3 THEN 1 ELSE 0 END) AS resolved_count,
            SUM(CASE WHEN Status_ID = 4 THEN 1 ELSE 0 END) AS rejected_count,
            COUNT(*) AS total
        FROM items
    ");
    $stats = $stmtStats->fetch();

    // ── Durum filtresi ──
    // status GET parametresi → ilgili Status_ID filtresi
    $statusFilter = strtolower(trim($_GET['status'] ?? ''));
    $statusIdMap  = [
        'lost'     => 1,
        'found'    => 2,
        'resolved' => 3,
        'rejected' => 4,
    ];

    $whereClause = '';
    $params      = [];

    if (array_key_exists($statusFilter, $statusIdMap)) {
        $whereClause    = 'WHERE i.Status_ID = :status_id';
        $params[':status_id'] = $statusIdMap[$statusFilter];
    }

    // ── İlanları çek ──
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
            s.Status_ID     AS status_id,
            u.Full_Name     AS owner_name,
            u.Email         AS owner_email,
            u.User_Type     AS owner_type,
            (SELECT Photo_Path FROM item_photos
             WHERE item_photos.Item_ID = i.Item_ID LIMIT 1) AS photo
        FROM items i
        INNER JOIN categories c ON i.Category_ID = c.Category_ID
        INNER JOIN locations  l ON i.Location_ID = l.Location_ID
        INNER JOIN statuses   s ON i.Status_ID   = s.Status_ID
        INNER JOIN users      u ON i.User_ID     = u.User_ID
        $whereClause
        ORDER BY i.Created_At DESC
    ");
    $stmt->execute($params);
    $listings = $stmt->fetchAll();

    echo json_encode([
        'success'  => true,
        'listings' => $listings,
        'stats'    => [
            'lost'     => (int)($stats['lost_count']     ?? 0),
            'found'    => (int)($stats['found_count']    ?? 0),
            'resolved' => (int)($stats['resolved_count'] ?? 0),
            'rejected' => (int)($stats['rejected_count'] ?? 0),
            'total'    => (int)($stats['total']          ?? 0),
        ]
    ]);

} catch (PDOException $e) {
    error_log("Admin Listeleme Hatası: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veriler yüklenirken bir hata oluştu.']);
}
