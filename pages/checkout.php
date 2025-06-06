<!-- pages/checkout.php -->
<?php
require_once '../config.php';

// Redirect jika cart kosong
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: products.php');
    exit;
}

$cart = $_SESSION['cart'];
$cartTotal = 0;
$cartItems = [];

try {
    $pdo = getDB();
    
    // Validasi dan ambil data produk dari cart
    foreach ($cart as $item) {
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
        $stmt->execute([$item['id']]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Cek stok
            if ($product['stock_quantity'] < $item['quantity']) {
                $item['quantity'] = $product['stock_quantity'];
                if ($item['quantity'] == 0) {
                    continue; // Skip produk yang habis
                }
            }
            
            $subtotal = $product['final_price'] * $item['quantity'];
            $cartTotal += $subtotal;
            
            $cartItems[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'sku' => $product['sku'],
                'price' => $product['final_price'],
                'quantity' => $item['quantity'],
                'subtotal' => $subtotal,
                'image' => $product['primary_image'],
                'stock' => $product['stock_quantity']
            ];
        }
    }
    
    // Redirect jika tidak ada item valid
    if (empty($cartItems)) {
        unset($_SESSION['cart']);
        header('Location: products.php?message=cart_empty');
        exit;
    }
    
    // Get shipping areas
    $stmt = $pdo->prepare("SELECT * FROM shipping_areas WHERE is_active = 1 ORDER BY area_name");
    $stmt->execute();
    $shippingAreas = $stmt->fetchAll();
    
    // Get app settings
    $site_name = getAppSetting('site_name', 'Somay Ecommerce');
    $min_order_amount = getAppSetting('min_order_amount', 25000);
    $free_shipping_min = getAppSetting('free_shipping_min', 100000);
    $whatsapp_number = getAppSetting('whatsapp_number', '6281234567890');
    
} catch (Exception $e) {
    error_log("Checkout error: " . $e->getMessage());
    header('Location: products.php?message=error');
    exit;
}

