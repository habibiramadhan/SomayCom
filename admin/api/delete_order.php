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
    
    // Check if order exists and is pending
    $stmt = $pdo->prepare("SELECT id, order_status FROM orders WHERE id = ?");
    $stmt->execute([$data['id']]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception('Pesanan tidak ditemukan');
    }

    if ($order['order_status'] !== 'pending') {
        throw new Exception('Hanya pesanan dengan status menunggu yang dapat dihapus');
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Delete order items
        $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt->execute([$data['id']]);

        // Delete order
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$data['id']]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Pesanan berhasil dihapus']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 