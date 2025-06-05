<?php
// admin/category/process.php
// Category process handler
$processAction = $_POST['process_action'] ?? '';

switch ($processAction) {
    case 'create':
        createCategory();
        break;
    case 'update':
        updateCategory();
        break;
    case 'delete':
        deleteCategory();
        break;
    case 'toggle_status':
        toggleStatus();
        break;
    default:
        redirectWithMessage('category.php', 'Aksi tidak valid', 'error');
}

/**
 * Create new category
 */
function createCategory() {
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('category.php', 'Invalid CSRF token', 'error');
    }
    
    try {
        $pdo = getDB();
        
        // Prepare data
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => generateCategorySlug($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Validate data
        $errors = validateCategoryData($data);
        if (!empty($errors)) {
            $_SESSION['form_data'] = $data;
            $_SESSION['form_errors'] = $errors;
            redirectWithMessage('category.php?action=create', 'Data tidak valid', 'error');
        }
        
        // Create category
        $sql = "
            INSERT INTO categories (name, slug, description, sort_order, is_active) 
            VALUES (?, ?, ?, ?, ?)
        ";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'],
            $data['sort_order'],
            $data['is_active']
        ]);
        
        if ($success) {
            $categoryId = $pdo->lastInsertId();
            
            // Handle image upload
            if (!empty($_FILES['image']['name'])) {
                try {
                    $filename = handleCategoryImageUpload($_FILES['image']);
                    // Update category with image
                    $stmt = $pdo->prepare("UPDATE categories SET image = ? WHERE id = ?");
                    $stmt->execute([$filename, $categoryId]);
                } catch (Exception $e) {
                    // Log error but don't fail the entire operation
                    error_log("Error uploading category image: " . $e->getMessage());
                }
            }
            
            // Log activity
            logActivity('category_create', "Created category: {$data['name']}", $_SESSION['admin_id']);
            
            redirectWithMessage('category.php', 'Kategori berhasil ditambahkan', 'success');
        } else {
            throw new Exception('Failed to create category');
        }
        
    } catch (Exception $e) {
        error_log("Error creating category: " . $e->getMessage());
        $_SESSION['form_data'] = $data ?? [];
        redirectWithMessage('category.php?action=create', 'Gagal menambahkan kategori: ' . $e->getMessage(), 'error');
    }
}

/**
 * Update category
 */
function updateCategory() {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        redirectWithMessage('category.php', 'ID kategori tidak valid', 'error');
    }
    
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('category.php', 'Invalid CSRF token', 'error');
    }
    
    try {
        $pdo = getDB();
        
        // Get current category
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $currentCategory = $stmt->fetch();
        
        if (!$currentCategory) {
            redirectWithMessage('category.php', 'Kategori tidak ditemukan', 'error');
        }
        
        // Prepare data
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => generateCategorySlug($_POST['name'] ?? '', $id),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Validate data
        $errors = validateCategoryData($data, $id);
        if (!empty($errors)) {
            $_SESSION['form_data'] = $data;
            $_SESSION['form_errors'] = $errors;
            redirectWithMessage("category.php?action=edit&id=$id", 'Data tidak valid', 'error');
        }
        
        // Handle image deletion
        if (isset($_POST['delete_image']) && !empty($currentCategory['image'])) {
            deleteCategoryImage($currentCategory['image']);
            $data['image'] = null;
        } else {
            $data['image'] = $currentCategory['image'];
        }
        
        // Handle new image upload
        if (!empty($_FILES['image']['name'])) {
            try {
                // Delete old image if exists
                if (!empty($currentCategory['image'])) {
                    deleteCategoryImage($currentCategory['image']);
                }
                $data['image'] = handleCategoryImageUpload($_FILES['image']);
            } catch (Exception $e) {
                error_log("Error uploading category image: " . $e->getMessage());
                // Keep existing image on upload error
                $data['image'] = $currentCategory['image'];
            }
        }
        
        // Update category
        $sql = "
            UPDATE categories SET
                name = ?, slug = ?, description = ?, image = ?, 
                sort_order = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'],
            $data['image'],
            $data['sort_order'],
            $data['is_active'],
            $id
        ]);
        
        if ($success) {
            // Log activity
            logActivity('category_update', "Updated category: {$data['name']}", $_SESSION['admin_id']);
            
            redirectWithMessage('category.php', 'Kategori berhasil diupdate', 'success');
        } else {
            throw new Exception('Failed to update category');
        }
        
    } catch (Exception $e) {
        error_log("Error updating category: " . $e->getMessage());
        $_SESSION['form_data'] = $data ?? [];
        redirectWithMessage("category.php?action=edit&id=$id", 'Gagal mengupdate kategori: ' . $e->getMessage(), 'error');
    }
}

/**
 * Delete category
 */
function deleteCategory() {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        redirectWithMessage('category.php', 'ID kategori tidak valid', 'error');
    }
    
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('category.php', 'Invalid CSRF token', 'error');
    }
    
    try {
        $pdo = getDB();
        
        // Get category info
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            redirectWithMessage('category.php', 'Kategori tidak ditemukan', 'error');
        }
        
        // Check if category has products
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            redirectWithMessage('category.php', 'Kategori tidak dapat dihapus karena masih memiliki produk', 'error');
        }
        
        // Delete category image if exists
        if (!empty($category['image'])) {
            deleteCategoryImage($category['image']);
        }
        
        // Delete category
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $success = $stmt->execute([$id]);
        
        if ($success) {
            // Log activity
            logActivity('category_delete', "Deleted category: {$category['name']}", $_SESSION['admin_id']);
            
            redirectWithMessage('category.php', 'Kategori berhasil dihapus', 'success');
        } else {
            throw new Exception('Failed to delete category');
        }
        
    } catch (Exception $e) {
        error_log("Error deleting category: " . $e->getMessage());
        redirectWithMessage('category.php', 'Gagal menghapus kategori: ' . $e->getMessage(), 'error');
    }
}

/**
 * Toggle category status via AJAX
 */
function toggleStatus() {
    header('Content-Type: application/json');
    
    $id = (int)($_POST['id'] ?? 0);
    $isActive = $_POST['is_active'] === 'true' ? 1 : 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit;
    }
    
    try {
        $pdo = getDB();
        
        // Get category info
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            echo json_encode(['success' => false, 'message' => 'Kategori tidak ditemukan']);
            exit;
        }
        
        // Update status
        $stmt = $pdo->prepare("UPDATE categories SET is_active = ? WHERE id = ?");
        $success = $stmt->execute([$isActive, $id]);
        
        if ($success) {
            // Log activity
            $status = $isActive ? 'activated' : 'deactivated';
            logActivity('category_toggle', "Category {$status}: {$category['name']}", $_SESSION['admin_id']);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Status kategori berhasil diubah'
            ]);
        } else {
            throw new Exception('Failed to update status');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
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
    if (!isset($data['sort_order']) || $data['sort_order'] < 0) {
        $errors[] = 'Urutan tidak boleh negatif';
    }
    
    return $errors;
}

/**
 * Generate unique slug for category
 */
function generateCategorySlug($name, $excludeId = null) {
    $pdo = getDB();
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
 * Handle category image upload
 */
function handleCategoryImageUpload($file) {
    // Create categories upload directory if it doesn't exist
    $uploadDir = UPLOAD_PATH . 'categories/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Gagal membuat directory upload');
        }
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
?>