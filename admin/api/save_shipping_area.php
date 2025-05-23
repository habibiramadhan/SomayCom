<?php
require_once '../auth_check.php';
hasAccess();

header('Content-Type: application/json');

try {
    // Get JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['area_name']) || !isset($data['shipping_cost']) || !isset($data['estimated_delivery'])) {
        throw new Exception('Data tidak lengkap');
    }

    $pdo = getDB();
    
    // Begin transaction
    $pdo->beginTransaction();

    try {
        if (isset($data['id'])) {
            // Update existing area
            $stmt = $pdo->prepare("
                UPDATE shipping_areas 
                SET area_name = ?,
                    postal_code = ?,
                    shipping_cost = ?,
                    estimated_delivery = ?,
                    is_active = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $data['area_name'],
                $data['postal_code'] ?? null,
                $data['shipping_cost'],
                $data['estimated_delivery'],
                $data['is_active'],
                $data['id']
            ]);
            $message = 'Area pengiriman berhasil diperbarui';
        } else {
            // Insert new area
            $stmt = $pdo->prepare("
                INSERT INTO shipping_areas (area_name, postal_code, shipping_cost, estimated_delivery, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['area_name'],
                $data['postal_code'] ?? null,
                $data['shipping_cost'],
                $data['estimated_delivery'],
                $data['is_active']
            ]);
            $message = 'Area pengiriman berhasil ditambahkan';
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => $message]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 