<?php
// helpers/CategoryHelper.php

/**
 * Generate unique category slug
 */
function generateCategorySlug($name, $excludeId = null) {
    $pdo = getDB();
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $slug = trim($slug, '-'); // Remove leading/trailing dashes
    $originalSlug = $slug;
    $counter = 1;
    
    do {
        $where = "slug = ?";
        $params = [$slug];
        
        if ($excludeId) {
            $where .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE $where");
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
function validateCategoryData($data, $excludeId = null) {
    $errors = [];
    $pdo = getDB();
    
    // Required fields
    if (empty($data['name'])) {
        $errors[] = 'Nama kategori harus diisi';
    }
    
    // Check minimum length
    if (!empty($data['name']) && strlen(trim($data['name'])) < 2) {
        $errors[] = 'Nama kategori minimal 2 karakter';
    }
    
    // Check maximum length
    if (!empty($data['name']) && strlen(trim($data['name'])) > 100) {
        $errors[] = 'Nama kategori maksimal 100 karakter';
    }
    
    // Check unique name
    if (!empty($data['name'])) {
        $where = "name = ?";
        $params = [$data['name']];
        
        if ($excludeId) {
            $where .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE $where");
        $stmt->execute($params);
        
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Nama kategori sudah digunakan';
        }
    }
    
    // Validate sort order
    if (!isset($data['sort_order']) || !is_numeric($data['sort_order']) || $data['sort_order'] < 0) {
        $errors[] = 'Urutan harus berupa angka positif';
    }
    
    // Validate description length
    if (!empty($data['description']) && strlen($data['description']) > 1000) {
        $errors[] = 'Deskripsi maksimal 1000 karakter';
    }
    
    return $errors;
}

/**
 * Handle category image upload
 */
function handleCategoryImageUpload($file) {
    // Create categories upload directory if it doesn't exist
    $uploadDir = UPLOAD_PATH . 'categories/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Gagal membuat directory upload untuk kategori');
        }
    }
    
    // Additional validation for category images
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception('Ukuran gambar terlalu besar. Maksimal 5MB');
    }
    
    // Check image dimensions (optional)
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception('File bukan gambar yang valid');
    }
    
    $minWidth = 100;
    $minHeight = 100;
    $maxWidth = 2000;
    $maxHeight = 2000;
    
    if ($imageInfo[0] < $minWidth || $imageInfo[1] < $minHeight) {
        throw new Exception("Ukuran gambar terlalu kecil. Minimal {$minWidth}x{$minHeight}px");
    }
    
    if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
        throw new Exception("Ukuran gambar terlalu besar. Maksimal {$maxWidth}x{$maxHeight}px");
    }
    
    return uploadFile($file, $uploadDir, ALLOWED_IMAGE_TYPES);
}

/**
 * Delete category image
 */
function deleteCategoryImage($imagePath) {
    if (!empty($imagePath)) {
        $fullPath = UPLOAD_PATH . 'categories/' . $imagePath;
        return deleteFile($fullPath);
    }
    return false;
}

/**
 * Get category image URL
 */
function getCategoryImageUrl($imagePath) {
    if (empty($imagePath)) {
        return null;
    }
    
    $fullPath = UPLOAD_PATH . 'categories/' . $imagePath;
    if (!file_exists($fullPath)) {
        return null;
    }
    
    return SITE_URL . '/uploads/categories/' . $imagePath;
}

/**
 * Get category breadcrumb
 */
function getCategoryBreadcrumb($categorySlug) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE slug = ? AND is_active = 1");
    $stmt->execute([$categorySlug]);
    $category = $stmt->fetch();
    
    $breadcrumb = [
        ['name' => 'Beranda', 'url' => SITE_URL],
        ['name' => 'Produk', 'url' => SITE_URL . '/pages/products.php']
    ];
    
    if ($category) {
        $breadcrumb[] = ['name' => $category['name'], 'url' => SITE_URL . '/pages/products.php?category=' . $categorySlug];
    }
    
    return $breadcrumb;
}

