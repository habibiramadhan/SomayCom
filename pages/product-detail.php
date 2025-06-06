<!-- pages/product-detail.php -->
<?php
require_once '../config.php';

// Get product slug from URL
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: products.php');
    exit;
}

try {
    $pdo = getDB();
    
    // Get product data
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug,
               CASE 
                   WHEN p.discount_price IS NOT NULL AND p.discount_price > 0 THEN p.discount_price 
                   ELSE p.price 
               END as final_price
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.slug = ? AND p.is_active = 1
    ");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: products.php');
        exit;
    }
    
    // Get product images
    $stmt = $pdo->prepare("
        SELECT * FROM product_images 
        WHERE product_id = ? 
        ORDER BY is_primary DESC, sort_order ASC
    ");
    $stmt->execute([$product['id']]);
    $images = $stmt->fetchAll();
    
    // Get related products (same category)
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
               CASE 
                   WHEN p.discount_price IS NOT NULL AND p.discount_price > 0 THEN p.discount_price 
                   ELSE p.price 
               END as final_price
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
        ORDER BY RAND()
        LIMIT 4
    ");
    $stmt->execute([$product['category_id'], $product['id']]);
    $related_products = $stmt->fetchAll();
    
    // Get app settings
    $site_name = getAppSetting('site_name', 'Somay Ecommerce');
    $site_phone = getAppSetting('site_phone', '081234567890');
    $whatsapp_number = getAppSetting('whatsapp_number', '6281234567890');
    $min_order_amount = getAppSetting('min_order_amount', 25000);
    
} catch (Exception $e) {
    error_log("Product detail error: " . $e->getMessage());
    header('Location: products.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['meta_title'] ?: $product['name']); ?> - <?php echo htmlspecialchars($site_name); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($product['meta_description'] ?: substr($product['description'] ?: '', 0, 160)); ?>">
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
        .image-gallery img {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .image-gallery img:hover {
            opacity: 0.8;
        }
        .image-gallery img.active {
            border: 3px solid #FF6B35;
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
                    <a href="products.php" class="text-dark hover:text-primary transition-colors font-medium">Produk</a>
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

    <!-- Breadcrumb -->
    <div class="bg-white border-b">
        <div class="container mx-auto px-4 py-4">
            <nav class="text-sm">
                <ol class="flex items-center space-x-2">
                    <li><a href="../index.php" class="text-gray-500 hover:text-primary">Beranda</a></li>
                    <li class="text-gray-400"><i class="fas fa-chevron-right"></i></li>
                    <li><a href="products.php" class="text-gray-500 hover:text-primary">Produk</a></li>
                    <?php if ($product['category_name']): ?>
                    <li class="text-gray-400"><i class="fas fa-chevron-right"></i></li>
                    <li><a href="products.php?category=<?php echo $product['category_slug']; ?>" class="text-gray-500 hover:text-primary"><?php echo htmlspecialchars($product['category_name']); ?></a></li>
                    <?php endif; ?>
                    <li class="text-gray-400"><i class="fas fa-chevron-right"></i></li>
                    <li class="text-gray-900 font-medium"><?php echo htmlspecialchars($product['name']); ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Product Detail -->
    <section class="py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- Product Images -->
                <div class="space-y-4">
                    <!-- Main Image -->
                    <div class="aspect-square bg-white rounded-xl shadow-md overflow-hidden">
                        <?php if (!empty($images)): ?>
                        <img id="main-image" src="../uploads/products/<?php echo htmlspecialchars($images[0]['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="w-full h-full object-cover cursor-pointer"
                             onclick="openImageModal(this.src)">
                        <?php else: ?>
                        <div class="w-full h-full product-image flex items-center justify-center">
                            <i class="fas fa-utensils text-gray-400 text-6xl"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Image Thumbnails -->
                    <?php if (count($images) > 1): ?>
                    <div class="grid grid-cols-4 gap-4 image-gallery">
                        <?php foreach ($images as $index => $image): ?>
                        <img src="../uploads/products/<?php echo htmlspecialchars($image['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="w-full h-20 object-cover rounded-lg border-2 border-gray-200 <?php echo $index === 0 ? 'active' : ''; ?>"
                             onclick="changeMainImage(this)">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Product Info -->
                <div class="space-y-6">
                    <!-- Product Title & Category -->
                    <div>
                        <?php if ($product['category_name']): ?>
                        <span class="inline-block bg-gray-100 text-gray-600 text-sm px-3 py-1 rounded-full mb-3">
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </span>
                        <?php endif; ?>
                        
                        <h1 class="text-3xl font-bold text-dark mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>
                        <p class="text-gray-600">SKU: <?php echo htmlspecialchars($product['sku']); ?></p>
                        
                        <!-- Product Badges -->
                        <div class="flex items-center space-x-2 mt-3">
                            <?php if ($product['is_featured']): ?>
                            <span class="bg-accent text-dark text-xs px-2 py-1 rounded-full">
                                <i class="fas fa-star mr-1"></i>Produk Unggulan
                            </span>
                            <?php endif; ?>
                            <?php if ($product['stock_quantity'] == 0): ?>
                            <span class="bg-red-100 text-red-600 text-xs px-2 py-1 rounded-full">
                                Stok Habis
                            </span>
                            <?php elseif ($product['stock_quantity'] <= 5): ?>
                            <span class="bg-orange-100 text-orange-600 text-xs px-2 py-1 rounded-full">
                                Stok Terbatas
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Price -->
                    <div class="border-b pb-6">
                        <div class="flex items-center space-x-4">
                            <?php if ($product['discount_price'] && $product['discount_price'] != $product['price']): ?>
                            <span class="text-2xl text-gray-500 line-through"><?php echo formatRupiah($product['price']); ?></span>
                            <span class="text-4xl font-bold text-primary"><?php echo formatRupiah($product['final_price']); ?></span>
                            <span class="bg-red-500 text-white text-sm px-2 py-1 rounded">
                                Hemat <?php echo formatRupiah($product['price'] - $product['final_price']); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-4xl font-bold text-primary"><?php echo formatRupiah($product['final_price']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($product['weight'] > 0): ?>
                        <p class="text-gray-600 mt-2">Berat: <?php echo number_format($product['weight']); ?> gram</p>
                        <?php endif; ?>
                    </div>

                    <!-- Short Description -->
                    <?php if ($product['short_description']): ?>
                    <div>
                        <h3 class="text-lg font-semibold text-dark mb-3">Deskripsi Singkat</h3>
                        <p class="text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($product['short_description'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Add to Cart Section -->
                    <div class="bg-gray-50 rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-lg font-semibold">Jumlah:</span>
                            <div class="flex items-center space-x-3">
                                <button onclick="decreaseQuantity()" class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center hover:bg-gray-300 transition-colors">
                                    <i class="fas fa-minus text-sm"></i>
                                </button>
                                <input type="number" id="quantity" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>" 
                                       class="w-16 text-center border border-gray-300 rounded py-2">
                                <button onclick="increaseQuantity()" class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center hover:bg-gray-300 transition-colors">
                                    <i class="fas fa-plus text-sm"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="space-y-3">
                            <?php if ($product['stock_quantity'] > 0): ?>
                            <button onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['final_price']; ?>)" 
                                    class="w-full bg-primary text-white py-4 rounded-lg font-semibold hover:bg-secondary transition-colors text-lg">
                                <i class="fas fa-cart-plus mr-2"></i>
                                Tambah ke Keranjang
                            </button>
                            <a href="https://wa.me/<?php echo htmlspecialchars($whatsapp_number); ?>?text=Halo, saya tertarik dengan produk <?php echo urlencode($product['name']); ?>" 
                               target="_blank"
                               class="w-full bg-green-500 text-white py-4 rounded-lg font-semibold hover:bg-green-600 transition-colors text-lg inline-flex items-center justify-center">
                                <i class="fab fa-whatsapp mr-2"></i>
                                Pesan via WhatsApp
                            </a>
                            <?php else: ?>
                            <button disabled class="w-full bg-gray-400 text-white py-4 rounded-lg font-semibold cursor-not-allowed text-lg">
                                <i class="fas fa-times mr-2"></i>
                                Stok Habis
                            </button>
                            <a href="https://wa.me/<?php echo htmlspecialchars($whatsapp_number); ?>?text=Halo, kapan produk <?php echo urlencode($product['name']); ?> tersedia kembali?" 
                               target="_blank"
                               class="w-full bg-blue-500 text-white py-4 rounded-lg font-semibold hover:bg-blue-600 transition-colors text-lg inline-flex items-center justify-center">
                                <i class="fab fa-whatsapp mr-2"></i>
                                Tanya Ketersediaan
                            </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-4 text-sm text-gray-600">
                            <p><i class="fas fa-truck mr-2"></i>Pengiriman ke Tangerang Selatan</p>
                            <p><i class="fas fa-shield-alt mr-2"></i>Garansi kualitas produk</p>
                            <p><i class="fas fa-money-bill-wave mr-2"></i>Minimal pembelian <?php echo formatRupiah($min_order_amount); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Product Description -->
    <?php if ($product['description']): ?>
    <section class="py-12 bg-white">
        <div class="container mx-auto px-4">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-2xl font-bold text-dark mb-6">Deskripsi Produk</h2>
                <div class="prose max-w-none text-gray-700 leading-relaxed">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Related Products -->
    <?php if (!empty($related_products)): ?>
    <section class="py-16 bg-gray-50">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-dark mb-8 text-center">Produk Terkait</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <?php foreach ($related_products as $related): ?>
                <div class="bg-white rounded-xl shadow-md overflow-hidden card-hover">
                    <div class="h-48 product-image bg-center bg-cover" 
                         style="<?php if ($related['primary_image']): ?>background-image: url('../uploads/products/<?php echo htmlspecialchars($related['primary_image']); ?>');<?php endif; ?>">
                        <?php if (!$related['primary_image']): ?>
                        <div class="h-full flex items-center justify-center">
                            <i class="fas fa-utensils text-gray-400 text-4xl"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-6">
                        <h4 class="text-lg font-semibold text-dark mb-2"><?php echo htmlspecialchars($related['name']); ?></h4>
                        <div class="flex justify-between items-center">
                            <span class="text-xl font-bold text-primary"><?php echo formatRupiah($related['final_price']); ?></span>
                            <a href="product-detail.php?slug=<?php echo $related['slug']; ?>" 
                               class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition-colors">
                                Lihat
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

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
                    <h5 class="text-lg font-semibold mb-4">Menu</h5>
                    <ul class="space-y-2">
                        <li><a href="../index.php" class="text-gray-400 hover:text-white transition-colors">Beranda</a></li>
                        <li><a href="products.php" class="text-gray-400 hover:text-white transition-colors">Produk</a></li>
                        <li><a href="../index.php#about" class="text-gray-400 hover:text-white transition-colors">Tentang</a></li>
                        <li><a href="../index.php#contact" class="text-gray-400 hover:text-white transition-colors">Kontak</a></li>
                    </ul>
                </div>
                
                <div>
                    <h5 class="text-lg font-semibold mb-4">Layanan</h5>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Pengiriman Cepat</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">COD</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Transfer Bank</a></li>
                        <li><a href="track-order.php" class="text-gray-400 hover:text-white transition-colors">Lacak Pesanan</a></li>
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

    <!-- Image Modal -->
    <div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 hidden z-50 flex items-center justify-center">
        <div class="max-w-4xl max-h-full p-4">
            <img id="modalImage" src="" class="max-w-full max-h-full object-contain">
        </div>
        <button onclick="closeImageModal()" class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300">
            <i class="fas fa-times"></i>
        </button>
    </div>

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
        // Product data
        const product = {
            id: <?php echo $product['id']; ?>,
            name: '<?php echo addslashes($product['name']); ?>',
            price: <?php echo $product['final_price']; ?>,
            stock: <?php echo $product['stock_quantity']; ?>
        };

        // Image gallery functionality
        function changeMainImage(thumbnail) {
            const mainImage = document.getElementById('main-image');
            mainImage.src = thumbnail.src;
            
            // Update active thumbnail
            document.querySelectorAll('.image-gallery img').forEach(img => {
                img.classList.remove('active');
            });
            thumbnail.classList.add('active');
        }

        function openImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').classList.remove('hidden');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.add('hidden');
        }

        // Quantity controls
        function increaseQuantity() {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value);
            const maxValue = parseInt(quantityInput.max);
            
            if (currentValue < maxValue) {
                quantityInput.value = currentValue + 1;
            }
        }

        function decreaseQuantity() {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value);
            
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        }

        // Cart functionality
        let cart = [];

        // Load cart dari localStorage saat halaman dimuat
        function loadCartFromLocalStorage() {
            const storedCart = localStorage.getItem('cart');
            if (storedCart) {
                cart = JSON.parse(storedCart);
            }
        }

        // Simpan cart ke localStorage setiap ada perubahan
        function saveCartToLocalStorage() {
            localStorage.setItem('cart', JSON.stringify(cart));
        }

        function addToCart(productId, productName, productPrice) {
            const quantity = parseInt(document.getElementById('quantity').value);
            const existingItem = cart.find(item => item.id === productId);
            
            if (existingItem) {
                existingItem.quantity += quantity;
            } else {
                cart.push({id: productId, name: productName, price: productPrice, quantity: quantity});
            }
            saveCartToLocalStorage();
            updateCartUI();
            showNotification(`${quantity} ${productName} ditambahkan ke keranjang!`);
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
            saveCartToLocalStorage();
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

        // Keyboard navigation for modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });

        // Click outside modal to close
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Initialize
        loadCartFromLocalStorage();
        updateCartUI();

        // Quantity input validation
        document.getElementById('quantity').addEventListener('input', function() {
            const value = parseInt(this.value);
            const max = parseInt(this.max);
            const min = parseInt(this.min);
            
            if (value > max) {
                this.value = max;
            } else if (value < min) {
                this.value = min;
            }
        });

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