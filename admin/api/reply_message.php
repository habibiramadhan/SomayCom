<?php
require_once '../auth_check.php';
hasAccess();

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !isset($data['admin_reply'])) {
        throw new Exception('Data tidak lengkap');
    }

    $pdo = getDB();
    $pdo->beginTransaction();

    // Update pesan dengan balasan admin
    $stmt = $pdo->prepare("
        UPDATE contact_messages 
        SET admin_reply = ?,
            replied_by = ?,
            replied_at = CURRENT_TIMESTAMP,
            status = 'replied',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([
        $data['admin_reply'],
        $_SESSION['admin_id'],
        $data['id']
    ]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Pesan tidak ditemukan');
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Balasan berhasil dikirim'
    ]);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 