<?php
// Product process handler
$processAction = $_POST['process_action'] ?? '';

switch ($processAction) {
    case 'create':
        createProduct();
        break;
    case 'update':
        updateProduct();
        break;
    case 'delete':
        deleteProduct();
        break;
    case 'update_stock':
        updateStock();
        break;
    default:
        redirectWithMessage('product.php', 'Aksi tidak valid', 'error');
}

/**
 * Create new product
 */
function createProduct() {
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('product.php', 'Invalid CSRF token', 'error');
    }
    
    try {
        $pdo = getDB();
        
        // Prepare data
        $data = [
            'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
            'sku' => trim($_POST['sku'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'slug' => generateSlug($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'short_description' => trim($_POST['short_description'] ?? ''),
            'price' => (float)($_POST['price'] ?? 0),
            'discount_price' => !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null,
            'weight' => (float)($_POST['weight'] ?? 0),
            'stock_quantity' => (int)($_POST['stock_quantity'] ?? 0),
            'min_stock' => (int)($_POST['min_stock'] ?? 5),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'meta_title' => trim($_POST['meta_title'] ?? ''),
            'meta_description' => trim($_POST['meta_description'] ?? '')
        ];
        
        // Validate data
        $errors = validateProductData($data);
        if (!empty($errors)) {
            $_SESSION['form_data'] = $data;
            $_SESSION['form_errors'] = $errors;
            redirectWithMessage('product.php?action=create', 'Data tidak valid', 'error');
        }
        
        // Create product
        $sql = "
            INSERT INTO products (
                category_id, sku, name, slug, description, short_description,
                price, discount_price, weight, stock_quantity, min_stock,
                is_active, is_featured, meta_title, meta_description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $pdo->prepare($sql);
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
            $productId = $pdo->lastInsertId();
            
            // Handle image uploads
            handleImageUploads($productId);
            
            // Log stock movement for initial stock
            if ($data['stock_quantity'] > 0) {
                logStockMovement($productId, 'in', $data['stock_quantity'], 0, $data['stock_quantity'], 'adjustment');
            }
            
            // Log activity
            logActivity('product_create', "Created product: {$data['name']}", $_SESSION['admin_id']);
            
            redirectWithMessage('product.php', 'Produk berhasil ditambahkan', 'success');
        } else {
            throw new Exception('Failed to create product');
        }
        
    } catch (Exception $e) {
        error_log("Error creating product: " . $e->getMessage());
        $_SESSION['form_data'] = $data ?? [];
        redirectWithMessage('product.php?action=create', 'Gagal menambahkan produk: ' . $e->getMessage(), 'error');
    }
}

/**
 * Update product
 */
function updateProduct() {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        redirectWithMessage('product.php', 'ID produk tidak valid', 'error');
    }
    
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('product.php', 'Invalid CSRF token', 'error');
    }
    
    try {
        $pdo = getDB();
        
        // Get current product
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $currentProduct = $stmt->fetch();
        
        if (!$currentProduct) {
            redirectWithMessage('product.php', 'Produk tidak ditemukan', 'error');
        }
        
        // Prepare data
        $data = [
            'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
            'sku' => trim($_POST['sku'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'slug' => generateSlug($_POST['name'] ?? '', $id),
            'description' => trim($_POST['description'] ?? ''),
            'short_description' => trim($_POST['short_description'] ?? ''),
            'price' => (float)($_POST['price'] ?? 0),
            'discount_price' => !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null,
            'weight' => (float)($_POST['weight'] ?? 0),
            'stock_quantity' => (int)($_POST['stock_quantity'] ?? 0),
            'min_stock' => (int)($_POST['min_stock'] ?? 5),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'meta_title' => trim($_POST['meta_title'] ?? ''),
            'meta_description' => trim($_POST['meta_description'] ?? '')
        ];
        
        // Validate data
        $errors = validateProductData($data, $id);
        if (!empty($errors)) {
            $_SESSION['form_data'] = $data;
            $_SESSION['form_errors'] = $errors;
            redirectWithMessage("product.php?action=edit&id=$id", 'Data tidak valid', 'error');
        }
        
        // Update product
        $sql = "
            UPDATE products SET
                category_id = ?, sku = ?, name = ?, slug = ?, description = ?,
                short_description = ?, price = ?, discount_price = ?, weight = ?,
                stock_quantity = ?, min_stock = ?, is_active = ?, is_featured = ?,
                meta_title = ?, meta_description = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
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
        
        if ($success) {
            // Handle image uploads
            handleImageUploads($id);
            
            // Handle image deletions
            if (!empty($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $imageId) {
                    deleteProductImage($imageId);
                }
            }
            
            // Log stock movement if stock changed
            if ($currentProduct['stock_quantity'] != $data['stock_quantity']) {
                $difference = $data['stock_quantity'] - $currentProduct['stock_quantity'];
                $type = $difference > 0 ? 'in' : 'out';
                logStockMovement($id, $type, abs($difference), $currentProduct['stock_quantity'], $data['stock_quantity'], 'adjustment');
            }
            
            // Log activity
            logActivity('product_update', "Updated product: {$data['name']}", $_SESSION['admin_id']);
            
            redirectWithMessage('product.php', 'Produk berhasil diupdate', 'success');
        } else {
            throw new Exception('Failed to update product');
        }
        
    } catch (Exception $e) {
        error_log("Error updating product: " . $e->getMessage());
        $_SESSION['form_data'] = $data ?? [];
        redirectWithMessage("product.php?action=edit&id=$id", 'Gagal mengupdate produk: ' . $e->getMessage(), 'error');
    }
}

/**
 * Delete product
 */
function deleteProduct() {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        redirectWithMessage('product.php', 'ID produk tidak valid', 'error');
    }
    
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('product.php', 'Invalid CSRF token', 'error');
    }
    
    try {
        $pdo = getDB();
        
        // Get product info
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            redirectWithMessage('product.php', 'Produk tidak ditemukan', 'error');
        }
        
        // Check if product is used in orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            redirectWithMessage('product.php', 'Produk tidak dapat dihapus karena sudah digunakan dalam pesanan', 'error');
        }
        
        // Delete product images
        deleteAllProductImages($id);
        
        // Delete product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $success = $stmt->execute([$id]);
        
        if ($success) {
            // Log activity
            logActivity('product_delete', "Deleted product: {$product['name']}", $_SESSION['admin_id']);
            
            redirectWithMessage('product.php', 'Produk berhasil dihapus', 'success');
        } else {
            throw new Exception('Failed to delete product');
        }
        
    } catch (Exception $e) {
        error_log("Error deleting product: " . $e->getMessage());
        redirectWithMessage('product.php', 'Gagal menghapus produk: ' . $e->getMessage(), 'error');
    }
}

