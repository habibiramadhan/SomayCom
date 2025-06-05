<!-- admin/orders/process.php -->
<?php
// Order process handler
$processAction = $_POST['action'] ?? '';

switch ($processAction) {
    case 'update_status':
        updateOrderStatus();
        break;
    case 'export':
        exportOrders();
        break;
    default:
        redirectWithMessage('orders.php', 'Aksi tidak valid', 'error');
}

/**
 * Update order status
 */
function updateOrderStatus() {
    header('Content-Type: application/json');
    
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $adminNotes = trim($_POST['admin_notes'] ?? '');
    
    if (!$orderId || !$newStatus) {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
        exit;
    }
    
    $validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($newStatus, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
        exit;
    }
    
    try {
        $pdo = getDB();
        
        // Get current order
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan']);
            exit;
        }
        
        // Validate status transition
        $validTransitions = getValidStatusTransitions($order['order_status']);
        if (!in_array($newStatus, $validTransitions)) {
            echo json_encode(['success' => false, 'message' => 'Transisi status tidak valid']);
            exit;
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Prepare update data
        $updateFields = ['order_status = ?'];
        $updateParams = [$newStatus];
        
        // Set timestamp fields based on status
        switch ($newStatus) {
            case 'confirmed':
                $updateFields[] = 'confirmed_at = CURRENT_TIMESTAMP';
                // Auto set payment status to paid for confirmed orders
                if ($order['payment_status'] === 'pending') {
                    $updateFields[] = 'payment_status = ?';
                    $updateParams[] = 'paid';
                }
                break;
            case 'shipped':
                $updateFields[] = 'shipped_at = CURRENT_TIMESTAMP';
                break;
            case 'delivered':
                $updateFields[] = 'delivered_at = CURRENT_TIMESTAMP';
                // Auto set payment status to paid for delivered orders
                if ($order['payment_status'] === 'pending') {
                    $updateFields[] = 'payment_status = ?';
                    $updateParams[] = 'paid';
                }
                break;
        }
        
        // Add admin notes if provided
        if (!empty($adminNotes)) {
            $currentNotes = $order['admin_notes'];
            $newNotes = $currentNotes ? $currentNotes . "\n\n" . date('Y-m-d H:i:s') . " - " . $adminNotes : date('Y-m-d H:i:s') . " - " . $adminNotes;
            $updateFields[] = 'admin_notes = ?';
            $updateParams[] = $newNotes;
        }
        
        $updateFields[] = 'updated_at = CURRENT_TIMESTAMP';
        $updateParams[] = $orderId;
        
        // Update order
        $sql = "UPDATE orders SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($updateParams);
        
        if ($success) {
            // Handle stock restoration for cancelled orders
            if ($newStatus === 'cancelled' && $order['order_status'] !== 'cancelled') {
                restoreOrderStock($orderId);
            }
            
            // Handle stock deduction for confirmed orders
            if ($newStatus === 'confirmed' && $order['order_status'] === 'pending') {
                deductOrderStock($orderId);
            }
            
            // Log activity
            $statusTexts = [
                'pending' => 'Menunggu',
                'confirmed' => 'Dikonfirmasi',
                'processing' => 'Diproses',
                'shipped' => 'Dikirim',
                'delivered' => 'Selesai',
                'cancelled' => 'Dibatalkan'
            ];
            
            $statusText = $statusTexts[$newStatus] ?? $newStatus;
            logActivity('order_status_update', "Updated order {$order['order_number']} status to {$statusText}", $_SESSION['admin_id']);
            
            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'message' => 'Status pesanan berhasil diupdate',
                'new_status' => $newStatus
            ]);
        } else {
            throw new Exception('Failed to update order status');
        }
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log("Error updating order status: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * Get valid status transitions
 */
function getValidStatusTransitions($currentStatus) {
    $transitions = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'shipped', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'cancelled'],
        'delivered' => [], // No transitions from delivered
        'cancelled' => [] // No transitions from cancelled
    ];
    
    return $transitions[$currentStatus] ?? [];
}

/**
 * Restore stock when order is cancelled
 */
function restoreOrderStock($orderId) {
    $pdo = getDB();
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.product_id, oi.quantity, p.name as product_name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll();
    
    foreach ($orderItems as $item) {
        // Get current stock
        $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stmt->execute([$item['product_id']]);
        $currentStock = $stmt->fetchColumn();
        
        if ($currentStock !== false) {
            $newStock = $currentStock + $item['quantity'];
            
            // Update stock
            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$newStock, $item['product_id']]);
            
            // Log stock movement
            logStockMovement(
                $item['product_id'], 
                'in', 
                $item['quantity'], 
                $currentStock, 
                $newStock, 
                'return', 
                $orderId, 
                "Stock restored from cancelled order"
            );
        }
    }
}

