<!-- pages/track-order.php -->
<?php
require_once '../config.php';

$order = null;
$orderItems = [];
$error = '';
$orderNumber = $_GET['order'] ?? $_POST['order_number'] ?? '';

if (!empty($orderNumber)) {
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
        
        if ($order) {
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
        } else {
            $error = 'Pesanan tidak ditemukan. Mohon periksa kembali nomor pesanan Anda.';
        }
        
    } catch (Exception $e) {
        error_log("Track order error: " . $e->getMessage());
        $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
    }
}

// Get app settings
$site_name = getAppSetting('site_name', 'Somay Ecommerce');
$whatsapp_number = getAppSetting('whatsapp_number', '6281234567890');
$site_phone = getAppSetting('site_phone', '081234567890');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lacak Pesanan - <?php echo htmlspecialchars($site_name); ?></title>
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
                    <a href="../index.php" class="text-gray-600 hover:text-primary">
                        <i class="fas fa-home mr-2"></i>Beranda
                    </a>
                    <a href="products.php" class="text-gray-600 hover:text-primary">
                        <i class="fas fa-shopping-bag mr-2"></i>Produk
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Page Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">Lacak Pesanan Anda</h1>
                <p class="text-gray-600">Masukkan nomor pesanan untuk melihat status terkini</p>
            </div>

            <!-- Search Form -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <form method="POST" class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Pesanan</label>
                        <input type="text" name="order_number" value="<?php echo htmlspecialchars($orderNumber); ?>" 
                               placeholder="Contoh: ORD240523001" required
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-lg">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full sm:w-auto bg-primary text-white px-8 py-3 rounded-lg font-semibold hover:bg-secondary transition-colors">
                            <i class="fas fa-search mr-2"></i>Lacak Pesanan
                        </button>
                    </div>
                </form>
            </div>

            <?php if (!empty($error)): ?>
            <!-- Error Message -->
            <div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-lg mb-8">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($order): ?>
            <!-- Order Found -->
            <div class="space-y-6">
                <!-- Order Status -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-primary to-secondary p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($order['order_number']); ?></h2>
                                <p class="text-orange-100">Pesanan dibuat pada <?php echo formatDate($order['created_at'], 'd M Y, H:i'); ?></p>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-bold"><?php echo formatRupiah($order['total_amount']); ?></div>
                                <div class="text-orange-100"><?php echo ucfirst($order['payment_method']); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Timeline -->
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">Status Pesanan</h3>
                        <div class="relative">
                            <?php
                            $statuses = [
                                'pending' => ['label' => 'Pesanan Diterima', 'icon' => 'fa-clock', 'color' => 'yellow'],
                                'confirmed' => ['label' => 'Pesanan Dikonfirmasi', 'icon' => 'fa-check', 'color' => 'blue'],
                                'processing' => ['label' => 'Sedang Diproses', 'icon' => 'fa-cog', 'color' => 'purple'],
                                'shipped' => ['label' => 'Dalam Pengiriman', 'icon' => 'fa-truck', 'color' => 'indigo'],
                                'delivered' => ['label' => 'Pesanan Selesai', 'icon' => 'fa-check-circle', 'color' => 'green'],
                                'cancelled' => ['label' => 'Pesanan Dibatalkan', 'icon' => 'fa-times-circle', 'color' => 'red']
                            ];

                            $statusKeys = array_keys($statuses);
                            $currentStatusIndex = array_search($order['order_status'], $statusKeys);
                            $isDelivered = $order['order_status'] === 'delivered';
                            $isCancelled = $order['order_status'] === 'cancelled';
                            ?>

                            <div class="space-y-6">
                                <?php foreach ($statuses as $statusKey => $statusInfo): ?>
                                <?php 
                                $isActive = $statusKey === $order['order_status'];
                                $isPassed = array_search($statusKey, $statusKeys) < $currentStatusIndex;
                                $isShown = $isPassed || $isActive || (!$isCancelled && !$isPassed && !$isActive);
                                
                                // Don't show cancelled unless it's the current status
                                if ($statusKey === 'cancelled' && !$isCancelled) continue;
                                
                                // Don't show other statuses if cancelled
                                if ($isCancelled && $statusKey !== 'cancelled' && $statusKey !== 'pending') continue;
                                ?>
                                
                                <?php if ($isShown): ?>
                                <div class="flex items-center">
                                    <div class="flex items-center justify-center w-12 h-12 rounded-full flex-shrink-0 
                                                <?php echo $isActive ? "bg-{$statusInfo['color']}-500 text-white" : 
                                                          ($isPassed ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-400'); ?>">
                                        <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <h4 class="font-medium <?php echo $isActive || $isPassed ? 'text-gray-900' : 'text-gray-500'; ?>">
                                            <?php echo $statusInfo['label']; ?>
                                        </h4>
                                        <?php if ($isActive): ?>
                                        <p class="text-sm text-<?php echo $statusInfo['color']; ?>-600 font-medium">Status saat ini</p>
                                        <?php elseif ($isPassed): ?>
                                        <p class="text-sm text-green-600">Selesai</p>
                                        <?php endif; ?>
                                        
                                        <?php
                                        // Show timestamps
                                        $timestamp = null;
                                        switch ($statusKey) {
                                            case 'pending':
                                                $timestamp = $order['created_at'];
                                                break;
                                            case 'confirmed':
                                                $timestamp = $order['confirmed_at'];
                                                break;
                                            case 'shipped':
                                                $timestamp = $order['shipped_at'];
                                                break;
                                            case 'delivered':
                                                $timestamp = $order['delivered_at'];
                                                break;
                                        }
                                        
                                        if ($timestamp):
                                        ?>
                                        <p class="text-xs text-gray-500"><?php echo formatDate($timestamp, 'd M Y, H:i'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Details -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Order Items -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Item Pesanan</h3>
                        <div class="space-y-4">
                            <?php foreach ($orderItems as $item): ?>
                            <div class="flex items-center space-x-4 pb-4 border-b last:border-b-0 last:pb-0">
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
                                    <p class="text-sm text-gray-500">SKU: <?php echo htmlspecialchars($item['product_sku']); ?></p>
                                    <p class="text-sm text-gray-600">
                                        <?php echo formatRupiah($item['price']); ?> × <?php echo number_format($item['quantity']); ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium"><?php echo formatRupiah($item['subtotal']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <!-- Order Summary -->
                            <div class="border-t pt-4 space-y-2">
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

                    <!-- Customer & Shipping Info -->
                    <div class="space-y-6">
                        <!-- Customer Info -->
                        <div class="bg-white rounded-xl shadow-md p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pelanggan</h3>
                            <div class="space-y-3">
                                <div>
                                    <span class="text-sm text-gray-500">Nama:</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Telepon:</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($order['customer_phone']); ?></p>
                                </div>
                                <?php if ($order['customer_email']): ?>
                                <div>
                                    <span class="text-sm text-gray-500">Email:</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($order['customer_email']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Shipping Info -->
                        <div class="bg-white rounded-xl shadow-md p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pengiriman</h3>
                            <div class="space-y-3">
                                <div>
                                    <span class="text-sm text-gray-500">Area:</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($order['area_name'] ?? 'Area tidak diketahui'); ?></p>
                                    <?php if ($order['estimated_delivery']): ?>
                                    <p class="text-sm text-gray-600">Estimasi: <?php echo htmlspecialchars($order['estimated_delivery']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Alamat:</span>
                                    <p class="font-medium"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                                </div>
                                <?php if ($order['notes']): ?>
                                <div>
                                    <span class="text-sm text-gray-500">Catatan:</span>
                                    <p class="font-medium"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Payment Info -->
                        <div class="bg-white rounded-xl shadow-md p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pembayaran</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Metode:</span>
                                    <span class="font-medium"><?php echo $order['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Transfer Bank'; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Status:</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                 <?php echo getPaymentStatusColor($order['payment_status']); ?>">
                                        <?php echo getPaymentStatusText($order['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($order['payment_method'] === 'transfer' && $order['payment_status'] === 'pending'): ?>
                            <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <p class="text-sm text-yellow-800 mb-2">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    Menunggu pembayaran
                                </p>
                                <p class="text-xs text-yellow-700">
                                    Silakan lakukan pembayaran dan kirim bukti transfer via WhatsApp
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Butuh Bantuan?</h3>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <?php 
                        $whatsappMessage = "Halo, saya ingin menanyakan tentang pesanan {$order['order_number']}";
                        $whatsappUrl = "https://wa.me/{$whatsapp_number}?text=" . urlencode($whatsappMessage);
                        ?>
                        
                        <a href="<?php echo $whatsappUrl; ?>" target="_blank" 
                           class="bg-green-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-600 transition-colors inline-flex items-center justify-center">
                            <i class="fab fa-whatsapp mr-2"></i>
                            Chat WhatsApp
                        </a>
                        
                        <a href="tel:<?php echo $site_phone; ?>" 
                           class="bg-blue-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-600 transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-phone mr-2"></i>
                            Telepon
                        </a>
                        
                        <button onclick="window.print()" 
                                class="bg-gray-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-600 transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-print mr-2"></i>
                            Cetak
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Help Section -->
            <div class="bg-blue-50 rounded-xl p-6 mt-8">
                <div class="text-center">
                    <h3 class="text-lg font-semibold text-blue-900 mb-2">Tidak Menemukan Pesanan Anda?</h3>
                    <p class="text-blue-700 mb-4">Pastikan nomor pesanan yang Anda masukkan benar, atau hubungi customer service kami</p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="https://wa.me/<?php echo $whatsapp_number; ?>?text=Halo, saya tidak bisa menemukan pesanan saya" 
                           target="_blank" 
                           class="bg-green-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-600 transition-colors inline-flex items-center justify-center">
                            <i class="fab fa-whatsapp mr-2"></i>
                            Bantuan via WhatsApp
                        </a>
                        <a href="tel:<?php echo $site_phone; ?>" 
                           class="bg-blue-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-600 transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-phone mr-2"></i>
                            Hubungi Kami
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div class="bg-white rounded-xl shadow-md p-6 mt-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Tips Melacak Pesanan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                            <div>
                                <h4 class="font-medium">Simpan Nomor Pesanan</h4>
                                <p class="text-sm text-gray-600">Simpan nomor pesanan Anda untuk memudahkan pelacakan</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-bell text-blue-500 mt-1 mr-3"></i>
                            <div>
                                <h4 class="font-medium">Notifikasi WhatsApp</h4>
                                <p class="text-sm text-gray-600">Kami akan mengirim update status via WhatsApp</p>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <i class="fas fa-clock text-orange-500 mt-1 mr-3"></i>
                            <div>
                                <h4 class="font-medium">Waktu Pengiriman</h4>
                                <p class="text-sm text-gray-600">Estimasi pengiriman sesuai area yang dipilih</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-headset text-purple-500 mt-1 mr-3"></i>
                            <div>
                                <h4 class="font-medium">Customer Service</h4>
                                <p class="text-sm text-gray-600">Tim kami siap membantu 24/7</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-12 mt-16">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <div class="flex items-center justify-center space-x-2 mb-4">
                    <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                        <i class="fas fa-utensils text-white"></i>
                    </div>
                    <h4 class="text-xl font-bold"><?php echo htmlspecialchars($site_name); ?></h4>
                </div>
                <p class="text-gray-400 mb-4">Distributor somay dan siomay terpercaya di Tangerang Selatan</p>
                <div class="flex justify-center space-x-6">
                    <a href="../index.php" class="text-gray-400 hover:text-white transition-colors">Beranda</a>
                    <a href="products.php" class="text-gray-400 hover:text-white transition-colors">Produk</a>
                    <a href="../index.php#contact" class="text-gray-400 hover:text-white transition-colors">Kontak</a>
                </div>
                <div class="border-t border-gray-700 mt-8 pt-8">
                    <p class="text-gray-400">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Auto-focus on order number input
        document.addEventListener('DOMContentLoaded', function() {
            const orderInput = document.querySelector('input[name="order_number"]');
            if (orderInput && !orderInput.value) {
                orderInput.focus();
            }
        });

        // Format order number input
        document.querySelector('input[name="order_number"]').addEventListener('input', function(e) {
            let value = e.target.value.toUpperCase();
            e.target.value = value;
        });

        // Print functionality
        function printOrder() {
            window.print();
        }

        // Copy order number to clipboard
        function copyOrderNumber() {
            const orderNumber = '<?php echo $order['order_number'] ?? ''; ?>';
            if (orderNumber) {
                navigator.clipboard.writeText(orderNumber).then(() => {
                    alert('Nomor pesanan berhasil disalin!');
                });
            }
        }
    </script>

    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .print-area, .print-area * {
                visibility: visible;
            }
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
            }
            nav, footer, .no-print {
                display: none !important;
            }
        }
    </style>
</body>
</html>

<?php
// Helper functions
function getPaymentStatusColor($status) {
    $colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'paid' => 'bg-green-100 text-green-800',
        'failed' => 'bg-red-100 text-red-800'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}

function getPaymentStatusText($status) {
    $texts = [
        'pending' => 'Menunggu Pembayaran',
        'paid' => 'Sudah Dibayar',
        'failed' => 'Pembayaran Gagal'
    ];
    return $texts[$status] ?? ucfirst($status);
}
?>