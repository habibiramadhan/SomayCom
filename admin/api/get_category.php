<?php
require_once '../auth_check.php';
hasAccess();

header('Content-Type: application/json');

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID kategori tidak ditemukan');
    }

    $pdo = getDB();
    
    // Ambil data kategori
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        throw new Exception('Kategori tidak ditemukan');
    }

    echo json_encode([
        'success' => true,
        'category' => $category
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 