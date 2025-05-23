<?php
require_once '../auth_check.php';
hasAccess();

header('Content-Type: application/json');

try {
    if (!isset($_GET['phone'])) {
        throw new Exception('Nomor telepon tidak ditemukan');
    }

    $pdo = getDB();
    
    // Ambil data pesanan pelanggan
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            sa.area_name as shipping_area
        FROM orders o
        LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
        WHERE o.customer_phone = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$_GET['phone']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 