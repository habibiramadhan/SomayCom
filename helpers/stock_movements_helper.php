<?php
// helpers/stock_movements_helper.php

/**
 * Log stock movement
 * Helper function to log stock movements from any part of the application
 */
function logStockMovement($productId, $movementType, $quantity, $previousStock, $currentStock, $referenceType, $referenceId = null, $notes = '', $adminId = null) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO stock_movements (
                product_id, movement_type, quantity, previous_stock, current_stock,
                reference_type, reference_id, notes, admin_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Ensure quantity has correct sign based on movement type
        if ($movementType === 'out' && $quantity > 0) {
            $quantity = -$quantity;
        } elseif ($movementType === 'in' && $quantity < 0) {
            $quantity = abs($quantity);
        }
        
        return $stmt->execute([
            $productId,
            $movementType,
            $quantity,
            $previousStock,
            $currentStock,
            $referenceType,
            $referenceId,
            $notes,
            $adminId ?? $_SESSION['admin_id'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Error logging stock movement: " . $e->getMessage());
        return false;
    }
}

/**
 * Update product stock and log movement
 */
function updateProductStock($productId, $quantity, $referenceType = 'adjustment', $referenceId = null, $notes = '', $adminId = null) {
    try {
        $pdo = getDB();
        $pdo->beginTransaction();
        
        // Get current product stock
        $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");
        $stmt->execute([$productId]);
        $currentStock = $stmt->fetchColumn();
        
        if ($currentStock === false) {
            throw new Exception('Product not found');
        }
        
        $newStock = $currentStock + $quantity;
        if ($newStock < 0) {
            throw new Exception('Insufficient stock');
        }
        
        // Update product stock
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$newStock, $productId]);
        
        // Log movement
        $movementType = $quantity > 0 ? 'in' : 'out';
        $success = logStockMovement(
            $productId,
            $movementType,
            abs($quantity),
            $currentStock,
            $newStock,
            $referenceType,
            $referenceId,
            $notes,
            $adminId
        );
        
        if (!$success) {
            throw new Exception('Failed to log stock movement');
        }
        
        $pdo->commit();
        return [
            'success' => true,
            'previous_stock' => $currentStock,
            'new_stock' => $newStock,
            'quantity' => $quantity
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating product stock: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Process order items and update stock
 */
function processOrderStockMovements($orderId, $orderItems, $action = 'sale') {
    try {
        $pdo = getDB();
        $pdo->beginTransaction();
        
        foreach ($orderItems as $item) {
            $productId = $item['product_id'];
            $quantity = (int)$item['quantity'];
            
            // For sales, reduce stock (negative quantity)
            // For returns, increase stock (positive quantity)
            $stockChange = $action === 'sale' ? -$quantity : $quantity;
            
            $result = updateProductStock(
                $productId,
                $stockChange,
                $action,
                $orderId,
                "Order #{$orderId} - {$action}",
                $_SESSION['admin_id'] ?? null
            );
            
            if (!$result['success']) {
                throw new Exception("Failed to update stock for product ID {$productId}: " . $result['error']);
            }
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error processing order stock movements: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Get stock movement statistics for dashboard
 */
function getStockMovementStats($days = 7) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_movements,
                SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE 0 END) as total_in,
                SUM(CASE WHEN movement_type = 'out' THEN ABS(quantity) ELSE 0 END) as total_out,
                COUNT(DISTINCT product_id) as products_affected,
                COUNT(DISTINCT admin_id) as admins_involved
            FROM stock_movements
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        
        return $stmt->fetch() ?: [
            'total_movements' => 0,
            'total_in' => 0,
            'total_out' => 0,
            'products_affected' => 0,
            'admins_involved' => 0
        ];
        
    } catch (Exception $e) {
        error_log("Error getting stock movement stats: " . $e->getMessage());
        return [
            'total_movements' => 0,
            'total_in' => 0,
            'total_out' => 0,
            'products_affected' => 0,
            'admins_involved' => 0
        ];
    }
}

/**
 * Get products with recent stock movements
 */
function getProductsWithRecentMovements($limit = 10, $days = 7) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.sku, p.stock_quantity, p.min_stock,
                   COUNT(sm.id) as movement_count,
                   SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END) as total_in,
                   SUM(CASE WHEN sm.movement_type = 'out' THEN ABS(sm.quantity) ELSE 0 END) as total_out,
                   MAX(sm.created_at) as last_movement
            FROM products p
            JOIN stock_movements sm ON p.id = sm.product_id
            WHERE sm.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND p.is_active = 1
            GROUP BY p.id, p.name, p.sku, p.stock_quantity, p.min_stock
            ORDER BY last_movement DESC
            LIMIT ?
        ");
        $stmt->execute([$days, $limit]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error getting products with recent movements: " . $e->getMessage());
        return [];
    }
}

/**
 * Check for products with unusual stock movements
 */
function detectUnusualStockMovements($threshold = 50, $days = 3) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.sku, p.stock_quantity,
                   SUM(ABS(sm.quantity)) as total_movement,
                   COUNT(sm.id) as movement_count,
                   AVG(ABS(sm.quantity)) as avg_movement
            FROM products p
            JOIN stock_movements sm ON p.id = sm.product_id
            WHERE sm.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND p.is_active = 1
            GROUP BY p.id, p.name, p.sku, p.stock_quantity
            HAVING total_movement > ? OR movement_count > 10
            ORDER BY total_movement DESC
        ");
        $stmt->execute([$days, $threshold]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error detecting unusual stock movements: " . $e->getMessage());
        return [];
    }
}

