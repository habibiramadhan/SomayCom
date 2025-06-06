<!-- includes/ajax-cart.php -->
<?php
session_start();
require_once '../config.php';
require_once 'cart-functions.php';

header('Content-Type: application/json');

// Get action from request
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        handleAddToCart();
        break;
    case 'update':
        handleUpdateQuantity();
        break;
    case 'remove':
        handleRemoveFromCart();
        break;
    case 'clear':
        handleClearCart();
        break;
    case 'get':
        handleGetCart();
        break;
    case 'count':
        handleGetCartCount();
        break;
    case 'sync':
        handleSyncCart();
        break;
    case 'validate':
        handleValidateCart();
        break;
    case 'shipping':
        handleCalculateShipping();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Handle add to cart
 */
function handleAddToCart() {
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    if ($productId <= 0 || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
        return;
    }
    
    $result = addToCart($productId, $quantity);
    
    if ($result['success']) {
        $cartSummary = getCartSummary();
        $result['cart_count'] = $cartSummary['total_quantity'];
        $result['cart_total'] = formatRupiah($cartSummary['subtotal']);
    }
    
    echo json_encode($result);
}

/**
 * Handle update quantity
 */
function handleUpdateQuantity() {
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Product ID tidak valid']);
        return;
    }
    
    $result = updateCartQuantity($productId, $quantity);
    
    if ($result['success']) {
        $cartSummary = getCartSummary();
        $result['cart_count'] = $cartSummary['total_quantity'];
        $result['cart_total'] = formatRupiah($cartSummary['subtotal']);
        $result['item_subtotal'] = $quantity > 0 ? formatRupiah(getCartItems()[$productId]['price'] * $quantity) : 0;
    }
    
    echo json_encode($result);
}

/**
 * Handle remove from cart
 */
function handleRemoveFromCart() {
    $productId = (int)($_POST['product_id'] ?? 0);
    
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Product ID tidak valid']);
        return;
    }
    
    $result = removeFromCart($productId);
    
    if ($result['success']) {
        $cartSummary = getCartSummary();
        $result['cart_count'] = $cartSummary['total_quantity'];
        $result['cart_total'] = formatRupiah($cartSummary['subtotal']);
        $result['is_empty'] = $cartSummary['is_empty'];
    }
    
    echo json_encode($result);
}

/**
 * Handle clear cart
 */
function handleClearCart() {
    $result = clearCart();
    
    if ($result['success']) {
        $result['cart_count'] = 0;
        $result['cart_total'] = formatRupiah(0);
    }
    
    echo json_encode($result);
}

/**
 * Handle get cart data
 */
function handleGetCart() {
    $cartItems = getCartItems();
    $cartSummary = getCartSummary();
    $warnings = getCartWarnings();
    
    // Format cart items for response
    $formattedItems = [];
    foreach ($cartItems as $item) {
        $formattedItems[] = [
            'id' => $item['id'],
            'name' => $item['name'],
            'sku' => $item['sku'],
            'price' => $item['price'],
            'price_formatted' => formatRupiah($item['price']),
            'quantity' => $item['quantity'],
            'subtotal' => $item['subtotal'],
            'subtotal_formatted' => formatRupiah($item['subtotal']),
            'image' => $item['image'],
            'category' => $item['category'],
            'stock' => $item['stock'],
            'in_stock' => $item['stock'] >= $item['quantity']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'items' => $formattedItems,
        'summary' => [
            'total_items' => $cartSummary['total_items'],
            'total_quantity' => $cartSummary['total_quantity'],
            'subtotal' => $cartSummary['subtotal'],
            'subtotal_formatted' => formatRupiah($cartSummary['subtotal']),
            'total_weight' => $cartSummary['total_weight'],
            'is_empty' => $cartSummary['is_empty']
        ],
        'warnings' => $warnings
    ]);
}

/**
 * Handle get cart count
 */
function handleGetCartCount() {
    $count = getCartCount();
    echo json_encode(['success' => true, 'count' => $count]);
}

/**
 * Handle sync cart
 */
function handleSyncCart() {
    $result = syncCart();
    
    if ($result['success']) {
        $cartSummary = getCartSummary();
        $result['cart_count'] = $cartSummary['total_quantity'];
        $result['cart_total'] = formatRupiah($cartSummary['subtotal']);
        $result['warnings'] = getCartWarnings();
        
        if ($result['updated']) {
            $result['message'] = 'Keranjang telah diperbarui sesuai ketersediaan produk';
        } else {
            $result['message'] = 'Keranjang sudah sinkron';
        }
    }
    
    echo json_encode($result);
}

/**
 * Handle validate cart
 */
function handleValidateCart() {
    $errors = validateCart();
    
    if (empty($errors)) {
        $cartSummary = getCartSummary();
        echo json_encode([
            'success' => true,
            'valid' => true,
            'message' => 'Keranjang valid untuk checkout',
            'summary' => $cartSummary
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'valid' => false,
            'errors' => $errors
        ]);
    }
}

/**
 * Handle calculate shipping
 */
function handleCalculateShipping() {
    $shippingAreaId = (int)($_POST['shipping_area_id'] ?? 0);
    
    if ($shippingAreaId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Area pengiriman tidak valid']);
        return;
    }
    
    try {
        $shippingCost = calculateShipping($shippingAreaId);
        $cartSummary = getCartSummary();
        $freeShippingMin = getAppSetting('free_shipping_min', 100000);
        $total = $cartSummary['subtotal'] + $shippingCost;
        
        echo json_encode([
            'success' => true,
            'shipping_cost' => $shippingCost,
            'shipping_cost_formatted' => formatRupiah($shippingCost),
            'subtotal' => $cartSummary['subtotal'],
            'subtotal_formatted' => formatRupiah($cartSummary['subtotal']),
            'total' => $total,
            'total_formatted' => formatRupiah($total),
            'is_free_shipping' => $shippingCost == 0,
            'free_shipping_min' => $freeShippingMin,
            'free_shipping_min_formatted' => formatRupiah($freeShippingMin)
        ]);
        
    } catch (Exception $e) {
        error_log("Calculate shipping AJAX error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat menghitung ongkir']);
    }
}
?>