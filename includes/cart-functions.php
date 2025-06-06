<!-- includes/cart-functions.php -->
<?php
/**
 * Cart Management Functions
 * Helper functions untuk mengelola shopping cart
 */

/**
 * Initialize cart session
 */
function initializeCart() {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
}

/**
 * Add item to cart
 */
function addToCart($productId, $quantity = 1) {
    initializeCart();
    
    try {
        $pdo = getDB();
        
        // Validate product
        $stmt = $pdo->prepare("
            SELECT id, name, price, discount_price, stock_quantity, is_active
            FROM products 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return ['success' => false, 'message' => 'Produk tidak ditemukan'];
        }
        
        if ($product['stock_quantity'] < $quantity) {
            return ['success' => false, 'message' => 'Stok tidak mencukupi'];
        }
        
        $finalPrice = $product['discount_price'] && $product['discount_price'] > 0 
                     ? $product['discount_price'] 
                     : $product['price'];
        
        // Check if item already in cart
        $existingIndex = -1;
        foreach ($_SESSION['cart'] as $index => $item) {
            if ($item['id'] == $productId) {
                $existingIndex = $index;
                break;
            }
        }
        
        if ($existingIndex >= 0) {
            // Update quantity
            $newQuantity = $_SESSION['cart'][$existingIndex]['quantity'] + $quantity;
            
            if ($newQuantity > $product['stock_quantity']) {
                return ['success' => false, 'message' => 'Jumlah melebihi stok tersedia'];
            }
            
            $_SESSION['cart'][$existingIndex]['quantity'] = $newQuantity;
        } else {
            // Add new item
            $_SESSION['cart'][] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $finalPrice,
                'quantity' => $quantity,
                'added_at' => time()
            ];
        }
        
        return ['success' => true, 'message' => 'Produk berhasil ditambahkan ke keranjang'];
        
    } catch (Exception $e) {
        error_log("Add to cart error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
    }
}

/**
 * Update cart item quantity
 */
function updateCartQuantity($productId, $quantity) {
    initializeCart();
    
    if ($quantity <= 0) {
        return removeFromCart($productId);
    }
    
    try {
        $pdo = getDB();
        
        // Validate stock
        $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? AND is_active = 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return ['success' => false, 'message' => 'Produk tidak ditemukan'];
        }
        
        if ($quantity > $product['stock_quantity']) {
            return ['success' => false, 'message' => 'Jumlah melebihi stok tersedia'];
        }
        
        // Update cart
        foreach ($_SESSION['cart'] as $index => $item) {
            if ($item['id'] == $productId) {
                $_SESSION['cart'][$index]['quantity'] = $quantity;
                return ['success' => true, 'message' => 'Keranjang berhasil diupdate'];
            }
        }
        
        return ['success' => false, 'message' => 'Item tidak ditemukan di keranjang'];
        
    } catch (Exception $e) {
        error_log("Update cart error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
    }
}

/**
 * Remove item from cart
 */
function removeFromCart($productId) {
    initializeCart();
    
    foreach ($_SESSION['cart'] as $index => $item) {
        if ($item['id'] == $productId) {
            unset($_SESSION['cart'][$index]);
            $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex array
            return ['success' => true, 'message' => 'Item berhasil dihapus dari keranjang'];
        }
    }
    
    return ['success' => false, 'message' => 'Item tidak ditemukan di keranjang'];
}

/**
 * Clear entire cart
 */
function clearCart() {
    $_SESSION['cart'] = [];
    return ['success' => true, 'message' => 'Keranjang berhasil dikosongkan'];
}

/**
 * Get cart items with product details
 */
