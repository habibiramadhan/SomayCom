<!-- pages/order-success.php -->
<?php
require_once '../config.php';

// Get order number from URL
$orderNumber = $_GET['order'] ?? '';

if (empty($orderNumber)) {
    header('Location: ../index.php');
    exit;
}

try {
    $pdo = getDB();
    
    // Get order data
    $stmt = $pdo->prepare("
        SELECT o.*, sa.area_name, sa.estimated_delivery
        FROM orders o
        LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
        WHERE o.order_number = ?
    ");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
    
    if (!$order) {
        header('Location: ../index.php');
        exit;
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as current_product_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ");
    $stmt->execute([$order['id']]);
    $orderItems = $stmt->fetchAll();
    
    // Get app settings
    $site_name = getAppSetting('site_name', 'Somay Ecommerce');
    $whatsapp_number = getAppSetting('whatsapp_number', '6281234567890');
    $site_phone = getAppSetting('site_phone', '081234567890');
    
} catch (Exception $e) {
    error_log("Order success page error: " . $e->getMessage());
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Berhasil - <?php echo htmlspecialchars($site_name); ?></title>
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
    <style>
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #FF6B35;
            animation: confetti-fall 3s linear infinite;
        }
        
        .confetti:nth-child(odd) {
            background: #F7931E;
            width: 8px;
            height: 8px;
            animation-duration: 2.5s;
        }
        
        .confetti:nth-child(even) {
            animation-duration: 3.5s;
        }
        
        @keyframes confetti-fall {
            to {
                transform: translateY(100vh) rotate(360deg);
            }
        }
        
        .success-bounce {
            animation: successBounce 0.6s ease-out;
        }
        
        @keyframes successBounce {
            0% { transform: scale(0); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-blue-50 min-h-screen">
    <!-- Confetti Animation -->
    <div id="confetti-container" class="fixed inset-0 pointer-events-none overflow-hidden z-10"></div>

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
                    <a href="../index.php" class="text-gray-600 hover:text-primary">
                        <i class="fas fa-home mr-2"></i>Beranda
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
                        <div class="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center">
                            <i class="fas fa-check text-sm"></i>
                        </div>
                        <span class="ml-2 text-green-600 font-medium">Checkout</span>
                    </div>
                    <div class="w-16 h-1 bg-green-500"></div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center success-bounce">
                            <i class="fas fa-check text-sm"></i>
                        </div>
                        <span class="ml-2 text-green-600 font-medium">Selesai</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Message -->
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-green-500 to-green-600 p-8 text-center text-white">
                    <div class="w-20 h-20 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4 success-bounce">
                        <i class="fas fa-check text-3xl"></i>
                    </div>
                    <h1 class="text-3xl font-bold mb-2">Pesanan Berhasil Dibuat!</h1>
                    <p class="text-green-100 text-lg">Terima kasih atas kepercayaan Anda</p>
                </div>

                <!-- Order Details -->
                <div class="p-8">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Order Info -->
                        <div class="space-y-6">
                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Detail Pesanan</h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Nomor Pesanan:</span>
                                        <span class="font-bold text-primary"><?php echo htmlspecialchars($order['order_number']); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Tanggal:</span>
                                        <span class="font-medium"><?php echo formatDate($order['created_at'], 'd M Y, H:i'); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Status:</span>
                                        <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-sm font-medium">
                                            Menunggu Konfirmasi
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Total Bayar:</span>
                                        <span class="font-bold text-xl text-primary"><?php echo formatRupiah($order['total_amount']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pengiriman</h3>
                                <div class="space-y-3">
                                    <div>
                                        <span class="text-gray-600 block">Nama:</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600 block">Telepon:</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600 block">Area:</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($order['area_name'] ?? 'Area tidak diketahui'); ?></span>
                                        <?php if ($order['estimated_delivery']): ?>
                                        <span class="text-sm text-gray-500 block">Estimasi: <?php echo htmlspecialchars($order['estimated_delivery']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="text-gray-600 block">Alamat:</span>
                                        <span class="font-medium"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></span>
                                    </div>
                                </div>
                            </div>

                            <?php if ($order['payment_method'] === 'transfer'): ?>
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
                                <h3 class="text-lg font-semibold text-blue-900 mb-3">
                                    <i class="fas fa-info-circle mr-2"></i>Instruksi Pembayaran
                                </h3>
                                <div class="text-blue-800 space-y-2">
                                    <p class="font-medium">Silakan transfer ke rekening berikut:</p>
                                    <div class="bg-white rounded-lg p-4 border border-blue-200">
                                        <p><strong>Bank BCA</strong></p>
                                        <p>No. Rekening: <strong>1234567890</strong></p>
                                        <p>Atas Nama: <strong>Somay Distributor</strong></p>
                                        <p class="text-red-600 mt-2">Total: <strong><?php echo formatRupiah($order['total_amount']); ?></strong></p>
                                    </div>
                                    <p class="text-sm">Setelah transfer, kirim bukti pembayaran via WhatsApp</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Order Items -->
                        <div class="space-y-6">
                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Item Pesanan</h3>
                                <div class="space-y-4">
                                    <?php foreach ($orderItems as $item): ?>
                                    <div class="flex items-center space-x-4">
                                        <div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center flex-shrink-0">
                                            <?php if (!empty($item['product_image'])): ?>
                                            <img src="../uploads/products/<?php echo htmlspecialchars($item['product_image']); ?>" 
                                                 class="w-16 h-16 rounded-lg object-cover" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                            <?php else: ?>
                                            <i class="fas fa-utensils text-gray-400"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-medium"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($item['product_sku']); ?></p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo formatRupiah($item['price']); ?> × <?php echo number_format($item['quantity']); ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-medium"><?php echo formatRupiah($item['subtotal']); ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Order Summary -->
                                <div class="border-t pt-4 mt-4 space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Subtotal:</span>
                                        <span><?php echo formatRupiah($order['subtotal']); ?></span>
                                    </div>
                                    <?php if ($order['shipping_cost'] > 0): ?>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Ongkos Kirim:</span>
                                        <span><?php echo formatRupiah($order['shipping_cost']); ?></span>
                                    </div>
                                    <?php else: ?>
                                    <div class="flex justify-between text-sm text-green-600">
                                        <span>Ongkos Kirim:</span>
                                        <span>GRATIS</span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex justify-between text-lg font-bold border-t pt-2">
                                        <span>Total:</span>
                                        <span class="text-primary"><?php echo formatRupiah($order['total_amount']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="border-t pt-8 mt-8">
                        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                            <?php 
                            $whatsappMessage = "Halo, saya telah melakukan pemesanan dengan nomor: {$order['order_number']}. ";
                            if ($order['payment_method'] === 'transfer') {
                                $whatsappMessage .= "Saya akan segera melakukan pembayaran.";
                            } else {
                                $whatsappMessage .= "Mohon dikonfirmasi untuk pengiriman COD.";
                            }
                            $whatsappUrl = "https://wa.me/{$whatsapp_number}?text=" . urlencode($whatsappMessage);
                            ?>
                            
                            <a href="<?php echo $whatsappUrl; ?>" target="_blank" 
                               class="bg-green-500 text-white px-8 py-4 rounded-xl font-semibold hover:bg-green-600 transition-colors inline-flex items-center text-lg">
                                <i class="fab fa-whatsapp mr-3 text-xl"></i>
                                <?php echo $order['payment_method'] === 'transfer' ? 'Kirim Bukti Bayar' : 'Konfirmasi Pesanan'; ?>
                            </a>
                            
                            <a href="track-order.php" 
                               class="bg-primary text-white px-8 py-4 rounded-xl font-semibold hover:bg-secondary transition-colors inline-flex items-center text-lg">
                                <i class="fas fa-search mr-3"></i>
                                Lacak Pesanan
                            </a>
                            
                            <a href="../index.php" 
                               class="bg-gray-500 text-white px-8 py-4 rounded-xl font-semibold hover:bg-gray-600 transition-colors inline-flex items-center text-lg">
                                <i class="fas fa-home mr-3"></i>
                                Kembali ke Beranda
                            </a>
                        </div>
                    </div>

                    <!-- Additional Info -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mt-8">
                        <h3 class="text-lg font-semibold text-yellow-900 mb-3">
                            <i class="fas fa-clock mr-2"></i>Langkah Selanjutnya
                        </h3>
                        <div class="text-yellow-800 space-y-2">
                            <?php if ($order['payment_method'] === 'transfer'): ?>
                            <p>1. Lakukan pembayaran ke rekening yang tertera</p>
                            <p>2. Kirim bukti pembayaran via WhatsApp</p>
                            <p>3. Tunggu konfirmasi dari tim kami</p>
                            <p>4. Pesanan akan segera diproses dan dikirim</p>
                            <?php else: ?>
                            <p>1. Tim kami akan menghubungi Anda untuk konfirmasi</p>
                            <p>2. Pesanan akan diproses dan dikirim</p>
                            <p>3. Siapkan uang pas saat kurir tiba</p>
                            <p>4. Pembayaran dilakukan saat barang diterima</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Contact Info -->
                    <div class="text-center mt-8 p-6 bg-gray-50 rounded-xl">
                        <h4 class="font-semibold text-gray-900 mb-2">Butuh Bantuan?</h4>
                        <p class="text-gray-600 mb-4">Tim customer service kami siap membantu Anda</p>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <a href="https://wa.me/<?php echo $whatsapp_number; ?>" target="_blank" 
                               class="text-green-600 hover:text-green-700 font-medium">
                                <i class="fab fa-whatsapp mr-2"></i>WhatsApp: <?php echo $site_phone; ?>
                            </a>
                            <a href="tel:<?php echo $site_phone; ?>" class="text-blue-600 hover:text-blue-700 font-medium">
                                <i class="fas fa-phone mr-2"></i>Telepon: <?php echo $site_phone; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Confetti animation
        function createConfetti() {
            const container = document.getElementById('confetti-container');
            const colors = ['#FF6B35', '#F7931E', '#FFD23F', '#4F46E5', '#059669'];
            
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.animationDelay = Math.random() * 3 + 's';
                confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
                container.appendChild(confetti);
                
                // Remove confetti after animation
                setTimeout(() => {
                    confetti.remove();
                }, 5000);
            }
        }
        
        // Start confetti animation when page loads
        document.addEventListener('DOMContentLoaded', function() {
            createConfetti();
            
            // Create confetti burst every 3 seconds for first 10 seconds
            let confettiCount = 0;
            const confettiInterval = setInterval(() => {
                confettiCount++;
                if (confettiCount < 3) {
                    createConfetti();
                } else {
                    clearInterval(confettiInterval);
                }
            }, 3000);
        });
        
        // Copy order number to clipboard
        function copyOrderNumber() {
            const orderNumber = '<?php echo $order['order_number']; ?>';
            navigator.clipboard.writeText(orderNumber).then(() => {
                alert('Nomor pesanan berhasil disalin!');
            });
        }
        
        // Add click event to order number
        document.addEventListener('DOMContentLoaded', function() {
            const orderNumberElement = document.querySelector('.text-primary');
            if (orderNumberElement) {
                orderNumberElement.style.cursor = 'pointer';
                orderNumberElement.title = 'Klik untuk menyalin nomor pesanan';
                orderNumberElement.addEventListener('click', copyOrderNumber);
            }
        });
    </script>
</body>
</html>