// Handle form submission
$errors = [];
$success = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $formData = [
        'customer_name' => trim($_POST['customer_name'] ?? ''),
        'customer_phone' => trim($_POST['customer_phone'] ?? ''),
        'customer_email' => trim($_POST['customer_email'] ?? ''),
        'shipping_area_id' => (int)($_POST['shipping_area_id'] ?? 0),
        'shipping_address' => trim($_POST['shipping_address'] ?? ''),
        'payment_method' => $_POST['payment_method'] ?? '',
        'notes' => trim($_POST['notes'] ?? '')
    ];
    
    // Validation
    if (empty($formData['customer_name'])) {
        $errors[] = 'Nama lengkap harus diisi';
    }
    
    if (empty($formData['customer_phone'])) {
        $errors[] = 'Nomor telepon harus diisi';
    } elseif (!preg_match('/^(\+62|62|0)[0-9]{8,13}$/', $formData['customer_phone'])) {
        $errors[] = 'Format nomor telepon tidak valid';
    }
    
    if (!empty($formData['customer_email']) && !filter_var($formData['customer_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid';
    }
    
    if (empty($formData['shipping_area_id'])) {
        $errors[] = 'Area pengiriman harus dipilih';
    }
    
    if (empty($formData['shipping_address'])) {
        $errors[] = 'Alamat pengiriman harus diisi';
    }
    
    if (!in_array($formData['payment_method'], ['cod', 'transfer'])) {
        $errors[] = 'Metode pembayaran tidak valid';
    }
    
    // Check minimum order
    if ($cartTotal < $min_order_amount) {
        $errors[] = 'Minimal pembelian ' . formatRupiah($min_order_amount);
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Get shipping cost
            $stmt = $pdo->prepare("SELECT shipping_cost FROM shipping_areas WHERE id = ? AND is_active = 1");
            $stmt->execute([$formData['shipping_area_id']]);
            $shippingArea = $stmt->fetch();
            
            if (!$shippingArea) {
                throw new Exception('Area pengiriman tidak valid');
            }
            
            $shippingCost = $cartTotal >= $free_shipping_min ? 0 : $shippingArea['shipping_cost'];
            $totalAmount = $cartTotal + $shippingCost;
            
            // Generate order number
            $orderNumber = generateOrderNumber();
            
            // Format phone number
            $phone = preg_replace('/[^0-9]/', '', $formData['customer_phone']);
            if (substr($phone, 0, 1) === '0') {
                $phone = '62' . substr($phone, 1);
            } elseif (substr($phone, 0, 2) !== '62') {
                $phone = '62' . $phone;
            }
            
            // Create order
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    order_number, customer_name, customer_phone, customer_email,
                    shipping_area_id, shipping_address, shipping_cost,
                    subtotal, total_amount, payment_method, payment_status,
                    order_status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?)
            ");
            
            $success = $stmt->execute([
                $orderNumber,
                $formData['customer_name'],
                $phone,
                $formData['customer_email'],
                $formData['shipping_area_id'],
                $formData['shipping_address'],
                $shippingCost,
                $cartTotal,
                $totalAmount,
                $formData['payment_method'],
                $formData['notes']
            ]);
            
            if (!$success) {
                throw new Exception('Gagal membuat pesanan');
            }
            
            $orderId = $pdo->lastInsertId();
            
            // Create order items
            $stmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id, product_id, product_name, product_sku,
                    price, quantity, subtotal
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($cartItems as $item) {
                $success = $stmt->execute([
                    $orderId,
                    $item['id'],
                    $item['name'],
                    $item['sku'],
                    $item['price'],
                    $item['quantity'],
                    $item['subtotal']
                ]);
                
                if (!$success) {
                    throw new Exception('Gagal menyimpan item pesanan');
                }
            }
            
            $pdo->commit();
            
            // Clear cart
            unset($_SESSION['cart']);
            
            // Redirect to success page
            header('Location: order-success.php?order=' . $orderNumber);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Order creation error: " . $e->getMessage());
            $errors[] = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo htmlspecialchars($site_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#FF6B35',
                        secondary: '#F7931E',
                        accent: '#FFD23F',
                        dark: '#1E293B',
                        light: '#F8FAFC'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-2">
                    <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
                        <i class="fas fa-utensils text-white text-lg"></i>
                    </div>
                    <a href="../index.php" class="text-2xl font-bold text-dark"><?php echo htmlspecialchars($site_name); ?></a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="products.php" class="text-gray-600 hover:text-primary">
                        <i class="fas fa-arrow-left mr-2"></i>Kembali Belanja
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- Progress Bar -->
        <div class="mb-8">
            <div class="flex items-center justify-center">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center">
                            <i class="fas fa-check text-sm"></i>
                        </div>
                        <span class="ml-2 text-green-600 font-medium">Keranjang</span>
                    </div>
                    <div class="w-16 h-1 bg-green-500"></div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center">
                            <span class="text-sm font-bold">2</span>
                        </div>
                        <span class="ml-2 text-primary font-medium">Checkout</span>
                    </div>
                    <div class="w-16 h-1 bg-gray-300"></div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center">
                            <span class="text-sm font-bold">3</span>
                        </div>
                        <span class="ml-2 text-gray-500">Selesai</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Form Checkout -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Informasi Pelanggan -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pelanggan</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap *</label>
                            <input type="text" name="customer_name" value="<?php echo htmlspecialchars($formData['customer_name'] ?? ''); ?>" 
                                   required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Telepon *</label>
                            <input type="tel" name="customer_phone" value="<?php echo htmlspecialchars($formData['customer_phone'] ?? ''); ?>" 
                                   required placeholder="08xxxxxxxxx" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email (Opsional)</label>
                            <input type="email" name="customer_email" value="<?php echo htmlspecialchars($formData['customer_email'] ?? ''); ?>" 
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        </div>
                    </div>
                </div>

                <!-- Informasi Pengiriman -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pengiriman</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Area Pengiriman *</label>
                            <select name="shipping_area_id" id="shipping_area" required onchange="updateShippingCost()" 
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                                <option value="">Pilih Area Pengiriman</option>
                                <?php foreach ($shippingAreas as $area): ?>
                                <option value="<?php echo $area['id']; ?>" 
                                        data-cost="<?php echo $area['shipping_cost']; ?>"
                                        data-delivery="<?php echo htmlspecialchars($area['estimated_delivery']); ?>"
                                        <?php echo ($formData['shipping_area_id'] ?? 0) == $area['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($area['area_name']); ?> - <?php echo formatRupiah($area['shipping_cost']); ?>
                                    <?php if ($area['estimated_delivery']): ?>(<?php echo htmlspecialchars($area['estimated_delivery']); ?>)<?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Alamat Lengkap *</label>
                            <textarea name="shipping_address" rows="3" required 
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                      placeholder="Masukkan alamat lengkap untuk pengiriman..."><?php echo htmlspecialchars($formData['shipping_address'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Catatan Pesanan (Opsional)</label>
                            <textarea name="notes" rows="2" 
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                      placeholder="Catatan khusus untuk pesanan Anda..."><?php echo htmlspecialchars($formData['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Metode Pembayaran -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Metode Pembayaran</h3>
                    <div class="space-y-3">
                        <label class="flex items-center p-4 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="payment_method" value="cod" 
                                   <?php echo ($formData['payment_method'] ?? '') === 'cod' ? 'checked' : ''; ?> 
                                   class="text-primary focus:ring-primary">
                            <div class="ml-3">
                                <div class="font-medium">Cash on Delivery (COD)</div>
                                <div class="text-sm text-gray-500">Bayar saat barang diterima</div>
                            </div>
                        </label>
                        <label class="flex items-center p-4 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="payment_method" value="transfer" 
                                   <?php echo ($formData['payment_method'] ?? '') === 'transfer' ? 'checked' : ''; ?> 
                                   class="text-primary focus:ring-primary">
                            <div class="ml-3">
                                <div class="font-medium">Transfer Bank</div>
                                <div class="text-sm text-gray-500">Transfer ke rekening yang akan diberikan</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="space-y-6">
                <!-- Cart Items -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Pesanan Anda</h3>
                    <div class="space-y-4">
                        <?php foreach ($cartItems as $item): ?>
                        <div class="flex items-center space-x-3 pb-4 border-b last:border-b-0 last:pb-0">
                            <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center flex-shrink-0">
                                <?php if ($item['image']): ?>
                                <img src="../uploads/products/<?php echo htmlspecialchars($item['image']); ?>" 
                                     class="w-12 h-12 rounded-lg object-cover" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php else: ?>
                                <i class="fas fa-utensils text-gray-400"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-medium text-sm"><?php echo htmlspecialchars($item['name']); ?></h4>
                                <p class="text-xs text-gray-500"><?php echo formatRupiah($item['price']); ?> x <?php echo $item['quantity']; ?></p>
                            </div>
                            <div class="text-sm font-medium"><?php echo formatRupiah($item['subtotal']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order Total -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Ringkasan Biaya</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="font-medium"><?php echo formatRupiah($cartTotal); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Ongkos Kirim:</span>
                            <span id="shipping-cost" class="font-medium">Pilih area</span>
                        </div>
                        <div id="free-shipping-info" class="text-sm text-green-600 hidden">
                            <i class="fas fa-check mr-1"></i>Gratis ongkir! (Min. <?php echo formatRupiah($free_shipping_min); ?>)
                        </div>
                        <hr>
                        <div class="flex justify-between text-lg font-bold">
                            <span>Total:</span>
                            <span id="total-amount" class="text-primary"><?php echo formatRupiah($cartTotal); ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" class="w-full bg-primary text-white py-4 rounded-lg font-semibold hover:bg-secondary transition-colors">
                            <i class="fas fa-shopping-cart mr-2"></i>
                            Buat Pesanan
                        </button>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <a href="products.php" class="text-sm text-gray-500 hover:text-primary">
                            <i class="fas fa-arrow-left mr-1"></i>Kembali ke Belanja
                        </a>
                    </div>
                </div>

                <!-- Help Info -->
                <div class="bg-blue-50 rounded-xl p-6">
                    <h4 class="font-semibold text-blue-900 mb-2">Butuh Bantuan?</h4>
                    <p class="text-sm text-blue-700 mb-3">Tim kami siap membantu Anda dengan pesanan</p>
                    <a href="https://wa.me/<?php echo htmlspecialchars($whatsapp_number); ?>?text=Halo, saya butuh bantuan dengan pesanan" 
                       target="_blank" class="bg-green-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-600 transition-colors inline-flex items-center">
                        <i class="fab fa-whatsapp mr-2"></i>Chat WhatsApp
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script>
        const cartTotal = <?php echo $cartTotal; ?>;
        const freeShippingMin = <?php echo $free_shipping_min; ?>;
        
        function updateShippingCost() {
            const shippingSelect = document.getElementById('shipping_area');
            const selectedOption = shippingSelect.options[shippingSelect.selectedIndex];
            const shippingCostElement = document.getElementById('shipping-cost');
            const totalAmountElement = document.getElementById('total-amount');
            const freeShippingInfo = document.getElementById('free-shipping-info');
            
            if (selectedOption.value) {
                let shippingCost = parseInt(selectedOption.dataset.cost);
                
                // Check for free shipping
                if (cartTotal >= freeShippingMin) {
                    shippingCost = 0;
                    freeShippingInfo.classList.remove('hidden');
                } else {
                    freeShippingInfo.classList.add('hidden');
                }
                
                const total = cartTotal + shippingCost;
                
                shippingCostElement.textContent = formatRupiah(shippingCost);
                totalAmountElement.textContent = formatRupiah(total);
            } else {
                shippingCostElement.textContent = 'Pilih area';
                totalAmountElement.textContent = formatRupiah(cartTotal);
                freeShippingInfo.classList.add('hidden');
            }
        }
        
        function formatRupiah(amount) {
            return 'Rp ' + amount.toLocaleString('id-ID');
        }
        
        // Initialize shipping cost if area already selected
        document.addEventListener('DOMContentLoaded', function() {
            updateShippingCost();
        });
    </script>
</body>
</html>