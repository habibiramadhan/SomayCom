<?php
require_once 'config.php';

// Ambil produk featured dari database
try {
    $pdo = getDB();
    
    // Get featured products
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
               CASE 
                   WHEN p.discount_price IS NOT NULL AND p.discount_price > 0 THEN p.discount_price 
                   ELSE p.price 
               END as final_price
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 AND p.is_featured = 1
        ORDER BY p.created_at DESC
        LIMIT 6
    ");
    $stmt->execute();
    $featured_products = $stmt->fetchAll();
    
    // Get app settings
    $site_name = getAppSetting('site_name', 'Somay Ecommerce');
    $site_phone = getAppSetting('site_phone', '081234567890');
    $site_email = getAppSetting('site_email', 'info@somay.com');
    $whatsapp_number = getAppSetting('whatsapp_number', '6281234567890');
    $min_order_amount = getAppSetting('min_order_amount', 25000);
    $free_shipping_min = getAppSetting('free_shipping_min', 100000);
    
    // Get categories for navigation
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    // Get shipping areas count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM shipping_areas WHERE is_active = 1");
    $stmt->execute();
    $shipping_areas_count = $stmt->fetch()['total'];
    
    // Get some statistics for about section
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            COUNT(DISTINCT o.customer_phone) as total_customers,
            COALESCE(SUM(oi.quantity), 0) as total_products_sold
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.order_status NOT IN ('cancelled')
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
} catch (Exception $e) {
    error_log("Homepage database error: " . $e->getMessage());
    $featured_products = [];
    $categories = [];
    $stats = ['total_orders' => 0, 'total_customers' => 0, 'total_products_sold' => 0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_name); ?> - Distributor Somay Terlengkap</title>
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
        .hero-bg {
            background: linear-gradient(135deg, #FF6B35 0%, #F7931E 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .product-image {
            background-color: #f3f4f6;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23e5e7eb' fill-opacity='0.4'%3E%3Cpath d='M30 40c5.5 0 10-4.5 10-10s-4.5-10-10-10-10 4.5-10 10 4.5 10 10 10zm0-16c3.3 0 6 2.7 6 6s-2.7 6-6 6-6-2.7-6-6 2.7-6 6-6z'/%3E%3C/g%3E%3C/svg%3E");
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-2">
                    <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
                        <i class="fas fa-utensils text-white text-lg"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-dark"><?php echo htmlspecialchars($site_name); ?></h1>
                </div>
                
                <div class="hidden md:flex space-x-8">
                    <a href="#" class="text-dark hover:text-primary transition-colors font-medium">Beranda</a>
                    <div class="relative group">
                        <a href="pages/products.php" class="text-dark hover:text-primary transition-colors font-medium">Produk</a>
                        <?php if (!empty($categories)): ?>
                        <div class="absolute left-0 mt-2 w-48 bg-white rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all">
                            <div class="py-1">
                                <?php foreach ($categories as $category): ?>
                                <a href="pages/products.php?category=<?php echo $category['slug']; ?>" 
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <a href="#about" class="text-dark hover:text-primary transition-colors font-medium">Tentang</a>
                    <a href="#contact" class="text-dark hover:text-primary transition-colors font-medium">Kontak</a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <button class="relative p-2 text-dark hover:text-primary transition-colors" onclick="toggleCart()">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <span id="cart-count" class="absolute -top-2 -right-2 bg-primary text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">0</span>
                    </button>
                    <a href="pages/track-order.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors">
                        <i class="fas fa-search mr-2"></i>Lacak Pesanan
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-bg text-white py-20">
        <div class="container mx-auto px-4">
            <div class="flex flex-col lg:flex-row items-center">
                <div class="lg:w-1/2 lg:pr-12 mb-8 lg:mb-0">
                    <h2 class="text-5xl font-bold mb-6 leading-tight">
                        Somay Lezat <br>
                        <span class="text-accent">Langsung dari Distributor</span>
                    </h2>
                    <p class="text-xl mb-8 opacity-90">
                        Nikmati somay dan siomay berkualitas tinggi dengan berbagai varian rasa. 
                        Pengiriman cepat se-Tangerang Selatan!
                    </p>
                    <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                        <a href="pages/products.php" class="bg-white text-primary px-8 py-4 rounded-lg font-semibold hover:bg-gray-100 transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-shopping-bag mr-2"></i>
                            Belanja Sekarang
                        </a>
                        <a href="#about" class="border-2 border-white text-white px-8 py-4 rounded-lg font-semibold hover:bg-white hover:text-primary transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            Pelajari Lebih Lanjut
                        </a>
                    </div>
                    
                    <div class="mt-8 grid grid-cols-2 gap-4 text-sm">
                        <div class="flex items-center">
                            <i class="fas fa-shipping-fast mr-2 text-accent"></i>
                            <span>Pengiriman ke <?php echo $shipping_areas_count; ?> area</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-money-bill-wave mr-2 text-accent"></i>
                            <span>Gratis ongkir min <?php echo formatRupiah($free_shipping_min); ?></span>
                        </div>
                    </div>
                </div>
                <div class="lg:w-1/2">
                    <div class="relative">
                        <div class="floating">
                            <div class="w-80 h-80 mx-auto bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <i class="fas fa-utensils text-white text-8xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h3 class="text-3xl font-bold text-dark mb-4">Mengapa Pilih Kami?</h3>
                <p class="text-gray-600 text-lg">Keunggulan yang membuat kami berbeda</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center card-hover p-6 rounded-xl">
                    <div class="w-16 h-16 bg-primary bg-opacity-10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-truck text-primary text-2xl"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-dark mb-3">Pengiriman Cepat</h4>
                    <p class="text-gray-600">Pengiriman dalam 1-2 jam untuk area Tangerang Selatan</p>
                </div>
                
                <div class="text-center card-hover p-6 rounded-xl">
                    <div class="w-16 h-16 bg-secondary bg-opacity-10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-shield-alt text-secondary text-2xl"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-dark mb-3">Kualitas Terjamin</h4>
                    <p class="text-gray-600">Bahan segar dan proses produksi yang higienis</p>
                </div>
                
                <div class="text-center card-hover p-6 rounded-xl">
                    <div class="w-16 h-16 bg-accent bg-opacity-10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-money-bill-wave text-accent text-2xl"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-dark mb-3">Harga Distributor</h4>
                    <p class="text-gray-600">Harga terbaik karena langsung dari distributor</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="py-16 bg-gray-50">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h3 class="text-3xl font-bold text-dark mb-4">Produk Unggulan</h3>
                <p class="text-gray-600 text-lg">Somay dan siomay terlaris pilihan pelanggan</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if (!empty($featured_products)): ?>
                    <?php foreach ($featured_products as $product): ?>
                    <div class="bg-white rounded-xl shadow-md overflow-hidden card-hover">
                        <div class="h-48 product-image bg-center bg-cover" 
                             style="<?php if ($product['primary_image']): ?>background-image: url('<?php echo htmlspecialchars($product['primary_image']); ?>');<?php endif; ?>">
                            <?php if (!$product['primary_image']): ?>
                            <div class="h-full flex items-center justify-center">
                                <i class="fas fa-utensils text-gray-400 text-4xl"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                    <?php echo htmlspecialchars($product['category_name'] ?? 'Produk'); ?>
                                </span>
                                <?php if ($product['stock_quantity'] <= $product['min_stock']): ?>
                                <span class="text-xs text-red-500 bg-red-100 px-2 py-1 rounded">
                                    Stok Terbatas
                                </span>
                                <?php endif; ?>
                            </div>
                            <h4 class="text-lg font-semibold text-dark mb-2"><?php echo htmlspecialchars($product['name']); ?></h4>
                            <p class="text-gray-600 mb-4 text-sm">
                                <?php echo htmlspecialchars(substr($product['short_description'] ?? $product['description'] ?? '', 0, 100)); ?>
                                <?php if (strlen($product['short_description'] ?? $product['description'] ?? '') > 100): ?>...<?php endif; ?>
                            </p>
                            <div class="flex justify-between items-center">
                                <div class="flex flex-col">
                                    <?php if ($product['discount_price'] && $product['discount_price'] != $product['price']): ?>
                                    <span class="text-sm text-gray-500 line-through"><?php echo formatRupiah($product['price']); ?></span>
                                    <span class="text-xl font-bold text-primary"><?php echo formatRupiah($product['final_price']); ?></span>
                                    <?php else: ?>
                                    <span class="text-xl font-bold text-primary"><?php echo formatRupiah($product['final_price']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <button onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['final_price']; ?>)" 
                                        class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors"
                                        <?php if ($product['stock_quantity'] == 0): ?>disabled<?php endif; ?>>
                                    <i class="fas fa-cart-plus mr-1"></i>
                                    <?php echo $product['stock_quantity'] == 0 ? 'Habis' : 'Tambah'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-12">
                        <i class="fas fa-box-open text-gray-400 text-6xl mb-4"></i>
                        <p class="text-gray-500 text-lg">Belum ada produk unggulan tersedia</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-12">
                <a href="pages/products.php" class="bg-primary text-white px-8 py-4 rounded-lg font-semibold hover:bg-orange-600 transition-colors inline-flex items-center">
                    <i class="fas fa-eye mr-2"></i>
                    Lihat Semua Produk
                </a>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="flex flex-col lg:flex-row items-center">
                <div class="lg:w-1/2 lg:pr-12 mb-8 lg:mb-0">
                    <h3 class="text-3xl font-bold text-dark mb-6">Tentang <?php echo htmlspecialchars($site_name); ?></h3>
                    <p class="text-gray-600 text-lg mb-6">
                        Kami adalah distributor somay dan siomay terpercaya yang telah melayani masyarakat 
                        Tangerang Selatan selama bertahun-tahun. Dengan komitmen pada kualitas dan kepuasan pelanggan, 
                        kami menyediakan berbagai varian somay dengan cita rasa autentik.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-primary mr-3"></i>
                            <span class="text-gray-700">Bahan segar dan berkualitas tinggi</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-primary mr-3"></i>
                            <span class="text-gray-700">Proses produksi yang higienis</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-primary mr-3"></i>
                            <span class="text-gray-700">Pengiriman tepat waktu</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-primary mr-3"></i>
                            <span class="text-gray-700">Harga kompetitif</span>
                        </div>
                    </div>
                </div>
                <div class="lg:w-1/2">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="card-hover">
                            <div class="bg-primary bg-opacity-10 p-6 rounded-xl text-center">
                                <h4 class="text-2xl font-bold text-primary mb-2"><?php echo number_format($stats['total_customers']); ?>+</h4>
                                <p class="text-gray-600">Pelanggan Puas</p>
                            </div>
                        </div>
                        <div class="card-hover">
                            <div class="bg-secondary bg-opacity-10 p-6 rounded-xl text-center">
                                <h4 class="text-2xl font-bold text-secondary mb-2"><?php echo number_format($stats['total_orders']); ?>+</h4>
                                <p class="text-gray-600">Pesanan Selesai</p>
                            </div>
                        </div>
                        <div class="card-hover">
                            <div class="bg-accent bg-opacity-10 p-6 rounded-xl text-center">
                                <h4 class="text-2xl font-bold text-accent mb-2">24/7</h4>
                                <p class="text-gray-600">Layanan Pelanggan</p>
                            </div>
                        </div>
                        <div class="card-hover">
                            <div class="bg-green-500 bg-opacity-10 p-6 rounded-xl text-center">
                                <h4 class="text-2xl font-bold text-green-500 mb-2"><?php echo number_format($stats['total_products_sold']); ?>+</h4>
                                <p class="text-gray-600">Produk Terjual</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-16 bg-gray-50">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h3 class="text-3xl font-bold text-dark mb-4">Hubungi Kami</h3>
                <p class="text-gray-600 text-lg">Siap melayani kebutuhan somay Anda</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary bg-opacity-10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-phone text-primary text-2xl"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-dark mb-2">Telepon</h4>
                    <p class="text-gray-600"><?php echo htmlspecialchars($site_phone); ?></p>
                    <a href="tel:<?php echo htmlspecialchars($site_phone); ?>" class="text-primary hover:underline">Hubungi Sekarang</a>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-500 bg-opacity-10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fab fa-whatsapp text-green-500 text-2xl"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-dark mb-2">WhatsApp</h4>
                    <p class="text-gray-600"><?php echo htmlspecialchars($site_phone); ?></p>
                    <a href="https://wa.me/<?php echo htmlspecialchars($whatsapp_number); ?>" target="_blank" class="text-green-500 hover:underline">Chat WhatsApp</a>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-500 bg-opacity-10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-envelope text-blue-500 text-2xl"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-dark mb-2">Email</h4>
                    <p class="text-gray-600"><?php echo htmlspecialchars($site_email); ?></p>
                    <a href="mailto:<?php echo htmlspecialchars($site_email); ?>" class="text-blue-500 hover:underline">Kirim Email</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                            <i class="fas fa-utensils text-white"></i>
                        </div>
                        <h4 class="text-xl font-bold"><?php echo htmlspecialchars($site_name); ?></h4>
                    </div>
                    <p class="text-gray-400 mb-4">Distributor somay dan siomay terpercaya di Tangerang Selatan.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-facebook text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-instagram text-xl"></i>
                        </a>
                        <a href="https://wa.me/<?php echo htmlspecialchars($whatsapp_number); ?>" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-whatsapp text-xl"></i>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h5 class="text-lg font-semibold mb-4">Kategori</h5>
                    <ul class="space-y-2">
                        <?php foreach (array_slice($categories, 0, 4) as $category): ?>
                        <li><a href="pages/products.php?category=<?php echo $category['slug']; ?>" class="text-gray-400 hover:text-white transition-colors"><?php echo htmlspecialchars($category['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div>
                    <h5 class="text-lg font-semibold mb-4">Layanan</h5>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Pengiriman Cepat</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">COD</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Transfer Bank</a></li>
                        <li><a href="pages/track-order.php" class="text-gray-400 hover:text-white transition-colors">Lacak Pesanan</a></li>
                    </ul>
                </div>
                
                <div>
                    <h5 class="text-lg font-semibold mb-4">Kontak</h5>
                    <ul class="space-y-2 text-gray-400">
                        <li class="flex items-center">
                            <i class="fas fa-map-marker-alt mr-2"></i>
                            Tangerang Selatan, Banten
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone mr-2"></i>
                            <?php echo htmlspecialchars($site_phone); ?>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope mr-2"></i>
                            <?php echo htmlspecialchars($site_email); ?>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-8 text-center">
                <p class="text-gray-400">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Cart Sidebar -->
    <div id="cart-sidebar" class="fixed right-0 top-0 h-full w-80 bg-white shadow-xl transform translate-x-full transition-transform z-50">
            <div class="p-6 border-b">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Keranjang Belanja</h3>
                    <button onclick="toggleCart()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div id="cart-items" class="p-6 flex-1 overflow-y-auto">
                <p class="text-gray-500 text-center">Keranjang kosong</p>
            </div>
            <div class="p-6 border-t">
                <div class="flex justify-between items-center mb-4">
                    <span class="font-semibold">Total:</span>
                    <span id="cart-total" class="font-bold text-lg text-primary">Rp 0</span>
                </div>
                <a href="pages/checkout.php" class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-orange-600 transition-colors inline-flex items-center justify-center">
                    <i class="fas fa-shopping-cart mr-2"></i>
                    Checkout
                </a>
            </div>
        </div>
    
    <!-- Cart Overlay -->
    <div id="cart-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden" onclick="toggleCart()"></div>

    <script>
        // Data produk dari PHP
        const featuredProducts = <?php echo json_encode($featured_products); ?>;

        // Load featured products
        function loadFeaturedProducts() {
            const container = document.getElementById('featured-products');
            container.innerHTML = '';
            
            featuredProducts.forEach(product => {
                const productCard = `
                    <div class="bg-white rounded-xl shadow-md overflow-hidden card-hover">
                        <div class="h-48 product-image bg-center bg-cover" 
                             style="${product.primary_image ? `background-image: url('${product.primary_image}');` : ''}">
                            ${!product.primary_image ? `
                            <div class="h-full flex items-center justify-center">
                                <i class="fas fa-utensils text-gray-400 text-4xl"></i>
                            </div>
                            ` : ''}
                        </div>
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                    ${product.category_name || 'Produk'}
                                </span>
                                ${product.stock_quantity <= product.min_stock ? `
                                <span class="text-xs text-red-500 bg-red-100 px-2 py-1 rounded">
                                    Stok Terbatas
                                </span>
                                ` : ''}
                            </div>
                            <h4 class="text-lg font-semibold text-dark mb-2">${product.name}</h4>
                            <p class="text-gray-600 mb-4 text-sm">
                                ${(product.short_description || product.description || '').substring(0, 100)}
                                ${(product.short_description || product.description || '').length > 100 ? '...' : ''}
                            </p>
                            <div class="flex justify-between items-center">
                                <div class="flex flex-col">
                                    ${product.discount_price && product.discount_price != product.price ? `
                                    <span class="text-sm text-gray-500 line-through">${formatRupiah(product.price)}</span>
                                    <span class="text-xl font-bold text-primary">${formatRupiah(product.final_price)}</span>
                                    ` : `
                                    <span class="text-xl font-bold text-primary">${formatRupiah(product.final_price)}</span>
                                    `}
                                </div>
                                <button onclick="addToCart(${product.id}, '${product.name.replace(/'/g, "\\'")}', ${product.final_price})" 
                                        class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors"
                                        ${product.stock_quantity == 0 ? 'disabled' : ''}>
                                    <i class="fas fa-cart-plus mr-1"></i>
                                    ${product.stock_quantity == 0 ? 'Habis' : 'Tambah'}
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                container.innerHTML += productCard;
            });
        }

        // Format rupiah
        function formatRupiah(amount) {
            return 'Rp ' + amount.toLocaleString('id-ID');
        }

        // Cart functionality
        let cart = [];

        function addToCart(productId, productName, productPrice) {
            const existingItem = cart.find(item => item.id === productId);
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({id: productId, name: productName, price: productPrice, quantity: 1});
            }
            updateCartUI();
            showNotification('Produk ditambahkan ke keranjang!');
        }

        function updateCartUI() {
            const cartCount = cart.reduce((total, item) => total + item.quantity, 0);
            document.getElementById('cart-count').textContent = cartCount;
            
            const cartTotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
            document.getElementById('cart-total').textContent = formatRupiah(cartTotal);
            
            const cartItems = document.getElementById('cart-items');
            if (cart.length === 0) {
                cartItems.innerHTML = '<p class="text-gray-500 text-center">Keranjang kosong</p>';
            } else {
                cartItems.innerHTML = cart.map(item => `
                    <div class="flex items-center justify-between py-3 border-b">
                        <div class="flex-1">
                            <h5 class="font-medium">${item.name}</h5>
                            <p class="text-sm text-gray-500">${formatRupiah(item.price)} x ${item.quantity}</p>
                        </div>
                        <button onclick="removeFromCart(${item.id})" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `).join('');
            }
        }

        function removeFromCart(productId) {
            cart = cart.filter(item => item.id !== productId);
            updateCartUI();
        }

        function toggleCart() {
            const sidebar = document.getElementById('cart-sidebar');
            const overlay = document.getElementById('cart-overlay');
            
            if (sidebar.classList.contains('translate-x-full')) {
                sidebar.classList.remove('translate-x-full');
                overlay.classList.remove('hidden');
            } else {
                sidebar.classList.add('translate-x-full');
                overlay.classList.add('hidden');
            }
        }

        function showNotification(message) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform';
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);
            
            // Hide notification after 3 seconds
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadFeaturedProducts();
            updateCartUI();
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>