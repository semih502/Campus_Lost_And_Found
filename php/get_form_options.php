<?php
/**
 * ============================================================
 *  get_form_options.php  —  Dinamik Form Seçenekleri
 * ============================================================
 *  Bu dosya, categories ve locations tablolarındaki tüm
 *  kayıtları çekip JSON olarak döndürür. 03_create-ad.html
 *  sayfası bu verileri AJAX ile alarak <select> menülerini
 *  dinamik olarak doldurur.
 *
 *  Yanıt formatı:
 *  {
 *    "success": true,
 *    "categories": [ { "id": 1, "name": "Electronics" }, ... ],
 *    "locations":  [ { "id": 1, "name": "Central Library" }, ... ]
 *  }
 * ============================================================
 */

// ──────────────────────────────────────────────
//  Başlangıç Ayarları
// ──────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

// Veritabanı bağlantısını dahil et.
require_once __DIR__ . '/db_connect.php';

// ──────────────────────────────────────────────
//  Verileri Çek
// ──────────────────────────────────────────────
try {
    // ── Kategorileri çek ──
    // İsimlere göre alfabetik sırala — kullanıcı deneyimi için.
    $stmtCat = $pdo->query("
        SELECT Category_ID AS id, Category_Name AS name
        FROM categories
        ORDER BY Category_Name ASC
    ");
    $categories = $stmtCat->fetchAll();

    // ── Lokasyonları çek ──
    $stmtLoc = $pdo->query("
        SELECT Location_ID AS id, Location_Name AS name
        FROM locations
        ORDER BY Location_Name ASC
    ");
    $locations = $stmtLoc->fetchAll();

    // ── Başarı yanıtını döndür ──
    echo json_encode([
        'success'    => true,
        'categories' => $categories,
        'locations'  => $locations
    ]);

} catch (PDOException $e) {
    // Veritabanı hatası
    error_log("Form Seçenekleri Hatası: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Form verileri yüklenirken bir hata oluştu.'
    ]);
}
