<?php
// models/ShippingArea.php
class ShippingArea {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDB();
    }
    
    /**
     * Get all shipping areas with pagination and filters
     */
    public function getAllAreas($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where = "WHERE 1=1";
        $params = [];
        
        // Apply filters
        if (!empty($filters['search'])) {
            $where .= " AND (sa.area_name LIKE ? OR sa.postal_code LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($filters['is_active'] !== '') {
            $where .= " AND sa.is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        $sql = "
            SELECT sa.*,
                   COUNT(o.id) as total_orders,
                   COALESCE(SUM(o.total_amount), 0) as total_revenue
            FROM shipping_areas sa
            LEFT JOIN orders o ON sa.id = o.shipping_area_id AND o.order_status NOT IN ('cancelled')
            $where
            GROUP BY sa.id
            ORDER BY sa.created_at DESC
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
    public function getTotalAreas($filters = []) {
        $where = "WHERE 1=1";
        $params = [];
        
        // Apply same filters as getAllAreas
        if (!empty($filters['search'])) {
            $where .= " AND (area_name LIKE ? OR postal_code LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($filters['is_active'] !== '') {
            $where .= " AND is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM shipping_areas $where");
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get area by ID
     */
    public function getAreaById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM shipping_areas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get active areas for dropdown
     */
    public function getActiveAreas() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM shipping_areas 
            WHERE is_active = 1 
            ORDER BY area_name
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Create new area
     */
    public function createArea($data) {
        $sql = "
            INSERT INTO shipping_areas (
                area_name, postal_code, shipping_cost, estimated_delivery, is_active
            ) VALUES (?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            $data['area_name'],
            $data['postal_code'],
            $data['shipping_cost'],
            $data['estimated_delivery'],
            $data['is_active']
        ]);
        
        if ($success) {
            return $this->pdo->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Update area
     */
    public function updateArea($id, $data) {
        $sql = "
            UPDATE shipping_areas SET
                area_name = ?, postal_code = ?, shipping_cost = ?, 
                estimated_delivery = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['area_name'],
            $data['postal_code'],
            $data['shipping_cost'],
            $data['estimated_delivery'],
            $data['is_active'],
            $id
        ]);
    }
    
    /**
     * Delete area
     */
    public function deleteArea($id) {
        // Check if area is used in orders
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE shipping_area_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Area tidak dapat dihapus karena sudah digunakan dalam pesanan');
        }
        
        // Delete area
        $stmt = $this->pdo->prepare("DELETE FROM shipping_areas WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Toggle area status
     */
    public function toggleStatus($id, $isActive) {
        $stmt = $this->pdo->prepare("
            UPDATE shipping_areas 
            SET is_active = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        return $stmt->execute([$isActive, $id]);
    }
    
    /**
     * Get area statistics
     */
    public function getAreaStats($id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(o.id) as total_orders,
                COUNT(DISTINCT o.customer_phone) as total_customers,
                COALESCE(SUM(o.total_amount), 0) as total_revenue,
                COALESCE(AVG(o.total_amount), 0) as avg_order_value,
                MAX(o.created_at) as last_order_date
            FROM orders o
            WHERE o.shipping_area_id = ? AND o.order_status NOT IN ('cancelled')
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get recent orders for area
     */
    public function getAreaOrders($id, $limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT o.*, 
                   COUNT(oi.id) as total_items
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.shipping_area_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$id, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Check if area name is unique
     */
    public function isAreaNameUnique($areaName, $excludeId = null) {
        $where = "area_name = ?";
        $params = [$areaName];
        
        if ($excludeId) {
            $where .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM shipping_areas WHERE $where");
        $stmt->execute($params);
        
        return $stmt->fetchColumn() == 0;
    }
    
    /**
     * Get shipping cost by area ID
     */
    public function getShippingCost($areaId) {
        $stmt = $this->pdo->prepare("
            SELECT shipping_cost 
            FROM shipping_areas 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$areaId]);
        $result = $stmt->fetch();
        return $result ? $result['shipping_cost'] : 0;
    }
    
    /**
     * Validate area data
     */
    public function validateArea($data, $excludeId = null) {
        $errors = [];
        
        // Required fields
        if (empty($data['area_name'])) {
            $errors[] = 'Nama area harus diisi';
        }
        
        if (empty($data['shipping_cost']) || $data['shipping_cost'] < 0) {
            $errors[] = 'Ongkos kirim harus diisi dan tidak boleh negatif';
        }
        
        // Check unique area name
        if (!empty($data['area_name']) && !$this->isAreaNameUnique($data['area_name'], $excludeId)) {
            $errors[] = 'Nama area sudah digunakan';
        }
        
        // Validate postal code format (optional)
        if (!empty($data['postal_code']) && !preg_match('/^\d{5}$/', $data['postal_code'])) {
            $errors[] = 'Kode pos harus 5 digit angka';
        }
        
        return $errors;
    }
    
    /**
     * Get areas for customer selection (public method)
     */
    public function getAreasForCustomer() {
        $stmt = $this->pdo->prepare("
            SELECT id, area_name, shipping_cost, estimated_delivery
            FROM shipping_areas 
            WHERE is_active = 1 
            ORDER BY area_name
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Search areas by name or postal code
     */
    public function searchAreas($query) {
        $searchTerm = '%' . $query . '%';
        $stmt = $this->pdo->prepare("
            SELECT * FROM shipping_areas 
            WHERE (area_name LIKE ? OR postal_code LIKE ?) 
            AND is_active = 1
            ORDER BY area_name
            LIMIT 10
        ");
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchAll();
    }
}
?>