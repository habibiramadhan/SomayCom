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
    
    // Check if order exists
    $stmt = $pdo->prepare("SELECT id, order_status FROM orders WHERE id = ?");
    $stmt->execute([$data['id']]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception('Pesanan tidak ditemukan');
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Update order status
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET order_status = ?,
                payment_status = ?,
                admin_notes = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $data['order_status'],
            $data['payment_status'],
            $data['admin_notes'] ?? null,
            $data['id']
        ]);

        // Update stock if order is cancelled
        if ($data['order_status'] === 'cancelled' && $order['order_status'] !== 'cancelled') {
            // Get order items
            $stmt = $pdo->prepare("
                SELECT product_id, quantity 
                FROM order_items 
                WHERE order_id = ?
            ");
            $stmt->execute([$data['id']]);
            $items = $stmt->fetchAll();

            // Return items to stock
            foreach ($items as $item) {
                // Update product stock
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET stock_quantity = stock_quantity + ? 
                    WHERE id = ?
                ");
                $stmt->execute([$item['quantity'], $item['product_id']]);

                // Log stock movement
                $stmt = $pdo->prepare("
                    INSERT INTO stock_movements (
                        product_id, movement_type, quantity, 
                        previous_stock, current_stock, 
                        reference_type, reference_id, notes, admin_id
                    ) VALUES (
                        ?, 'in', ?, 
                        (SELECT stock_quantity - ? FROM products WHERE id = ?), 
                        (SELECT stock_quantity FROM products WHERE id = ?),
                        'return', ?, 'Return from cancelled order', ?
                    )
                ");
                $stmt->execute([
                    $item['product_id'],
                    $item['quantity'],
                    $item['quantity'],
                    $item['product_id'],
                    $item['product_id'],
                    $data['id'],
                    $_SESSION['admin_id']
                ]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Status pesanan berhasil diperbarui']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 