/**
 * Deduct stock when order is confirmed
 */
function deductOrderStock($orderId) {
    $pdo = getDB();
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.product_id, oi.quantity, p.name as product_name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll();
    
    foreach ($orderItems as $item) {
        // Get current stock
        $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stmt->execute([$item['product_id']]);
        $currentStock = $stmt->fetchColumn();
        
        if ($currentStock !== false) {
            $newStock = max(0, $currentStock - $item['quantity']);
            
            // Update stock
            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$newStock, $item['product_id']]);
            
            // Log stock movement
            logStockMovement(
                $item['product_id'], 
                'out', 
                $item['quantity'], 
                $currentStock, 
                $newStock, 
                'sale', 
                $orderId, 
                "Stock deducted for confirmed order"
            );
        }
    }
}

/**
 * Log stock movement
 */
function logStockMovement($productId, $movementType, $quantity, $previousStock, $currentStock, $referenceType, $referenceId = null, $notes = '') {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO stock_movements (
            product_id, movement_type, quantity, previous_stock, current_stock,
            reference_type, reference_id, notes, admin_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([
        $productId,
        $movementType,
        $movementType === 'out' ? -$quantity : $quantity,
        $previousStock,
        $currentStock,
        $referenceType,
        $referenceId,
        $notes,
        $_SESSION['admin_id'] ?? null
    ]);
}

/**
 * Export orders to Excel
 */
function exportOrders() {
    // This would typically use a library like PhpSpreadsheet
    // For now, we'll export as CSV
    
    try {
        $pdo = getDB();
        
        // Build query with same filters as index
        $filters = [
            'search' => $_GET['search'] ?? '',
            'order_status' => $_GET['order_status'] ?? '',
            'payment_status' => $_GET['payment_status'] ?? '',
            'payment_method' => $_GET['payment_method'] ?? '',
            'shipping_area_id' => $_GET['shipping_area_id'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? ''
        ];
        
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['search'])) {
            $where .= " AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['order_status'])) {
            $where .= " AND o.order_status = ?";
            $params[] = $filters['order_status'];
        }
        
        if (!empty($filters['payment_status'])) {
            $where .= " AND o.payment_status = ?";
            $params[] = $filters['payment_status'];
        }
        
        if (!empty($filters['payment_method'])) {
            $where .= " AND o.payment_method = ?";
            $params[] = $filters['payment_method'];
        }
        
        if (!empty($filters['shipping_area_id'])) {
            $where .= " AND o.shipping_area_id = ?";
            $params[] = $filters['shipping_area_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where .= " AND DATE(o.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where .= " AND DATE(o.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql = "
            SELECT 
                o.order_number,
                o.customer_name,
                o.customer_phone,
                o.customer_email,
                sa.area_name,
                o.subtotal,
                o.shipping_cost,
                o.total_amount,
                o.payment_method,
                o.payment_status,
                o.order_status,
                o.created_at,
                o.shipping_address,
                o.notes
            FROM orders o
            LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
            $where
            ORDER BY o.created_at DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        // Set headers for CSV download
        $filename = 'orders_export_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Create file pointer
        $output = fopen('php://output', 'w');
        
        // Add BOM for proper UTF-8 encoding in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add CSV headers
        fputcsv($output, [
            'Nomor Pesanan',
            'Nama Pelanggan',
            'Telepon',
            'Email',
            'Area Pengiriman',
            'Subtotal',
            'Ongkos Kirim',
            'Total',
            'Metode Pembayaran',
            'Status Pembayaran',
            'Status Pesanan',
            'Tanggal Dibuat',
            'Alamat Pengiriman',
            'Catatan'
        ]);
        
        // Add data rows
        foreach ($orders as $order) {
            fputcsv($output, [
                $order['order_number'],
                $order['customer_name'],
                $order['customer_phone'],
                $order['customer_email'],
                $order['area_name'],
                $order['subtotal'],
                $order['shipping_cost'],
                $order['total_amount'],
                $order['payment_method'] === 'cod' ? 'COD' : 'Transfer',
                ucfirst($order['payment_status']),
                ucfirst($order['order_status']),
                date('d/m/Y H:i', strtotime($order['created_at'])),
                str_replace(["\r", "\n"], ' ', $order['shipping_address']),
                str_replace(["\r", "\n"], ' ', $order['notes'])
            ]);
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        error_log("Error exporting orders: " . $e->getMessage());
        redirectWithMessage('orders.php', 'Gagal mengekspor data pesanan', 'error');
    }
}
?>