/**
 * Update stock via AJAX
 */
function updateStock() {
    header('Content-Type: application/json');
    
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    if (!$productId || $quantity == 0) {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
        exit;
    }
    
    try {
        $pdo = getDB();
        
        // Get current product
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
            exit;
        }
        
        $newStock = $product['stock_quantity'] + $quantity;
        if ($newStock < 0) {
            echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi']);
            exit;
        }
        
        // Update stock
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        $success = $stmt->execute([$newStock, $productId]);
        
        if ($success) {
            // Log stock movement
            $movementType = $quantity > 0 ? 'in' : 'out';
            logStockMovement($productId, $movementType, abs($quantity), $product['stock_quantity'], $newStock, 'adjustment', null, $notes);
            
            // Log activity
            logActivity('stock_adjustment', "Stock adjustment for {$product['name']}: $quantity", $_SESSION['admin_id']);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Stok berhasil diupdate',
                'new_stock' => $newStock
            ]);
        } else {
            throw new Exception('Failed to update stock');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * Validate product data
 */
function validateProductData($data, $excludeId = null) {
    $errors = [];
    $pdo = getDB();
    
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
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE $where");
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

/**
 * Generate unique slug
 */
function generateSlug($name, $excludeId = null) {
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
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE $where");
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
 * Handle image uploads
 */
function handleImageUploads($productId) {
    if (!empty($_FILES['images']['name'][0])) {
        $totalImages = count($_FILES['images']['name']);
        $pdo = getDB();
        
        // Check if product already has images to determine if first uploaded image should be primary
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
        $stmt->execute([$productId]);
        $hasImages = $stmt->fetchColumn() > 0;
        
        for ($i = 0; $i < $totalImages; $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['images']['name'][$i],
                    'type' => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error' => $_FILES['images']['error'][$i],
                    'size' => $_FILES['images']['size'][$i]
                ];
                
                try {
                    $filename = uploadFile($file, PRODUCT_IMAGE_PATH, ALLOWED_IMAGE_TYPES);
                    $isPrimary = ($i == 0 && !$hasImages);
                    addProductImage($productId, $filename, '', $isPrimary, $i);
                } catch (Exception $e) {
                    error_log("Error uploading image: " . $e->getMessage());
                    // Don't stop the process, just log the error
                }
            }
        }
    }
}

/**
 * Add product image
 */
function addProductImage($productId, $imagePath, $altText = '', $isPrimary = false, $sortOrder = 0) {
    $pdo = getDB();
    
    // If this is primary image, unset other primary images
    if ($isPrimary) {
        $stmt = $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
        $stmt->execute([$productId]);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order)
        VALUES (?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$productId, $imagePath, $altText, $isPrimary, $sortOrder]);
}

/**
 * Delete product image
 */
function deleteProductImage($imageId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE id = ?");
    $stmt->execute([$imageId]);
    $image = $stmt->fetch();
    
    if ($image) {
        // Delete file
        deleteFile(PRODUCT_IMAGE_PATH . $image['image_path']);
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
        return $stmt->execute([$imageId]);
    }
    
    return false;
}

/**
 * Delete all product images
 */
function deleteAllProductImages($productId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll();
    
    foreach ($images as $image) {
        deleteFile(PRODUCT_IMAGE_PATH . $image['image_path']);
    }
    
    $stmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
    return $stmt->execute([$productId]);
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
?>