/**
 * Get active categories for navigation
 */
function getActiveCategoriesForNav() {
    static $categories = null;
    
    if ($categories === null) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("
                SELECT id, name, slug, 
                       (SELECT COUNT(*) FROM products WHERE category_id = categories.id AND is_active = 1) as product_count
                FROM categories 
                WHERE is_active = 1 
                ORDER BY sort_order ASC, name ASC
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error fetching categories for navigation: " . $e->getMessage());
            $categories = [];
        }
    }
    
    return $categories;
}

/**
 * Get category statistics
 */
function getCategoryStatistics() {
    try {
        $pdo = getDB();
        
        // Get basic category stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_categories,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_categories,
                COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_categories
            FROM categories
        ");
        $stmt->execute();
        $basicStats = $stmt->fetch();
        
        // Get categories with product counts
        $stmt = $pdo->prepare("
            SELECT 
                c.name,
                c.slug,
                COUNT(p.id) as product_count,
                COUNT(CASE WHEN p.is_active = 1 THEN 1 END) as active_products
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id
            WHERE c.is_active = 1
            GROUP BY c.id, c.name, c.slug
            ORDER BY product_count DESC
            LIMIT 5
        ");
        $stmt->execute();
        $topCategories = $stmt->fetchAll();
        
        return [
            'basic' => $basicStats,
            'top_categories' => $topCategories
        ];
        
    } catch (Exception $e) {
        error_log("Error fetching category statistics: " . $e->getMessage());
        return [
            'basic' => ['total_categories' => 0, 'active_categories' => 0, 'inactive_categories' => 0],
            'top_categories' => []
        ];
    }
}

/**
 * Get category with product count
 */
function getCategoryWithProductCount($categoryId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   COUNT(p.id) as total_products,
                   COUNT(CASE WHEN p.is_active = 1 THEN 1 END) as active_products
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id
            WHERE c.id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$categoryId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching category with product count: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if category can be deleted
 */
function canDeleteCategory($categoryId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        return $stmt->fetchColumn() == 0;
    } catch (Exception $e) {
        error_log("Error checking if category can be deleted: " . $e->getMessage());
        return false;
    }
}

/**
 * Get category dropdown options
 */
function getCategoryDropdownOptions($selectedId = null, $includeEmpty = true) {
    $categories = getActiveCategoriesForNav();
    $options = '';
    
    if ($includeEmpty) {
        $options .= '<option value="">Pilih Kategori</option>';
    }
    
    foreach ($categories as $category) {
        $selected = ($selectedId == $category['id']) ? 'selected' : '';
        $productCount = $category['product_count'] > 0 ? " ({$category['product_count']} produk)" : '';
        $options .= '<option value="' . $category['id'] . '" ' . $selected . '>' . 
                   htmlspecialchars($category['name']) . $productCount . '</option>';
    }
    
    return $options;
}

/**
 * Format category for API response
 */
function formatCategoryForAPI($category) {
    return [
        'id' => (int)$category['id'],
        'name' => $category['name'],
        'slug' => $category['slug'],
        'description' => $category['description'],
        'image' => $category['image'] ? getCategoryImageUrl($category['image']) : null,
        'sort_order' => (int)$category['sort_order'],
        'is_active' => (bool)$category['is_active'],
        'product_count' => isset($category['product_count']) ? (int)$category['product_count'] : 0,
        'created_at' => $category['created_at'],
        'updated_at' => $category['updated_at']
    ];
}

/**
 * Get category by slug
 */
function getCategoryBySlug($slug) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   COUNT(p.id) as product_count
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
            WHERE c.slug = ? AND c.is_active = 1
            GROUP BY c.id
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching category by slug: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all categories with product counts
 */
function getAllCategoriesWithProductCounts() {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   COUNT(p.id) as product_count,
                   COUNT(CASE WHEN p.is_active = 1 THEN 1 END) as active_products
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id
            GROUP BY c.id
            ORDER BY c.sort_order ASC, c.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching all categories with product counts: " . $e->getMessage());
        return [];
    }
}