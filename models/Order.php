<!-- admin/models/Order.php -->
<?php
class Order {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDB();
    }
    
    /**
     * Get all orders with pagination and filters
     */
    public function getAllOrders($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where = "WHERE 1=1";
        $params = [];
        
        // Apply filters
        if (!empty($filters['search'])) {
            $where .= " AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.customer_email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
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
            SELECT o.*, sa.area_name,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
            FROM orders o
            LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
            $where
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get total count for pagination
     */
    public function getTotalOrders($filters = []) {
        $where = "WHERE 1=1";
        $params = [];
        
        // Apply same filters as getAllOrders
        if (!empty($filters['search'])) {
            $where .= " AND (order_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ? OR customer_email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['order_status'])) {
            $where .= " AND order_status = ?";
            $params[] = $filters['order_status'];
        }
        
        if (!empty($filters['payment_status'])) {
            $where .= " AND payment_status = ?";
            $params[] = $filters['payment_status'];
        }
        
        if (!empty($filters['payment_method'])) {
            $where .= " AND payment_method = ?";
            $params[] = $filters['payment_method'];
        }
        
        if (!empty($filters['shipping_area_id'])) {
            $where .= " AND shipping_area_id = ?";
            $params[] = $filters['shipping_area_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders $where");
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get order by ID
     */
    public function getOrderById($id) {
        $stmt = $this->pdo->prepare("
            SELECT o.*, sa.area_name, sa.estimated_delivery
            FROM orders o
            LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
            WHERE o.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get order by order number
     */
    public function getOrderByNumber($orderNumber) {
        $stmt = $this->pdo->prepare("
            SELECT o.*, sa.area_name, sa.estimated_delivery
            FROM orders o
            LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
            WHERE o.order_number = ?
        ");
        $stmt->execute([$orderNumber]);
        return $stmt->fetch();
    }
    
    /**
     * Get order items
     */
    public function getOrderItems($orderId) {
        $stmt = $this->pdo->prepare("
            SELECT oi.*, p.name as current_product_name, p.id as current_product_id,
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            ORDER BY oi.id
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Update order status
     */
    public function updateStatus($id, $newStatus, $adminNotes = '') {
        // Get current order
        $order = $this->getOrderById($id);
        if (!$order) {
            throw new Exception('Pesanan tidak ditemukan');
        }
        
        // Validate status transition
        $validTransitions = $this->getValidStatusTransitions($order['order_status']);
        if (!in_array($newStatus, $validTransitions)) {
            throw new Exception('Transisi status tidak valid');
        }
        
        // Begin transaction
        $this->pdo->beginTransaction();
        
        try {
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
            $updateParams[] = $id;
            
            // Update order
            $sql = "UPDATE orders SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute($updateParams);
            
            if ($success) {
                // Handle stock changes
                if ($newStatus === 'cancelled' && $order['order_status'] !== 'cancelled') {
                    $this->restoreOrderStock($id);
                }
                
                if ($newStatus === 'confirmed' && $order['order_status'] === 'pending') {
                    $this->deductOrderStock($id);
                }
                
                $this->pdo->commit();
                return true;
            } else {
                throw new Exception('Failed to update order status');
            }
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get valid status transitions
     */
    public function getValidStatusTransitions($currentStatus) {
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
    private function restoreOrderStock($orderId) {
        // Get order items
        $stmt = $this->pdo->prepare("
            SELECT oi.product_id, oi.quantity, p.name as product_name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll();
        
        foreach ($orderItems as $item) {
            // Get current stock
            $stmt = $this->pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $stmt->execute([$item['product_id']]);
            $currentStock = $stmt->fetchColumn();
            
            if ($currentStock !== false) {
                $newStock = $currentStock + $item['quantity'];
                
                // Update stock
                $stmt = $this->pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                $stmt->execute([$newStock, $item['product_id']]);
                
                // Log stock movement
                $this->logStockMovement(
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
    private function deductOrderStock($orderId) {
        // Get order items
        $stmt = $this->pdo->prepare("
            SELECT oi.product_id, oi.quantity, p.name as product_name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll();
        
        foreach ($orderItems as $item) {
            // Get current stock
            $stmt = $this->pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $stmt->execute([$item['product_id']]);
            $currentStock = $stmt->fetchColumn();
            
            if ($currentStock !== false) {
                $newStock = max(0, $currentStock - $item['quantity']);
                
                // Update stock
                $stmt = $this->pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                $stmt->execute([$newStock, $item['product_id']]);
                
                // Log stock movement
                $this->logStockMovement(
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
    private function logStockMovement($productId, $movementType, $quantity, $previousStock, $currentStock, $referenceType, $referenceId = null, $notes = '') {
        $stmt = $this->pdo->prepare("
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
     * Get order statistics by status
     */
    public function getOrderStatistics() {
        $stmt = $this->pdo->prepare("
            SELECT 
                order_status,
                COUNT(*) as count,
                SUM(total_amount) as total_amount
            FROM orders 
            GROUP BY order_status
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent orders
     */
    public function getRecentOrders($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT o.*, sa.area_name
            FROM orders o
            LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
            ORDER BY o.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get orders by customer phone
     */
    public function getOrdersByCustomer($customerPhone) {
        $stmt = $this->pdo->prepare("
            SELECT o.*, sa.area_name
            FROM orders o
            LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
            WHERE o.customer_phone = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$customerPhone]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get daily sales report
     */
    public function getDailySales($dateFrom, $dateTo) {
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(created_at) as sale_date,
                COUNT(*) as total_orders,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_order_value
            FROM orders 
            WHERE order_status NOT IN ('cancelled')
            AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY sale_date DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get top products from orders
     */
    public function getTopProducts($limit = 10, $dateFrom = null, $dateTo = null) {
        $where = "WHERE o.order_status NOT IN ('cancelled')";
        $params = [];
        
        if ($dateFrom && $dateTo) {
            $where .= " AND DATE(o.created_at) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT 
                p.id,
                p.name,
                p.sku,
                SUM(oi.quantity) as total_sold,
                SUM(oi.subtotal) as total_revenue
            FROM products p
            JOIN order_items oi ON p.id = oi.product_id
            JOIN orders o ON oi.order_id = o.id
            $where
            GROUP BY p.id, p.name, p.sku
            ORDER BY total_sold DESC
            LIMIT ?
        ");
        
        $params[] = $limit;
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get sales by area
     */
    public function getSalesByArea($dateFrom = null, $dateTo = null) {
        $where = "WHERE o.order_status NOT IN ('cancelled')";
        $params = [];
        
        if ($dateFrom && $dateTo) {
            $where .= " AND DATE(o.created_at) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT 
                sa.area_name,
                COUNT(o.id) as total_orders,
                SUM(o.total_amount) as total_revenue
            FROM shipping_areas sa
            LEFT JOIN orders o ON sa.id = o.shipping_area_id 
            $where
            GROUP BY sa.id, sa.area_name
            ORDER BY total_revenue DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Update payment status
     */
    public function updatePaymentStatus($id, $paymentStatus) {
        $stmt = $this->pdo->prepare("UPDATE orders SET payment_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$paymentStatus, $id]);
    }
    
    /**
     * Add admin notes
     */
    public function addAdminNotes($id, $notes) {
        // Get current notes
        $stmt = $this->pdo->prepare("SELECT admin_notes FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        $currentNotes = $stmt->fetchColumn();
        
        // Append new notes with timestamp
        $newNotes = $currentNotes ? $currentNotes . "\n\n" . date('Y-m-d H:i:s') . " - " . $notes : date('Y-m-d H:i:s') . " - " . $notes;
        
        $stmt = $this->pdo->prepare("UPDATE orders SET admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$newNotes, $id]);
    }
    
    /**
     * Delete order (only if not confirmed)
     */
    public function deleteOrder($id) {
        $order = $this->getOrderById($id);
        if (!$order) {
            throw new Exception('Pesanan tidak ditemukan');
        }
        
        if ($order['order_status'] !== 'pending') {
            throw new Exception('Hanya pesanan dengan status pending yang dapat dihapus');
        }
        
        // Begin transaction
        $this->pdo->beginTransaction();
        
        try {
            // Delete order items first
            $stmt = $this->pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->execute([$id]);
            
            // Delete order
            $stmt = $this->pdo->prepare("DELETE FROM orders WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                $this->pdo->commit();
                return true;
            } else {
                throw new Exception('Failed to delete order');
            }
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get shipping areas for dropdown
     */
    public function getShippingAreas() {
        $stmt = $this->pdo->prepare("SELECT * FROM shipping_areas WHERE is_active = 1 ORDER BY area_name");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Validate order data
     */
    public function validateOrder($data) {
        $errors = [];
        
        // Required fields
        if (empty($data['customer_name'])) {
            $errors[] = 'Nama pelanggan harus diisi';
        }
        
        if (empty($data['customer_phone'])) {
            $errors[] = 'Nomor telepon harus diisi';
        }
        
        if (empty($data['shipping_address'])) {
            $errors[] = 'Alamat pengiriman harus diisi';
        }
        
        if (empty($data['total_amount']) || $data['total_amount'] <= 0) {
            $errors[] = 'Total pesanan harus lebih dari 0';
        }
        
        // Validate phone number
        if (!empty($data['customer_phone']) && !isValidPhone($data['customer_phone'])) {
            $errors[] = 'Format nomor telepon tidak valid';
        }
        
        // Validate email if provided
        if (!empty($data['customer_email']) && !isValidEmail($data['customer_email'])) {
            $errors[] = 'Format email tidak valid';
        }
        
        return $errors;
    }
}