/**
 * Get stock movement summary for a specific period
 */
function getStockMovementSummary($dateStart, $dateEnd) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT 
                reference_type,
                movement_type,
                COUNT(*) as count,
                SUM(ABS(quantity)) as total_quantity,
                COUNT(DISTINCT product_id) as unique_products,
                COUNT(DISTINCT admin_id) as unique_admins
            FROM stock_movements
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY reference_type, movement_type
            ORDER BY reference_type, movement_type
        ");
        $stmt->execute([$dateStart, $dateEnd]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error getting stock movement summary: " . $e->getMessage());
        return [];
    }
}

/**
 * Validate stock movement data
 */
function validateStockMovementData($data) {
    $errors = [];
    
    // Required fields
    if (empty($data['product_id'])) {
        $errors[] = 'Product ID is required';
    }
    
    if (empty($data['movement_type']) || !in_array($data['movement_type'], ['in', 'out', 'adjustment'])) {
        $errors[] = 'Valid movement type is required (in, out, adjustment)';
    }
    
    if (!isset($data['quantity']) || !is_numeric($data['quantity']) || $data['quantity'] == 0) {
        $errors[] = 'Valid quantity is required';
    }
    
    if (!isset($data['previous_stock']) || !is_numeric($data['previous_stock']) || $data['previous_stock'] < 0) {
        $errors[] = 'Valid previous stock is required';
    }
    
    if (!isset($data['current_stock']) || !is_numeric($data['current_stock']) || $data['current_stock'] < 0) {
        $errors[] = 'Valid current stock is required';
    }
    
    if (empty($data['reference_type']) || !in_array($data['reference_type'], ['purchase', 'sale', 'adjustment', 'return'])) {
        $errors[] = 'Valid reference type is required';
    }
    
    // Validate stock calculation
    $expectedStock = $data['previous_stock'] + ($data['movement_type'] === 'out' ? -abs($data['quantity']) : abs($data['quantity']));
    if (abs($expectedStock - $data['current_stock']) > 0.01) {
        $errors[] = 'Stock calculation does not match (previous + quantity ≠ current)';
    }
    
    return $errors;
}

/**
 * Format movement type for display
 */
function formatMovementType($type) {
    $types = [
        'in' => 'Masuk',
        'out' => 'Keluar',
        'adjustment' => 'Penyesuaian'
    ];
    return $types[$type] ?? ucfirst($type);
}

/**
 * Format reference type for display
 */
function formatReferenceType($type) {
    $types = [
        'purchase' => 'Pembelian',
        'sale' => 'Penjualan',
        'adjustment' => 'Penyesuaian',
        'return' => 'Retur'
    ];
    return $types[$type] ?? ucfirst($type);
}

/**
 * Get movement type color class
 */
function getMovementTypeClass($type) {
    $classes = [
        'in' => 'bg-green-100 text-green-800',
        'out' => 'bg-red-100 text-red-800',
        'adjustment' => 'bg-blue-100 text-blue-800'
    ];
    return $classes[$type] ?? 'bg-gray-100 text-gray-800';
}

/**
 * Get movement type icon
 */
function getMovementTypeIcon($type) {
    $icons = [
        'in' => 'fa-arrow-up',
        'out' => 'fa-arrow-down',
        'adjustment' => 'fa-adjust'
    ];
    return $icons[$type] ?? 'fa-exchange-alt';
}

/**
 * Clean up old stock movements (for maintenance)
 */
function cleanupOldStockMovements($months = 12) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            DELETE FROM stock_movements 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? MONTH)
            AND reference_type NOT IN ('sale', 'return')
        ");
        $stmt->execute([$months]);
        
        $deletedCount = $stmt->rowCount();
        
        logActivity('stock_cleanup', "Cleaned up {$deletedCount} old stock movement records", $_SESSION['admin_id'] ?? null);
        
        return $deletedCount;
        
    } catch (Exception $e) {
        error_log("Error cleaning up old stock movements: " . $e->getMessage());
        return false;
    }
}

/**
 * Export stock movements to CSV format
 */
function exportStockMovementsCSV($movements, $filename = null) {
    if (!$filename) {
        $filename = 'stock_movements_' . date('Y-m-d_H-i-s') . '.csv';
    }
    
    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    $headers = [
        'ID', 'Tanggal', 'Waktu', 'Produk', 'SKU', 'Jenis Pergerakan', 
        'Kuantitas', 'Stok Sebelum', 'Stok Sesudah', 'Referensi', 
        'Admin', 'Catatan'
    ];
    fputcsv($output, $headers);
    
    // Data
    foreach ($movements as $movement) {
        $row = [
            $movement['id'],
            formatDate($movement['created_at'], 'd/m/Y'),
            formatDate($movement['created_at'], 'H:i:s'),
            $movement['product_name'] ?? 'Produk Dihapus',
            $movement['product_sku'] ?? '-',
            formatMovementType($movement['movement_type']),
            $movement['quantity'],
            $movement['previous_stock'],
            $movement['current_stock'],
            formatReferenceType($movement['reference_type']),
            $movement['admin_name'] ?? 'System',
            $movement['notes'] ?? '-'
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
}
?>