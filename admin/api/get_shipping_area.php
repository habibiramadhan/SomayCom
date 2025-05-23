<?php
require_once '../auth_check.php';
hasAccess();

header('Content-Type: application/json');

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID area tidak ditemukan');
    }

    $pdo = getDB();
    
    // Ambil data area
    $stmt = $pdo->prepare("SELECT * FROM shipping_areas WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $area = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$area) {
        throw new Exception('Area tidak ditemukan');
    }

    echo json_encode([
        'success' => true,
        'area' => $area
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 