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
    
    // Check if category exists and has no products
    $stmt = $pdo->prepare("
        SELECT c.id, COUNT(p.id) as product_count 
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$data['id']]);
    $category = $stmt->fetch();

    if (!$category) {
        throw new Exception('Kategori tidak ditemukan');
    }

    if ($category['product_count'] > 0) {
        throw new Exception('Kategori tidak dapat dihapus karena masih memiliki produk');
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Delete category
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$data['id']]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Kategori berhasil dihapus']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 