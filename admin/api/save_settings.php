<?php
require_once '../auth_check.php';
hasAccess();

header('Content-Type: application/json');

try {
    // Get JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        throw new Exception('Data tidak valid');
    }

    $pdo = getDB();
    
    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Update each setting
        $stmt = $pdo->prepare("
            UPDATE app_settings 
            SET setting_value = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE setting_key = ?
        ");

        foreach ($data as $key => $value) {
            $stmt->execute([$value, $key]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Pengaturan berhasil disimpan']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 