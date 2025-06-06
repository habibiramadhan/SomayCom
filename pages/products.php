<?php
// pages/products.php
require_once '../config.php';

// Get filters from GET parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'category' => $_GET['category'] ?? '',
    'sort' => $_GET['sort'] ?? 'newest'
];

$page = (int)($_GET['page'] ?? 1);
$limit = 12;

try {
    $pdo = getDB();
    
    // Build WHERE clause
    $where = "WHERE p.is_active = 1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $where .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.short_description LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($filters['category'])) {
        $where .= " AND c.slug = ?";
        $params[] = $filters['category'];
    }
    
    // Sorting
    $orderBy = "ORDER BY p.created_at DESC";
    switch ($filters['sort']) {
        case 'price_low':
            $orderBy = "ORDER BY final_price ASC";
            break;
        case 'price_high':
            $orderBy = "ORDER BY final_price DESC";
            break;
        case 'name':
            $orderBy = "ORDER BY p.name ASC";
            break;
        case 'featured':
            $orderBy = "ORDER BY p.is_featured DESC, p.created_at DESC";
            break;
        default:
            $orderBy = "ORDER BY p.created_at DESC";
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id $where";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalProducts = $stmt->fetchColumn();
    $totalPages = ceil($totalProducts / $limit);
    
    // Get products
    $offset = ($page - 1) * $limit;
    $sql = "
        SELECT p.*, c.name as category_name, c.slug as category_slug,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
               CASE 
                   WHEN p.discount_price IS NOT NULL AND p.discount_price > 0 THEN p.discount_price 
                   ELSE p.price 
               END as final_price
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        $where
        $orderBy
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Get categories for filter
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    // Get app settings
    $site_name = getAppSetting('site_name', 'Somay Ecommerce');
    $site_phone = getAppSetting('site_phone', '081234567890');
    $whatsapp_number = getAppSetting('whatsapp_number', '6281234567890');
    
} catch (Exception $e) {
    error_log("Products page error: " . $e->getMessage());
    $products = [];
    $categories = [];
    $totalProducts = 0;
    $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produk - <?php echo htmlspecialchars($site_name); ?></title>
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
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
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
                    <a href="../index.php" class="text-2xl font-bold text-dark"><?php echo htmlspecialchars($site_name); ?></a>
                </div>
                
                <div class="hidden md:flex space-x-8">
                    <a href="../index.php" class="text-dark hover:text-primary transition-colors font-medium">Beranda</a>
                    <a href="#" class="text-primary font-medium">Produk</a>
                    <a href="../index.php#about" class="text-dark hover:text-primary transition-colors font-medium">Tentang</a>
                    <a href="../index.php#contact" class="text-dark hover:text-primary transition-colors font-medium">Kontak</a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <button class="relative p-2 text-dark hover:text-primary transition-colors" onclick="toggleCart()">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <span id="cart-count" class="absolute -top-2 -right-2 bg-primary text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">0</span>
                    </button>
                    <a href="track-order.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors">
                        <i class="fas fa-search mr-2"></i>Lacak Pesanan
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="bg-gradient-to-r from-primary to-secondary text-white py-16">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <h1 class="text-4xl font-bold mb-4">Produk Kami</h1>
                <p class="text-xl opacity-90">Somay dan siomay berkualitas tinggi dengan berbagai varian rasa</p>
            </div>
        </div>
    </section>

    <!-- Filters & Search -->
    <section class="py-8 bg-white border-b">
        <div class="container mx-auto px-4">
            <form method="GET" class="flex flex-col md:flex-row gap-4 items-center">
                <!-- Search -->
                <div class="flex-1">
                    <div class="relative">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                               placeholder="Cari produk..." 
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>
                
                <!-- Category Filter -->
                <div class="w-full md:w-auto">
                    <select name="category" class="w-full md:w-48 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['slug']; ?>" <?php echo $filters['category'] === $category['slug'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Sort -->
                <div class="w-full md:w-auto">
                    <select name="sort" class="w-full md:w-48 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="newest" <?php echo $filters['sort'] === 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                        <option value="featured" <?php echo $filters['sort'] === 'featured' ? 'selected' : ''; ?>>Unggulan</option>
                        <option value="name" <?php echo $filters['sort'] === 'name' ? 'selected' : ''; ?>>Nama A-Z</option>
                        <option value="price_low" <?php echo $filters['sort'] === 'price_low' ? 'selected' : ''; ?>>Harga Terendah</option>
                        <option value="price_high" <?php echo $filters['sort'] === 'price_high' ? 'selected' : ''; ?>>Harga Tertinggi</option>
                    </select>
                </div>
                
                <!-- Search Button -->
                <button type="submit" class="w-full md:w-auto bg-primary text-white px-6 py-3 rounded-lg hover:bg-secondary transition-colors">
                    <i class="fas fa-search mr-2"></i>Filter
                </button>
                
                <!-- Reset Button -->
                <a href="products.php" class="w-full md:w-auto bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition-colors text-center">
                    <i class="fas fa-times mr-2"></i>Reset
                </a>
            </form>
            
            <!-- Results Info -->
            <div class="mt-4 flex justify-between items-center text-sm text-gray-600">
                <p>Menampilkan <?php echo count($products); ?> dari <?php echo $totalProducts; ?> produk</p>
                <?php if (!empty($filters['search'])): ?>
                <p>Hasil pencarian untuk: "<strong><?php echo htmlspecialchars($filters['search']); ?></strong>"</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Products Grid -->
    <section class="py-16">
        <div class="container mx-auto px-4">
            <?php if (!empty($products)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                <?php foreach ($products as $product): ?>
                <div class="bg-white rounded-xl shadow-md overflow-hidden card-hover">
                    <div class="h-48 product-image bg-center bg-cover" 
                         style="<?php if ($product['primary_image']): ?>background-image: url('../uploads/products/<?php echo htmlspecialchars($product['primary_image']); ?>');<?php endif; ?>">
                        <?php if (!$product['primary_image']): ?>
                        <div class="h-full flex items-center justify-center">
                            <i class="fas fa-utensils text-gray-400 text-4xl"></i>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Product badges -->
                        <div class="absolute top-2 left-2">
                            <?php if ($product['is_featured']): ?>
                            <span class="bg-accent text-dark text-xs px-2 py-1 rounded mb-1 block">
                                <i class="fas fa-star mr-1"></i>Unggulan
                            </span>
                            <?php endif; ?>
                            <?php if ($product['discount_price'] && $product['discount_price'] != $product['price']): ?>
                            <span class="bg-red-500 text-white text-xs px-2 py-1 rounded">
                                Diskon
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                <?php echo htmlspecialchars($product['category_name'] ?? 'Produk'); ?>
                            </span>
                            <?php if ($product['stock_quantity'] == 0): ?>
                            <span class="text-xs text-red-500 bg-red-100 px-2 py-1 rounded">
                                Habis
                            </span>
                            <?php elseif ($product['stock_quantity'] <= 5): ?>
                            <span class="text-xs text-orange-500 bg-orange-100 px-2 py-1 rounded">
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
                            
                            <div class="flex items-center space-x-2">
                                <a href="product-detail.php?slug=<?php echo $product['slug']; ?>" 
                                   class="bg-gray-200 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-300 transition-colors" 
                                   title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['final_price']; ?>)" 
                                        class="bg-primary text-white px-3 py-2 rounded-lg hover:bg-orange-600 transition-colors"
                                        <?php if ($product['stock_quantity'] == 0): ?>disabled<?php endif; ?>>
                                    <i class="fas fa-cart-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="mt-12 flex justify-center">
                <div class="flex items-center space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                       class="px-4 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        <i class="fas fa-chevron-left mr-1"></i>Sebelumnya
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="px-4 py-2 text-sm <?php echo $i == $page ? 'bg-primary text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-md">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                       class="px-4 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Selanjutnya<i class="fas fa-chevron-right ml-1"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <!-- Empty State -->
            <div class="text-center py-16">
                <i class="fas fa-box-open text-gray-400 text-6xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">Produk Tidak Ditemukan</h3>
                <p class="text-gray-500 mb-6">Maaf, tidak ada produk yang sesuai dengan kriteria pencarian Anda.</p>
                <a href="products.php" class="bg-primary text-white px-6 py-3 rounded-lg hover:bg-secondary transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Lihat Semua Produk
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-primary text-white py-16">
        <div class="container mx-auto px-4 text-center">
            <h3 class="text-3xl font-bold mb-4">Butuh Bantuan Memilih Produk?</h3>
            <p class="text-xl mb-8 opacity-90">Tim kami siap membantu Anda menemukan produk yang tepat</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="https://wa.me/<?php echo htmlspecialchars($whatsapp_number); ?>" target="_blank" 
                   class="bg-green-500 text-white px-8 py-4 rounded-lg font-semibold hover:bg-green-600 transition-colors inline-flex items-center justify-center">
                    <i class="fab fa-whatsapp mr-2 text-xl"></i>
                    Chat WhatsApp
                </a>
                <a href="tel:<?php echo htmlspecialchars($site_phone); ?>" 
                   class="bg-white text-primary px-8 py-4 rounded-lg font-semibold hover:bg-gray-100 transition-colors inline-flex items-center justify-center">
                    <i class="fas fa-phone mr-2"></i>
                    Telepon Sekarang
                </a>
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
                </div>
                
                <div>
                    <h5 class="text-lg font-semibold mb-4">Kategori</h5>
                    <ul class="space-y-2">
                        <?php foreach (array_slice($categories, 0, 4) as $category): ?>
                        <li><a href="products.php?category=<?php echo $category['slug']; ?>" class="text-gray-400 hover:text-white transition-colors"><?php echo htmlspecialchars($category['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div>
                    <h5 class="text-lg font-semibold mb-4">Menu</h5>
                    <ul class="space-y-2">
                        <li><a href="../index.php" class="text-gray-400 hover:text-white transition-colors">Beranda</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Produk</a></li>
                        <li><a href="../index.php#about" class="text-gray-400 hover:text-white transition-colors">Tentang</a></li>
                        <li><a href="../index.php#contact" class="text-gray-400 hover:text-white transition-colors">Kontak</a></li>
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
            <button id="checkout-btn" class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-orange-600 transition-colors inline-flex items-center justify-center">
                <i class="fas fa-shopping-cart mr-2"></i>
                Checkout
            </button>
        </div>
    </div>
    
    <!-- Cart Overlay -->
    <div id="cart-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden" onclick="toggleCart()"></div>

    <script>
        // Cart functionality
        let cart = [];

        // Load cart from localStorage when page loads
        function loadCart() {
            const savedCart = localStorage.getItem('cart');
            if (savedCart) {
                cart = JSON.parse(savedCart);
                updateCartUI();
            }
        }

        // Save cart to localStorage whenever it changes
        function saveCart() {
            localStorage.setItem('cart', JSON.stringify(cart));
        }

        function addToCart(productId, productName, productPrice) {
            const existingItem = cart.find(item => item.id === productId);
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({id: productId, name: productName, price: productPrice, quantity: 1});
            }
            updateCartUI();
            saveCart(); // Save cart after adding item
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
            saveCart(); // Save cart after removing item
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

        function formatRupiah(amount) {
            return 'Rp ' + amount.toLocaleString('id-ID');
        }

        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform';
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);
            
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Initialize
        loadCart(); // Load cart when page loads

        // Tambahkan event listener untuk tombol checkout
        if (document.getElementById('checkout-btn')) {
            document.getElementById('checkout-btn').addEventListener('click', function(e) {
                e.preventDefault();
                const cart = localStorage.getItem('cart');
                if (!cart || JSON.parse(cart).length === 0) {
                    alert('Keranjang kosong!');
                    return;
                }
                // Kirim cart ke server via form POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'cart-to-session.php';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'cart';
                input.value = cart;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            });
        }
    </script>
</body>
</html>