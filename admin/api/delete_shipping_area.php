<?php
require_once '../auth_check.php';
hasAccess();

header('Content-Type: application/json');

try {
    // Get JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['id'])) {
        throw new Exception('Invalid request data');
    }

    $pdo = getDB();
    
    // Check if area exists and has no orders
    $stmt = $pdo->prepare("
        SELECT sa.id, COUNT(o.id) as order_count 
        FROM shipping_areas sa
        LEFT JOIN orders o ON sa.id = o.shipping_area_id
        WHERE sa.id = ?
        GROUP BY sa.id
    ");
    $stmt->execute([$data['id']]);
    $area = $stmt->fetch();

    if (!$area) {
        throw new Exception('Area tidak ditemukan');
    }

    if ($area['order_count'] > 0) {
        throw new Exception('Area tidak dapat dihapus karena masih memiliki pesanan');
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Delete area
        $stmt = $pdo->prepare("DELETE FROM shipping_areas WHERE id = ?");
        $stmt->execute([$data['id']]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Area pengiriman berhasil dihapus']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 