<?php
class Product {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDB();
    }
    
    /**
     * Get all products with pagination and filters
     */
    public function getAllProducts($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where = "WHERE 1=1";
        $params = [];
        
        // Apply filters
        if (!empty($filters['search'])) {
            $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['category_id'])) {
            $where .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['is_active'])) {
            $where .= " AND p.is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (!empty($filters['is_featured'])) {
            $where .= " AND p.is_featured = ?";
            $params[] = $filters['is_featured'];
        }
        
        if (!empty($filters['low_stock'])) {
            $where .= " AND p.stock_quantity <= p.min_stock";
        }
        
        $sql = "
            SELECT p.*, c.name as category_name,
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                   CASE 
                       WHEN p.discount_price IS NOT NULL AND p.discount_price > 0 THEN p.discount_price 
                       ELSE p.price 
                   END as final_price
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            $where
            ORDER BY p.created_at DESC
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
    public function getTotalProducts($filters = []) {
        $where = "WHERE 1=1";
        $params = [];
        
        // Apply same filters as getAllProducts
        if (!empty($filters['search'])) {
            $where .= " AND (name LIKE ? OR sku LIKE ? OR description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['category_id'])) {
            $where .= " AND category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['is_active'])) {
            $where .= " AND is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (!empty($filters['is_featured'])) {
            $where .= " AND is_featured = ?";
            $params[] = $filters['is_featured'];
        }
        
        if (!empty($filters['low_stock'])) {
            $where .= " AND stock_quantity <= min_stock";
        }
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products $where");
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get product by ID
     */
    public function getProductById($id) {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get product by slug
     */
    public function getProductBySlug($slug) {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.slug = ?
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }
    
    /**
     * Create new product
     */
    public function createProduct($data) {
        $sql = "
            INSERT INTO products (
                category_id, sku, name, slug, description, short_description,
                price, discount_price, weight, stock_quantity, min_stock,
                is_active, is_featured, meta_title, meta_description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            $data['category_id'],
            $data['sku'],
            $data['name'],
            $data['slug'],
            $data['description'],
            $data['short_description'],
            $data['price'],
            $data['discount_price'],
            $data['weight'],
            $data['stock_quantity'],
            $data['min_stock'],
            $data['is_active'],
            $data['is_featured'],
            $data['meta_title'],
            $data['meta_description']
        ]);
        
        if ($success) {
            $productId = $this->pdo->lastInsertId();
            
            // Log stock movement for initial stock
            if ($data['stock_quantity'] > 0) {
                $this->logStockMovement($productId, 'in', $data['stock_quantity'], 0, $data['stock_quantity'], 'adjustment');
            }
            
            return $productId;
        }
        
        return false;
    }
    
    /**
     * Update product
     */
    public function updateProduct($id, $data) {
        // Get current stock for stock movement logging
        $currentProduct = $this->getProductById($id);
        if (!$currentProduct) {
            return false;
        }
        
        $sql = "
            UPDATE products SET
                category_id = ?, sku = ?, name = ?, slug = ?, description = ?,
                short_description = ?, price = ?, discount_price = ?, weight = ?,
                stock_quantity = ?, min_stock = ?, is_active = ?, is_featured = ?,
                meta_title = ?, meta_description = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            $data['category_id'],
            $data['sku'],
            $data['name'],
            $data['slug'],
            $data['description'],
            $data['short_description'],
            $data['price'],
            $data['discount_price'],
            $data['weight'],
            $data['stock_quantity'],
            $data['min_stock'],
            $data['is_active'],
            $data['is_featured'],
            $data['meta_title'],
            $data['meta_description'],
            $id
        ]);
        
        // Log stock movement if stock changed
        if ($success && $currentProduct['stock_quantity'] != $data['stock_quantity']) {
            $difference = $data['stock_quantity'] - $currentProduct['stock_quantity'];
            $type = $difference > 0 ? 'in' : 'out';
            $this->logStockMovement($id, $type, abs($difference), $currentProduct['stock_quantity'], $data['stock_quantity'], 'adjustment');
        }
        
        return $success;
    }
    
    /**
     * Delete product
     */
    public function deleteProduct($id) {
        // Check if product is used in orders
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Product tidak dapat dihapus karena sudah digunakan dalam pesanan');
        }
        
        // Delete product images first
        $this->deleteProductImages($id);
        
        // Delete product
        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Get product images
     */
    public function getProductImages($productId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM product_images 
            WHERE product_id = ? 
            ORDER BY is_primary DESC, sort_order ASC
        ");
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Add product image
     */
    public function addProductImage($productId, $imagePath, $altText = '', $isPrimary = false, $sortOrder = 0) {
        // If this is primary image, unset other primary images
        if ($isPrimary) {
            $stmt = $this->pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
            $stmt->execute([$productId]);
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$productId, $imagePath, $altText, $isPrimary, $sortOrder]);
    }
    
    /**
     * Delete product image
     */
    public function deleteProductImage($imageId) {
        $stmt = $this->pdo->prepare("SELECT image_path FROM product_images WHERE id = ?");
        $stmt->execute([$imageId]);
        $image = $stmt->fetch();
        
        if ($image) {
            // Delete file
            deleteFile(PRODUCT_IMAGE_PATH . $image['image_path']);
            
            // Delete from database
            $stmt = $this->pdo->prepare("DELETE FROM product_images WHERE id = ?");
            return $stmt->execute([$imageId]);
        }
        
        return false;
    }
    
    /**
     * Delete all product images
     */
    public function deleteProductImages($productId) {
        $images = $this->getProductImages($productId);
        foreach ($images as $image) {
            deleteFile(PRODUCT_IMAGE_PATH . $image['image_path']);
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
        return $stmt->execute([$productId]);
    }
    
    /**
     * Generate unique SKU
     */
    public function generateSKU($prefix = 'PRD') {
        do {
            $sku = $prefix . date('y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ?");
            $stmt->execute([$sku]);
        } while ($stmt->fetchColumn() > 0);
        
        return $sku;
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
            
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE $where");
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
     * Update stock quantity
     */
    public function updateStock($productId, $quantity, $type = 'adjustment', $referenceId = null, $notes = '') {
        $currentProduct = $this->getProductById($productId);
        if (!$currentProduct) {
            return false;
        }
        
        $newStock = $currentProduct['stock_quantity'] + $quantity;
        if ($newStock < 0) {
            throw new Exception('Stok tidak mencukupi');
        }
        
        // Update stock
        $stmt = $this->pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        $success = $stmt->execute([$newStock, $productId]);
        
        if ($success) {
            // Log stock movement
            $movementType = $quantity > 0 ? 'in' : 'out';
            $this->logStockMovement($productId, $movementType, abs($quantity), $currentProduct['stock_quantity'], $newStock, $type, $referenceId, $notes);
        }
        
        return $success;
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
     * Get categories for dropdown
     */
    public function getCategories() {
        $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Validate product data
     */
    public function validateProduct($data, $excludeId = null) {
        $errors = [];
        
        // Required fields
        if (empty($data['name'])) {
            $errors[] = 'Nama produk harus diisi';
        }
        
        if (empty($data['sku'])) {
            $errors[] = 'SKU harus diisi';
        }
        
        if (empty($data['price']) || $data['price'] <= 0) {
            $errors[] = 'Harga harus diisi dan lebih dari 0';
        }
        
        // Check unique SKU
        if (!empty($data['sku'])) {
            $where = "sku = ?";
            $params = [$data['sku']];
            
            if ($excludeId) {
                $where .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE $where");
            $stmt->execute($params);
            
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'SKU sudah digunakan';
            }
        }
        
        // Validate discount price
        if (!empty($data['discount_price']) && $data['discount_price'] >= $data['price']) {
            $errors[] = 'Harga diskon harus lebih kecil dari harga normal';
        }
        
        // Validate stock
        if (!isset($data['stock_quantity']) || $data['stock_quantity'] < 0) {
            $errors[] = 'Stok tidak boleh negatif';
        }
        
        if (!isset($data['min_stock']) || $data['min_stock'] < 0) {
            $errors[] = 'Minimum stok tidak boleh negatif';
        }
        
        return $errors;
    }
}