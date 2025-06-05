<?php
// admin/models/Category.php
class Category {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDB();
    }
    
    /**
     * Get all categories with pagination and filters
     */
    public function getAllCategories($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where = "WHERE 1=1";
        $params = [];
        
        // Apply filters
        if (!empty($filters['search'])) {
            $where .= " AND (name LIKE ? OR description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($filters['is_active'] !== '') {
            $where .= " AND is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        $sql = "
            SELECT c.*, 
                   COUNT(p.id) as product_count
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
            $where
            GROUP BY c.id
            ORDER BY c.sort_order ASC, c.name ASC
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
    public function getTotalCategories($filters = []) {
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['search'])) {
            $where .= " AND (name LIKE ? OR description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($filters['is_active'] !== '') {
            $where .= " AND is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM categories $where");
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get category by ID
     */
    public function getCategoryById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get category by slug
     */
    public function getCategoryBySlug($slug) {
        $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }
    
    /**
     * Get active categories for dropdown
     */
    public function getActiveCategories() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM categories 
            WHERE is_active = 1 
            ORDER BY sort_order ASC, name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Create new category
     */
    public function createCategory($data) {
        $sql = "
            INSERT INTO categories (name, slug, description, image, sort_order, is_active) 
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'],
            $data['image'] ?? null,
            $data['sort_order'],
            $data['is_active']
        ]);
        
        return $success ? $this->pdo->lastInsertId() : false;
    }
    
    /**
     * Update category
     */
    public function updateCategory($id, $data) {
        $sql = "
            UPDATE categories SET
                name = ?, slug = ?, description = ?, image = ?, 
                sort_order = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'],
            $data['image'],
            $data['sort_order'],
            $data['is_active'],
            $id
        ]);
    }
    
    /**
     * Delete category
     */
    public function deleteCategory($id) {
        // Check if category has products
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Kategori tidak dapat dihapus karena masih memiliki produk');
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Update category status
     */
    public function updateStatus($id, $status) {
        $stmt = $this->pdo->prepare("UPDATE categories SET is_active = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
    
    /**
     * Generate unique slug
     */
    public function generateSlug($name, $excludeId = null) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $originalSlug = $slug;
        $counter = 1;
        
        do {
            $where = "slug = ?";
            $params = [$slug];
            
            if ($excludeId) {
                $where .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM categories WHERE $where");
            $stmt->execute($params);
            
            if ($stmt->fetchColumn() == 0) {
                break;
            }
            
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        } while (true);
        
        return $slug;
    }
    
    /**
     * Validate category data
     */
    public function validateCategory($data, $excludeId = null) {
        $errors = [];
        
        // Required fields
        if (empty($data['name'])) {
            $errors[] = 'Nama kategori harus diisi';
        }
        
        // Check unique name
        if (!empty($data['name'])) {
            $where = "name = ?";
            $params = [$data['name']];
            
            if ($excludeId) {
                $where .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM categories WHERE $where");
            $stmt->execute($params);
            
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Nama kategori sudah digunakan';
            }
        }
        
        // Validate sort order
        if (!isset($data['sort_order']) || $data['sort_order'] < 0) {
            $errors[] = 'Urutan tidak boleh negatif';
        }
        
        return $errors;
    }
    
    /**
     * Get category statistics
     */
    public function getCategoryStats($id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_products,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_products,
                COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_products,
                COUNT(CASE WHEN is_featured = 1 THEN 1 END) as featured_products,
                AVG(price) as avg_price,
                SUM(stock_quantity) as total_stock
            FROM products 
            WHERE category_id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get products in category
     */
    public function getCategoryProducts($id, $limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT p.*, 
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                   CASE 
                       WHEN p.discount_price IS NOT NULL AND p.discount_price > 0 THEN p.discount_price 
                       ELSE p.price 
                   END as final_price
            FROM products p
            WHERE p.category_id = ?
            ORDER BY p.is_active DESC, p.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$id, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Update sort order for multiple categories
     */
    public function updateSortOrder($categoryOrders) {
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
            
            foreach ($categoryOrders as $id => $order) {
                $stmt->execute([$order, $id]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * Get next sort order
     */
    public function getNextSortOrder() {
        $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}