<?php
require_once '../auth_check.php';
hasAccess();

header('Content-Type: application/json');

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID pesan tidak ditemukan');
    }

    $pdo = getDB();
    
    // Ambil detail pesan
    $stmt = $pdo->prepare("
        SELECT 
            cm.*,
            a.full_name as replied_by_name
        FROM contact_messages cm
        LEFT JOIN admins a ON cm.replied_by = a.id
        WHERE cm.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $message = $stmt->fetch();

    if (!$message) {
        throw new Exception('Pesan tidak ditemukan');
    }

    // Update status pesan menjadi 'read' jika masih 'new'
    if ($message['status'] === 'new') {
        $stmt = $pdo->prepare("
            UPDATE contact_messages 
            SET status = 'read', 
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$_GET['id']]);
        $message['status'] = 'read';
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 