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
    
    // Check if product exists
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
    $stmt->execute([$data['id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Produk tidak ditemukan');
    }

    // Check if product has orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
    $stmt->execute([$data['id']]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Produk tidak dapat dihapus karena sudah memiliki pesanan');
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Delete product images
        $stmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
        $stmt->execute([$data['id']]);

        // Delete stock movements
        $stmt = $pdo->prepare("DELETE FROM stock_movements WHERE product_id = ?");
        $stmt->execute([$data['id']]);

        // Delete product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$data['id']]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Produk berhasil dihapus']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 