<?php
// models/StockMovement.php
class StockMovement {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDB();
    }
    
    /**
     * Get all stock movements with pagination and filters
     */
    public function getAllMovements($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where = "WHERE 1=1";
        $params = [];
        
        // Apply filters
        if (!empty($filters['search'])) {
            $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR sm.notes LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['product_id'])) {
            $where .= " AND sm.product_id = ?";
            $params[] = $filters['product_id'];
        }
        
        if (!empty($filters['movement_type'])) {
            $where .= " AND sm.movement_type = ?";
            $params[] = $filters['movement_type'];
        }
        
        if (!empty($filters['reference_type'])) {
            $where .= " AND sm.reference_type = ?";
            $params[] = $filters['reference_type'];
        }
        
        if (!empty($filters['date_start'])) {
            $where .= " AND DATE(sm.created_at) >= ?";
            $params[] = $filters['date_start'];
        }
        
        if (!empty($filters['date_end'])) {
            $where .= " AND DATE(sm.created_at) <= ?";
            $params[] = $filters['date_end'];
        }
        
        if (!empty($filters['admin_id'])) {
            $where .= " AND sm.admin_id = ?";
            $params[] = $filters['admin_id'];
        }
        
        $sql = "
            SELECT sm.*, 
                   p.name as product_name, p.sku as product_sku,
                   a.full_name as admin_name,
                   o.order_number
            FROM stock_movements sm
            LEFT JOIN products p ON sm.product_id = p.id
            LEFT JOIN admins a ON sm.admin_id = a.id
            LEFT JOIN orders o ON sm.reference_id = o.id AND sm.reference_type = 'sale'
            $where
            ORDER BY sm.created_at DESC
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
    public function getTotalMovements($filters = []) {
        $where = "WHERE 1=1";
        $params = [];
        
        // Apply same filters as getAllMovements
        if (!empty($filters['search'])) {
            $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR sm.notes LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['product_id'])) {
            $where .= " AND sm.product_id = ?";
            $params[] = $filters['product_id'];
        }
        
        if (!empty($filters['movement_type'])) {
            $where .= " AND sm.movement_type = ?";
            $params[] = $filters['movement_type'];
        }
        
        if (!empty($filters['reference_type'])) {
            $where .= " AND sm.reference_type = ?";
            $params[] = $filters['reference_type'];
        }
        
        if (!empty($filters['date_start'])) {
            $where .= " AND DATE(sm.created_at) >= ?";
            $params[] = $filters['date_start'];
        }
        
        if (!empty($filters['date_end'])) {
            $where .= " AND DATE(sm.created_at) <= ?";
            $params[] = $filters['date_end'];
        }
        
        if (!empty($filters['admin_id'])) {
            $where .= " AND sm.admin_id = ?";
            $params[] = $filters['admin_id'];
        }
        
        $sql = "
            SELECT COUNT(*) 
            FROM stock_movements sm
            LEFT JOIN products p ON sm.product_id = p.id
            LEFT JOIN admins a ON sm.admin_id = a.id
            $where
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get movement by ID
     */
    public function getMovementById($id) {
        $stmt = $this->pdo->prepare("
            SELECT sm.*, 
                   p.name as product_name, p.sku as product_sku, p.price,
                   c.name as category_name,
                   a.full_name as admin_name, a.username as admin_username,
                   o.order_number, o.customer_name, o.total_amount
            FROM stock_movements sm
            LEFT JOIN products p ON sm.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN admins a ON sm.admin_id = a.id
            LEFT JOIN orders o ON sm.reference_id = o.id AND sm.reference_type = 'sale'
            WHERE sm.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Create new stock movement
     */
    public function createMovement($data) {
        $sql = "
            INSERT INTO stock_movements (
                product_id, movement_type, quantity, previous_stock, current_stock,
                reference_type, reference_id, notes, admin_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['product_id'],
            $data['movement_type'],
            $data['quantity'],
            $data['previous_stock'],
            $data['current_stock'],
            $data['reference_type'],
            $data['reference_id'] ?? null,
            $data['notes'] ?? '',
            $data['admin_id'] ?? null
        ]);
    }
    
    /**
     * Get movements by product ID
     */
    public function getMovementsByProduct($productId, $limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT sm.*, a.full_name as admin_name
            FROM stock_movements sm
            LEFT JOIN admins a ON sm.admin_id = a.id
            WHERE sm.product_id = ?
            ORDER BY sm.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$productId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get movements by reference
     */
    public function getMovementsByReference($referenceType, $referenceId) {
        $stmt = $this->pdo->prepare("
            SELECT sm.*, 
                   p.name as product_name, p.sku as product_sku,
                   a.full_name as admin_name
            FROM stock_movements sm
            LEFT JOIN products p ON sm.product_id = p.id
            LEFT JOIN admins a ON sm.admin_id = a.id
            WHERE sm.reference_type = ? AND sm.reference_id = ?
            ORDER BY sm.created_at DESC
        ");
        $stmt->execute([$referenceType, $referenceId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get summary statistics
     */
    public function getSummaryStats($filters = []) {
        $where = "WHERE 1=1";
        $params = [];
        
        // Apply filters
        if (!empty($filters['search'])) {
            $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR sm.notes LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['product_id'])) {
            $where .= " AND sm.product_id = ?";
            $params[] = $filters['product_id'];
        }
        
        if (!empty($filters['movement_type'])) {
            $where .= " AND sm.movement_type = ?";
            $params[] = $filters['movement_type'];
        }
        
        if (!empty($filters['reference_type'])) {
            $where .= " AND sm.reference_type = ?";
            $params[] = $filters['reference_type'];
        }
        
        if (!empty($filters['date_start'])) {
            $where .= " AND DATE(sm.created_at) >= ?";
            $params[] = $filters['date_start'];
        }
        
        if (!empty($filters['date_end'])) {
            $where .= " AND DATE(sm.created_at) <= ?";
            $params[] = $filters['date_end'];
        }
        
        if (!empty($filters['admin_id'])) {
            $where .= " AND sm.admin_id = ?";
            $params[] = $filters['admin_id'];
        }
        
        $sql = "
            SELECT 
                SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END) as total_in,
                SUM(CASE WHEN sm.movement_type = 'out' THEN ABS(sm.quantity) ELSE 0 END) as total_out,
                COUNT(*) as total_movements,
                COUNT(DISTINCT sm.product_id) as total_products_affected
            FROM stock_movements sm
            LEFT JOIN products p ON sm.product_id = p.id
            LEFT JOIN admins a ON sm.admin_id = a.id
            $where
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    /**
     * Get recent movements
     */
    public function getRecentMovements($limit = 5) {
        $stmt = $this->pdo->prepare("
            SELECT sm.*, 
                   p.name as product_name, p.sku as product_sku,
                   a.full_name as admin_name
            FROM stock_movements sm
            LEFT JOIN products p ON sm.product_id = p.id
            LEFT JOIN admins a ON sm.admin_id = a.id
            ORDER BY sm.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get products with low stock that had recent movements
     */
    public function getLowStockMovements($days = 7) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT sm.product_id,
                   p.name as product_name, 
                   p.sku as product_sku,
                   p.stock_quantity,
                   p.min_stock,
                   COUNT(sm.id) as movement_count,
                   MAX(sm.created_at) as last_movement
            FROM stock_movements sm
            JOIN products p ON sm.product_id = p.id
            WHERE p.stock_quantity <= p.min_stock 
            AND p.is_active = 1
            AND sm.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY sm.product_id, p.name, p.sku, p.stock_quantity, p.min_stock
            ORDER BY p.stock_quantity ASC, last_movement DESC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get top products by movement volume
     */
    public function getTopMovedProducts($days = 30, $limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT sm.product_id,
                   p.name as product_name,
                   p.sku as product_sku,
                   SUM(ABS(sm.quantity)) as total_moved,
                   COUNT(sm.id) as movement_count,
                   SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END) as total_in,
                   SUM(CASE WHEN sm.movement_type = 'out' THEN ABS(sm.quantity) ELSE 0 END) as total_out
            FROM stock_movements sm
            JOIN products p ON sm.product_id = p.id
            WHERE sm.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY sm.product_id, p.name, p.sku
            ORDER BY total_moved DESC
            LIMIT ?
        ");
        $stmt->execute([$days, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get movement trends by day
     */
    public function getMovementTrends($days = 30) {
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(created_at) as movement_date,
                COUNT(*) as total_movements,
                SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE 0 END) as total_in,
                SUM(CASE WHEN movement_type = 'out' THEN ABS(quantity) ELSE 0 END) as total_out,
                COUNT(DISTINCT product_id) as products_affected
            FROM stock_movements
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY movement_date DESC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get movements by admin
     */
    public function getMovementsByAdmin($adminId, $limit = 20) {
        $stmt = $this->pdo->prepare("
            SELECT sm.*, 
                   p.name as product_name, p.sku as product_sku
            FROM stock_movements sm
            LEFT JOIN products p ON sm.product_id = p.id
            WHERE sm.admin_id = ?
            ORDER BY sm.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$adminId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Delete old movements (for maintenance)
     */
    public function deleteOldMovements($months = 12) {
        $stmt = $this->pdo->prepare("
            DELETE FROM stock_movements 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? MONTH)
        ");
        return $stmt->execute([$months]);
    }
    
    /**
     * Get movement statistics by reference type
     */
    public function getStatsByReferenceType($days = 30) {
        $stmt = $this->pdo->prepare("
            SELECT 
                reference_type,
                COUNT(*) as movement_count,
                SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE 0 END) as total_in,
                SUM(CASE WHEN movement_type = 'out' THEN ABS(quantity) ELSE 0 END) as total_out
            FROM stock_movements
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY reference_type
            ORDER BY movement_count DESC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }
    
    /**
     * Export movements to array for CSV/Excel
     */
    public function exportMovements($filters = []) {
        $where = "WHERE 1=1";
        $params = [];
        
        // Apply filters (same as getAllMovements)
        if (!empty($filters['search'])) {
            $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR sm.notes LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['product_id'])) {
            $where .= " AND sm.product_id = ?";
            $params[] = $filters['product_id'];
        }
        
        if (!empty($filters['movement_type'])) {
            $where .= " AND sm.movement_type = ?";
            $params[] = $filters['movement_type'];
        }
        
        if (!empty($filters['reference_type'])) {
            $where .= " AND sm.reference_type = ?";
            $params[] = $filters['reference_type'];
        }
        
        if (!empty($filters['date_start'])) {
            $where .= " AND DATE(sm.created_at) >= ?";
            $params[] = $filters['date_start'];
        }
        
        if (!empty($filters['date_end'])) {
            $where .= " AND DATE(sm.created_at) <= ?";
            $params[] = $filters['date_end'];
        }
        
        if (!empty($filters['admin_id'])) {
            $where .= " AND sm.admin_id = ?";
            $params[] = $filters['admin_id'];
        }
        
        $sql = "
            SELECT sm.*, 
                   p.name as product_name, p.sku as product_sku,
                   a.full_name as admin_name,
                   o.order_number
            FROM stock_movements sm
            LEFT JOIN products p ON sm.product_id = p.id
            LEFT JOIN admins a ON sm.admin_id = a.id
            LEFT JOIN orders o ON sm.reference_id = o.id AND sm.reference_type = 'sale'
            $where
            ORDER BY sm.created_at DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}