<?php
// admin/category/view.php
// Set page title
$page_title = 'Detail Kategori';

// Get category ID
$categoryId = (int)$id;

try {
    $pdo = getDB();
    
    // Get category data
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();
    
    if (!$category) {
        redirectWithMessage('category.php', 'Kategori tidak ditemukan', 'error');
    }
    
    // Get products in this category
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
               CASE 
                   WHEN p.discount_price IS NOT NULL AND p.discount_price > 0 THEN p.discount_price 
                   ELSE p.price 
               END as final_price
        FROM products p
        WHERE p.category_id = ?
        ORDER BY p.is_active DESC, p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$categoryId]);
    $products = $stmt->fetchAll();
    
    // Get category statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_products,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_products,
            COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_products,
            COUNT(CASE WHEN is_featured = 1 THEN 1 END) as featured_products,
            AVG(price) as avg_price,
            SUM(stock_quantity) as total_stock
        FROM products 
        WHERE category_id = ?
    ");
    $stmt->execute([$categoryId]);
    $stats = $stmt->fetch();
    
} catch (Exception $e) {
    error_log("Error loading category view: " . $e->getMessage());
    redirectWithMessage('category.php', 'Terjadi kesalahan saat memuat data kategori', 'error');
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
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
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center">
                <a href="category.php" class="text-gray-400 hover:text-gray-600 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($category['name']); ?></h1>
                    <p class="text-gray-600">Slug: <?php echo htmlspecialchars($category['slug']); ?></p>
                </div>
            </div>
            
            <div class="flex items-center space-x-3">
                <a href="../pages/products.php?category=<?php echo $category['slug']; ?>" target="_blank" 
                   class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                    <i class="fas fa-external-link-alt mr-2"></i>Lihat di Website
                </a>
                <?php if (hasPermission('manage_products')): ?>
                <a href="category.php?action=edit&id=<?php echo $category['id']; ?>" 
                   class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition-colors">
                    <i class="fas fa-edit mr-2"></i>Edit Kategori
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Flash Message -->
        <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $flash['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
            <div class="flex items-center">
                <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                <span><?php echo htmlspecialchars($flash['message']); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Category Overview -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Informasi Kategori</h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $category['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $category['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Category Image -->
                        <?php if (!empty($category['image'])): ?>
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Gambar Kategori</h4>
                            <img src="../uploads/categories/<?php echo htmlspecialchars($category['image']); ?>" 
                                 class="w-full h-48 object-cover rounded-lg border cursor-pointer hover:opacity-75 transition-opacity"
                                 onclick="openImageModal('<?php echo htmlspecialchars($category['image']); ?>')"
                                 onerror="this.style.display='none';">
                        </div>
                        <?php endif; ?>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Urutan Tampil</h4>
                            <p class="text-gray-600"><?php echo number_format($category['sort_order']); ?></p>
                            
                            <?php if ($category['description']): ?>
                            <h4 class="font-medium text-gray-900 mb-2 mt-4">Deskripsi</h4>
                            <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($category['description'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Products in Category -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">Produk dalam Kategori</h3>
                            <a href="../product.php?category_id=<?php echo $category['id']; ?>" 
                               class="text-primary hover:text-secondary text-sm font-medium">
                                Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                    
                    <?php if (!empty($products)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Harga</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stok</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($products as $product): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="h-12 w-12 flex-shrink-0">
                                                <?php if (!empty($product['primary_image'])): ?>
                                                <img class="h-12 w-12 rounded-lg object-cover" 
                                                     src="../uploads/products/<?php echo htmlspecialchars($product['primary_image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                     onerror="this.style.display='none';">
                                                <?php else: ?>
                                                <div class="h-12 w-12 rounded-lg bg-gray-200 flex items-center justify-center">
                                                    <i class="fas fa-image text-gray-400"></i>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                    <?php if ($product['is_featured']): ?>
                                                    <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        <i class="fas fa-star mr-1"></i>Featured
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-sm text-gray-500">SKU: <?php echo htmlspecialchars($product['sku']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php if ($product['discount_price'] && $product['discount_price'] != $product['price']): ?>
                                            <div class="text-sm text-gray-500 line-through"><?php echo formatRupiah($product['price']); ?></div>
                                            <div class="text-sm font-medium text-primary"><?php echo formatRupiah($product['final_price']); ?></div>
                                            <?php else: ?>
                                            <div class="text-sm font-medium text-gray-900"><?php echo formatRupiah($product['price']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium text-gray-900"><?php echo number_format($product['stock_quantity']); ?></span>
                                            <?php if ($product['stock_quantity'] <= $product['min_stock']): ?>
                                            <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>Menipis
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $product['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $product['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-2">
                                            <a href="../product.php?action=view&id=<?php echo $product['id']; ?>" 
                                               class="text-gray-400 hover:text-gray-600" title="Lihat Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (hasPermission('manage_products')): ?>
                                            <a href="../product.php?action=edit&id=<?php echo $product['id']; ?>" 
                                               class="text-blue-600 hover:text-blue-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-box-open text-4xl mb-4 text-gray-300"></i>
                        <p class="text-lg">Belum ada produk dalam kategori ini</p>
                        <?php if (hasPermission('manage_products')): ?>
                        <a href="../product.php?action=create&category_id=<?php echo $category['id']; ?>" 
                           class="mt-4 inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-secondary transition-colors">
                            <i class="fas fa-plus mr-2"></i>Tambah Produk
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Category Statistics -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Statistik Kategori</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Produk</span>
                            <span class="font-bold text-gray-900"><?php echo number_format($stats['total_products'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Produk Aktif</span>
                            <span class="font-bold text-green-600"><?php echo number_format($stats['active_products'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Produk Nonaktif</span>
                            <span class="font-bold text-red-600"><?php echo number_format($stats['inactive_products'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Produk Featured</span>
                            <span class="font-bold text-yellow-600"><?php echo number_format($stats['featured_products'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Rata-rata Harga</span>
                            <span class="font-bold text-primary"><?php echo formatRupiah($stats['avg_price'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Stok</span>
                            <span class="font-bold text-gray-900"><?php echo number_format($stats['total_stock'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Category Meta -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Meta</h3>
                    <div class="space-y-4 text-sm">
                        <div>
                            <span class="text-gray-600">Dibuat:</span>
                            <p class="font-medium"><?php echo formatDate($category['created_at']); ?></p>
                        </div>
                        <div>
                            <span class="text-gray-600">Terakhir Diupdate:</span>
                            <p class="font-medium"><?php echo formatDate($category['updated_at']); ?></p>
                        </div>
                        <div>
                            <span class="text-gray-600">Slug:</span>
                            <p class="font-medium text-blue-600"><?php echo htmlspecialchars($category['slug']); ?></p>
                        </div>
                        <div>
                            <span class="text-gray-600">Urutan:</span>
                            <p class="font-medium"><?php echo $category['sort_order']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <?php if (hasPermission('manage_products')): ?>
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Aksi Cepat</h3>
                    <div class="space-y-3">
                        <a href="../product.php?action=create&category_id=<?php echo $category['id']; ?>" 
                           class="w-full bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600 transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-plus mr-2"></i>Tambah Produk
                        </a>
                        <a href="category.php?action=edit&id=<?php echo $category['id']; ?>" 
                           class="w-full bg-primary text-white py-2 px-4 rounded-lg hover:bg-secondary transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-edit mr-2"></i>Edit Kategori
                        </a>
                        <button onclick="toggleCategoryStatus(<?php echo $category['id']; ?>, <?php echo $category['is_active'] ? 'false' : 'true'; ?>)" 
                                class="w-full <?php echo $category['is_active'] ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600'; ?> text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas <?php echo $category['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?> mr-2"></i>
                            <?php echo $category['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 hidden z-50 flex items-center justify-center">
        <div class="max-w-4xl max-h-full p-4">
            <img id="modalImage" src="" class="max-w-full max-h-full object-contain">
        </div>
        <button onclick="closeImageModal()" class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <script>
        function openImageModal(imagePath) {
            document.getElementById('modalImage').src = '../uploads/categories/' + imagePath;
            document.getElementById('imageModal').classList.remove('hidden');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.add('hidden');
        }

        function toggleCategoryStatus(categoryId, newStatus) {
            const action = newStatus === 'true' ? 'mengaktifkan' : 'menonaktifkan';
            if (confirm(`Apakah Anda yakin ingin ${action} kategori ini?`)) {
                const formData = new FormData();
                formData.append('process_action', 'toggle_status');
                formData.append('id', categoryId);
                formData.append('is_active', newStatus);
                formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
                
                fetch('category.php?action=process', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan sistem');
                });
            }
        }

        // Close modal when clicking outside
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });
    </script>
</body>
</html>