function getCartItems() {
    initializeCart();
    
    if (empty($_SESSION['cart'])) {
        return [];
    }
    
    try {
        $pdo = getDB();
        $cartItems = [];
        
        foreach ($_SESSION['cart'] as $cartItem) {
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name,
                       (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                       CASE 
                           WHEN p.discount_price IS NOT NULL AND p.discount_price > 0 THEN p.discount_price 
                           ELSE p.price 
                       END as final_price
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id = ? AND p.is_active = 1
            ");
            $stmt->execute([$cartItem['id']]);
            $product = $stmt->fetch();
            
            if ($product) {
                // Adjust quantity if stock changed
                $quantity = min($cartItem['quantity'], $product['stock_quantity']);
                
                $cartItems[] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'sku' => $product['sku'],
                    'price' => $product['final_price'],
                    'quantity' => $quantity,
                    'subtotal' => $product['final_price'] * $quantity,
                    'image' => $product['primary_image'],
                    'category' => $product['category_name'],
                    'stock' => $product['stock_quantity'],
                    'weight' => $product['weight'],
                    'added_at' => $cartItem['added_at']
                ];
            }
        }
        
        return $cartItems;
        
    } catch (Exception $e) {
        error_log("Get cart items error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get cart summary
 */
function getCartSummary() {
    $cartItems = getCartItems();
    
    $summary = [
        'total_items' => 0,
        'total_quantity' => 0,
        'subtotal' => 0,
        'total_weight' => 0,
        'is_empty' => true
    ];
    
    if (!empty($cartItems)) {
        $summary['is_empty'] = false;
        $summary['total_items'] = count($cartItems);
        
        foreach ($cartItems as $item) {
            $summary['total_quantity'] += $item['quantity'];
            $summary['subtotal'] += $item['subtotal'];
            $summary['total_weight'] += ($item['weight'] * $item['quantity']);
        }
    }
    
    return $summary;
}

/**
 * Validate cart before checkout
 */
function validateCart() {
    $cartItems = getCartItems();
    $errors = [];
    
    if (empty($cartItems)) {
        $errors[] = 'Keranjang belanja kosong';
        return $errors;
    }
    
    $minOrderAmount = getAppSetting('min_order_amount', 25000);
    $cartSummary = getCartSummary();
    
    if ($cartSummary['subtotal'] < $minOrderAmount) {
        $errors[] = 'Minimal pembelian ' . formatRupiah($minOrderAmount);
    }
    
    try {
        $pdo = getDB();
        
        foreach ($cartItems as $item) {
            // Check if product still active and in stock
            $stmt = $pdo->prepare("
                SELECT stock_quantity, is_active, name 
                FROM products 
                WHERE id = ?
            ");
            $stmt->execute([$item['id']]);
            $product = $stmt->fetch();
            
            if (!$product) {
                $errors[] = "Produk {$item['name']} tidak tersedia";
                continue;
            }
            
            if (!$product['is_active']) {
                $errors[] = "Produk {$item['name']} sudah tidak aktif";
                continue;
            }
            
            if ($product['stock_quantity'] < $item['quantity']) {
                if ($product['stock_quantity'] == 0) {
                    $errors[] = "Produk {$item['name']} sudah habis";
                } else {
                    $errors[] = "Produk {$item['name']} stok tersisa {$product['stock_quantity']}";
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Validate cart error: " . $e->getMessage());
        $errors[] = 'Terjadi kesalahan saat validasi keranjang';
    }
    
    return $errors;
}

/**
 * Sync cart with database (update prices, check stock)
 */
function syncCart() {
    initializeCart();
    
    if (empty($_SESSION['cart'])) {
        return ['success' => true, 'updated' => false];
    }
    
    try {
        $pdo = getDB();
        $updated = false;
        $newCart = [];
        
        foreach ($_SESSION['cart'] as $cartItem) {
            $stmt = $pdo->prepare("
                SELECT id, name, price, discount_price, stock_quantity, is_active
                FROM products 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$cartItem['id']]);
            $product = $stmt->fetch();
            
            if ($product) {
                $finalPrice = $product['discount_price'] && $product['discount_price'] > 0 
                             ? $product['discount_price'] 
                             : $product['price'];
                
                // Update price if changed
                if ($cartItem['price'] != $finalPrice) {
                    $cartItem['price'] = $finalPrice;
                    $updated = true;
                }
                
                // Adjust quantity if stock insufficient
                if ($cartItem['quantity'] > $product['stock_quantity']) {
                    $cartItem['quantity'] = $product['stock_quantity'];
                    $updated = true;
                }
                
                // Only keep items with quantity > 0
                if ($cartItem['quantity'] > 0) {
                    $newCart[] = $cartItem;
                } else {
                    $updated = true;
                }
            } else {
                // Product not found or inactive, remove from cart
                $updated = true;
            }
        }
        
        $_SESSION['cart'] = $newCart;
        
        return ['success' => true, 'updated' => $updated];
        
    } catch (Exception $e) {
        error_log("Sync cart error: " . $e->getMessage());
        return ['success' => false, 'updated' => false];
    }
}

/**
 * Calculate shipping cost based on cart and area
 */
function calculateShipping($shippingAreaId) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT shipping_cost 
            FROM shipping_areas 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$shippingAreaId]);
        $area = $stmt->fetch();
        
        if (!$area) {
            return 0;
        }
        
        $cartSummary = getCartSummary();
        $freeShippingMin = getAppSetting('free_shipping_min', 100000);
        
        // Free shipping if cart total meets minimum
        if ($cartSummary['subtotal'] >= $freeShippingMin) {
            return 0;
        }
        
        return $area['shipping_cost'];
        
    } catch (Exception $e) {
        error_log("Calculate shipping error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get cart count for display
 */
function getCartCount() {
    initializeCart();
    
    $totalItems = 0;
    foreach ($_SESSION['cart'] as $item) {
        $totalItems += $item['quantity'];
    }
    
    return $totalItems;
}

/**
 * Check if product is in cart
 */
function isInCart($productId) {
    initializeCart();
    
    foreach ($_SESSION['cart'] as $item) {
        if ($item['id'] == $productId) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get product quantity in cart
 */
function getCartProductQuantity($productId) {
    initializeCart();
    
    foreach ($_SESSION['cart'] as $item) {
        if ($item['id'] == $productId) {
            return $item['quantity'];
        }
    }
    
    return 0;
}

/**
 * Apply coupon/discount code
 */
function applyCoupon($couponCode) {
    // TODO: Implement coupon system
    return ['success' => false, 'message' => 'Fitur kupon belum tersedia'];
}

/**
 * Save cart to database for logged-in users
 */
function saveCartToDatabase($userId) {
    // TODO: Implement cart persistence for registered users
    return ['success' => false, 'message' => 'Fitur simpan keranjang belum tersedia'];
}

/**
 * Load cart from database for logged-in users
 */
function loadCartFromDatabase($userId) {
    // TODO: Implement cart loading for registered users
    return ['success' => false, 'message' => 'Fitur muat keranjang belum tersedia'];
}

/**
 * Merge guest cart with user cart on login
 */
function mergeCart($userId) {
    // TODO: Implement cart merging on user login
    return ['success' => false, 'message' => 'Fitur gabung keranjang belum tersedia'];
}

/**
 * Clean up expired cart items
 */
function cleanupExpiredCartItems($maxAge = 86400) { // 24 hours default
    initializeCart();
    
    if (empty($_SESSION['cart'])) {
        return;
    }
    
    $currentTime = time();
    $cleaned = false;
    
    foreach ($_SESSION['cart'] as $index => $item) {
        $age = $currentTime - ($item['added_at'] ?? $currentTime);
        if ($age > $maxAge) {
            unset($_SESSION['cart'][$index]);
            $cleaned = true;
        }
    }
    
    if ($cleaned) {
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex
    }
}

/**
 * Generate cart summary for email/notifications
 */
function getCartSummaryText() {
    $cartItems = getCartItems();
    $summary = getCartSummary();
    
    if ($summary['is_empty']) {
        return 'Keranjang kosong';
    }
    
    $text = "Keranjang Belanja:\n";
    $text .= "================\n";
    
    foreach ($cartItems as $item) {
        $text .= "• {$item['name']}\n";
        $text .= "  {$item['quantity']} x " . formatRupiah($item['price']) . " = " . formatRupiah($item['subtotal']) . "\n";
    }
    
    $text .= "================\n";
    $text .= "Total: " . formatRupiah($summary['subtotal']) . "\n";
    $text .= "Jumlah Item: {$summary['total_quantity']}\n";
    
    return $text;
}

/**
 * Convert cart to order format
 */
function prepareCartForOrder() {
    $cartItems = getCartItems();
    $orderItems = [];
    
    foreach ($cartItems as $item) {
        $orderItems[] = [
            'product_id' => $item['id'],
            'product_name' => $item['name'],
            'product_sku' => $item['sku'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'subtotal' => $item['subtotal']
        ];
    }
    
    return $orderItems;
}

/**
 * Restore cart from failed order
 */
function restoreCartFromOrder($orderItems) {
    clearCart();
    
    foreach ($orderItems as $item) {
        $_SESSION['cart'][] = [
            'id' => $item['product_id'],
            'name' => $item['product_name'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'added_at' => time()
        ];
    }
    
    return ['success' => true, 'message' => 'Keranjang berhasil dipulihkan'];
}

/**
 * Get recommended products based on cart
 */
function getCartRecommendations($limit = 4) {
    $cartItems = getCartItems();
    
    if (empty($cartItems)) {
        return [];
    }
    
    try {
        $pdo = getDB();
        
        // Get categories from cart items
        $categoryIds = [];
        foreach ($cartItems as $item) {
            // Get category from product
            $stmt = $pdo->prepare("SELECT category_id FROM products WHERE id = ?");
            $stmt->execute([$item['id']]);
            $product = $stmt->fetch();
            if ($product && $product['category_id']) {
                $categoryIds[] = $product['category_id'];
            }
        }
        
        if (empty($categoryIds)) {
            return [];
        }
        
        // Get cart product IDs to exclude
        $cartProductIds = array_column($cartItems, 'id');
        $excludeIds = implode(',', $cartProductIds);
        $categoryPlaceholders = implode(',', array_fill(0, count($categoryIds), '?'));
        
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name,
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                   CASE 
                       WHEN p.discount_price IS NOT NULL AND p.discount_price > 0 THEN p.discount_price 
                       ELSE p.price 
                   END as final_price
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.category_id IN ($categoryPlaceholders)
            AND p.id NOT IN ($excludeIds)
            AND p.is_active = 1
            AND p.stock_quantity > 0
            ORDER BY p.is_featured DESC, RAND()
            LIMIT ?
        ");
        
        $params = array_merge($categoryIds, [$limit]);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Get cart recommendations error: " . $e->getMessage());
        return [];
    }
}

/**
 * Check cart health and return warnings
 */
function getCartWarnings() {
    $warnings = [];
    $cartItems = getCartItems();
    
    if (empty($cartItems)) {
        return $warnings;
    }
    
    try {
        $pdo = getDB();
        
        foreach ($cartItems as $item) {
            $stmt = $pdo->prepare("
                SELECT stock_quantity, is_active, name, price, discount_price
                FROM products 
                WHERE id = ?
            ");
            $stmt->execute([$item['id']]);
            $product = $stmt->fetch();
            
            if (!$product) {
                $warnings[] = [
                    'type' => 'error',
                    'message' => "Produk '{$item['name']}' tidak ditemukan",
                    'product_id' => $item['id']
                ];
                continue;
            }
            
            if (!$product['is_active']) {
                $warnings[] = [
                    'type' => 'error',
                    'message' => "Produk '{$item['name']}' sudah tidak tersedia",
                    'product_id' => $item['id']
                ];
                continue;
            }
            
            if ($product['stock_quantity'] < $item['quantity']) {
                if ($product['stock_quantity'] == 0) {
                    $warnings[] = [
                        'type' => 'error',
                        'message' => "Produk '{$item['name']}' sudah habis",
                        'product_id' => $item['id']
                    ];
                } else {
                    $warnings[] = [
                        'type' => 'warning',
                        'message' => "Produk '{$item['name']}' stok terbatas ({$product['stock_quantity']} tersisa)",
                        'product_id' => $item['id']
                    ];
                }
            }
            
            // Check price changes
            $currentPrice = $product['discount_price'] && $product['discount_price'] > 0 
                           ? $product['discount_price'] 
                           : $product['price'];
            
            if ($currentPrice != $item['price']) {
                if ($currentPrice < $item['price']) {
                    $warnings[] = [
                        'type' => 'info',
                        'message' => "Harga produk '{$item['name']}' turun menjadi " . formatRupiah($currentPrice),
                        'product_id' => $item['id']
                    ];
                } else {
                    $warnings[] = [
                        'type' => 'warning',
                        'message' => "Harga produk '{$item['name']}' naik menjadi " . formatRupiah($currentPrice),
                        'product_id' => $item['id']
                    ];
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Get cart warnings error: " . $e->getMessage());
        $warnings[] = [
            'type' => 'error',
            'message' => 'Terjadi kesalahan saat memeriksa keranjang',
            'product_id' => null
        ];
    }
    
    return $warnings;
}

/**
 * Export cart data for backup/restore
 */
function exportCart() {
    initializeCart();
    
    return [
        'cart' => $_SESSION['cart'],
        'exported_at' => time(),
        'version' => '1.0'
    ];
}

/**
 * Import cart data from backup
 */
function importCart($cartData) {
    if (!isset($cartData['cart']) || !is_array($cartData['cart'])) {
        return ['success' => false, 'message' => 'Data keranjang tidak valid'];
    }
    
    $_SESSION['cart'] = $cartData['cart'];
    
    // Sync with current product data
    $syncResult = syncCart();
    
    if ($syncResult['updated']) {
        return ['success' => true, 'message' => 'Keranjang berhasil dipulihkan dengan beberapa penyesuaian'];
    } else {
        return ['success' => true, 'message' => 'Keranjang berhasil dipulihkan'];
    }
}
?>