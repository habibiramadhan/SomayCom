<?php
require_once '../auth_check.php';
hasAccess();

header('Content-Type: application/json');

try {
    // Get JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        throw new Exception('Invalid JSON data');
    }

    $pdo = getDB();
    
    // Validate required fields
    $required_fields = ['name', 'sku', 'price', 'stock_quantity'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field $field is required");
        }
    }

    // Check if SKU is unique
    $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
    $stmt->execute([$data['sku'], $data['id'] ?? 0]);
    if ($stmt->fetch()) {
        throw new Exception('SKU sudah digunakan');
    }

    // Generate slug from name
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['name'])));

    // Prepare data
    $product_data = [
        'category_id' => $data['category_id'] ?: null,
        'sku' => $data['sku'],
        'name' => $data['name'],
        'slug' => $slug,
        'description' => $data['description'] ?? null,
        'price' => $data['price'],
        'discount_price' => $data['discount_price'] ?: null,
        'stock_quantity' => $data['stock_quantity'],
        'min_stock' => $data['min_stock'] ?? 5,
        'is_active' => isset($data['is_active']) ? 1 : 0,
        'is_featured' => isset($data['is_featured']) ? 1 : 0
    ];

    if (empty($data['id'])) {
        // Insert new product
        $sql = "INSERT INTO products (category_id, sku, name, slug, description, price, discount_price, 
                stock_quantity, min_stock, is_active, is_featured) 
                VALUES (:category_id, :sku, :name, :slug, :description, :price, :discount_price, 
                :stock_quantity, :min_stock, :is_active, :is_featured)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($product_data);
        
        $product_id = $pdo->lastInsertId();
        
        // Log stock movement
        $stmt = $pdo->prepare("
            INSERT INTO stock_movements (product_id, movement_type, quantity, previous_stock, current_stock, 
                                      reference_type, notes, admin_id)
            VALUES (?, 'in', ?, 0, ?, 'adjustment', 'Initial stock', ?)
        ");
        $stmt->execute([
            $product_id,
            $data['stock_quantity'],
            $data['stock_quantity'],
            $_SESSION['admin_id']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Produk berhasil ditambahkan']);
    } else {
        // Update existing product
        $sql = "UPDATE products SET 
                category_id = :category_id,
                sku = :sku,
                name = :name,
                slug = :slug,
                description = :description,
                price = :price,
                discount_price = :discount_price,
                stock_quantity = :stock_quantity,
                min_stock = :min_stock,
                is_active = :is_active,
                is_featured = :is_featured
                WHERE id = :id";
        
        $product_data['id'] = $data['id'];
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($product_data);
        
        // Log stock movement if quantity changed
        $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stmt->execute([$data['id']]);
        $old_quantity = $stmt->fetchColumn();
        
        if ($old_quantity != $data['stock_quantity']) {
            $stmt = $pdo->prepare("
                INSERT INTO stock_movements (product_id, movement_type, quantity, previous_stock, current_stock, 
                                          reference_type, notes, admin_id)
                VALUES (?, 'adjustment', ?, ?, ?, 'adjustment', 'Stock adjustment', ?)
            ");
            $stmt->execute([
                $data['id'],
                $data['stock_quantity'] - $old_quantity,
                $old_quantity,
                $data['stock_quantity'],
                $_SESSION['admin_id']
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Produk berhasil diperbarui']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 