<?php
// Set page title
$page_title = 'Detail Produk';

// Get product ID
$productId = (int)$id;

try {
    $pdo = getDB();
    
    // Get product data with category
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name,
               CASE 
                   WHEN p.discount_price IS NOT NULL AND p.discount_price > 0 THEN p.discount_price 
                   ELSE p.price 
               END as final_price
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        redirectWithMessage('product.php', 'Produk tidak ditemukan', 'error');
    }
    
    // Get product images
    $stmt = $pdo->prepare("
        SELECT * FROM product_images 
        WHERE product_id = ? 
        ORDER BY is_primary DESC, sort_order ASC
    ");
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll();
    
    // Get recent stock movements
    $stmt = $pdo->prepare("
        SELECT sm.*, a.full_name as admin_name
        FROM stock_movements sm
        LEFT JOIN admins a ON sm.admin_id = a.id
        WHERE sm.product_id = ?
        ORDER BY sm.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$productId]);
    $stockMovements = $stmt->fetchAll();
    
    // Get sales statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(oi.id) as total_orders,
            SUM(oi.quantity) as total_sold,
            SUM(oi.subtotal) as total_revenue,
            AVG(oi.price) as avg_selling_price
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.product_id = ? AND o.order_status NOT IN ('cancelled')
    ");
    $stmt->execute([$productId]);
    $salesStats = $stmt->fetch();
    
} catch (Exception $e) {
    error_log("Error loading product view: " . $e->getMessage());
    redirectWithMessage('product.php', 'Terjadi kesalahan saat memuat data produk', 'error');
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
                <a href="product.php" class="text-gray-400 hover:text-gray-600 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($product['name']); ?></h1>
                    <p class="text-gray-600">SKU: <?php echo htmlspecialchars($product['sku']); ?></p>
                </div>
            </div>
            
            <div class="flex items-center space-x-3">
                <a href="../pages/product-detail.php?slug=<?php echo $product['slug']; ?>" target="_blank" 
                   class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                    <i class="fas fa-external-link-alt mr-2"></i>Lihat di Website
                </a>
                <?php if (hasPermission('manage_products')): ?>
                <a href="product.php?action=edit&id=<?php echo $product['id']; ?>" 
                   class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition-colors">
                    <i class="fas fa-edit mr-2"></i>Edit Produk
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
                <!-- Product Overview -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Informasi Produk</h3>
                        <div class="flex items-center space-x-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $product['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $product['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                            </span>
                            <?php if ($product['is_featured']): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                <i class="fas fa-star mr-1"></i>Featured
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Kategori</h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($product['category_name'] ?? 'Tanpa Kategori'); ?></p>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Berat</h4>
                            <p class="text-gray-600"><?php echo number_format($product['weight'], 0); ?> gram</p>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Harga</h4>
                            <div class="flex items-center space-x-2">
                                <?php if ($product['discount_price'] && $product['discount_price'] != $product['price']): ?>
                                <span class="text-gray-500 line-through"><?php echo formatRupiah($product['price']); ?></span>
                                <span class="text-xl font-bold text-primary"><?php echo formatRupiah($product['final_price']); ?></span>
                                <?php else: ?>
                                <span class="text-xl font-bold text-gray-900"><?php echo formatRupiah($product['price']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Stok</h4>
                            <div class="flex items-center space-x-2">
                                <span class="text-xl font-bold <?php echo $product['stock_quantity'] <= $product['min_stock'] ? 'text-red-600' : 'text-green-600'; ?>">
                                    <?php echo number_format($product['stock_quantity']); ?>
                                </span>
                                <?php if ($product['stock_quantity'] <= $product['min_stock']): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>Menipis
                                </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-gray-500">Min. stok: <?php echo number_format($product['min_stock']); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($product['short_description']): ?>
                    <div class="mt-6">
                        <h4 class="font-medium text-gray-900 mb-2">Deskripsi Singkat</h4>
                        <p class="text-gray-600"><?php echo htmlspecialchars($product['short_description']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($product['description']): ?>
                    <div class="mt-6">
                        <h4 class="font-medium text-gray-900 mb-2">Deskripsi Lengkap</h4>
                        <div class="text-gray-600"><?php echo nl2br(htmlspecialchars($product['description'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Product Images -->
                <?php if (!empty($images)): ?>
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Gambar Produk</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <?php foreach ($images as $image): ?>
                        <div class="relative">
                            <img src="../uploads/products/<?php echo htmlspecialchars($image['image_path']); ?>" 
                                 class="w-full h-32 object-cover rounded-lg border cursor-pointer hover:opacity-75 transition-opacity"
                                 onclick="openImageModal('<?php echo htmlspecialchars($image['image_path']); ?>')"
                                 onerror="this.onerror=null; this.style.display='none'; this.parentElement.innerHTML='<div class=\'w-full h-32 bg-gray-200 rounded-lg border flex items-center justify-center\'><i class=\'fas fa-image text-gray-400 text-2xl\'></i></div>';">
                            <?php if ($image['is_primary']): ?>
                            <div class="absolute top-2 left-2 bg-primary text-white text-xs px-2 py-1 rounded">Utama</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stock Movements -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Riwayat Stok Terbaru</h3>
                    <?php if (!empty($stockMovements)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left">Tanggal</th>
                                    <th class="px-4 py-2 text-left">Jenis</th>
                                    <th class="px-4 py-2 text-left">Jumlah</th>
                                    <th class="px-4 py-2 text-left">Stok</th>
                                    <th class="px-4 py-2 text-left">Admin</th>
                                    <th class="px-4 py-2 text-left">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($stockMovements as $movement): ?>
                                <tr>
                                    <td class="px-4 py-2"><?php echo formatDate($movement['created_at'], 'd/m/Y H:i'); ?></td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $movement['movement_type'] === 'in' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <i class="fas <?php echo $movement['movement_type'] === 'in' ? 'fa-arrow-up' : 'fa-arrow-down'; ?> mr-1"></i>
                                            <?php echo $movement['movement_type'] === 'in' ? 'Masuk' : 'Keluar'; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 font-medium <?php echo $movement['quantity'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo $movement['quantity'] > 0 ? '+' : ''; ?><?php echo number_format($movement['quantity']); ?>
                                    </td>
                                    <td class="px-4 py-2"><?php echo number_format($movement['previous_stock']); ?> → <?php echo number_format($movement['current_stock']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($movement['admin_name'] ?? 'System'); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($movement['notes'] ?: ucfirst($movement['reference_type'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-500 text-center py-8">Belum ada riwayat perubahan stok</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Sales Statistics -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Statistik Penjualan</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Pesanan</span>
                            <span class="font-bold text-gray-900"><?php echo number_format($salesStats['total_orders'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Terjual</span>
                            <span class="font-bold text-gray-900"><?php echo number_format($salesStats['total_sold'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Pendapatan</span>
                            <span class="font-bold text-primary"><?php echo formatRupiah($salesStats['total_revenue'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Harga Rata-rata</span>
                            <span class="font-bold text-gray-900"><?php echo formatRupiah($salesStats['avg_selling_price'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Product Meta -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Meta</h3>
                    <div class="space-y-4 text-sm">
                        <div>
                            <span class="text-gray-600">Dibuat:</span>
                            <p class="font-medium"><?php echo formatDate($product['created_at']); ?></p>
                        </div>
                        <div>
                            <span class="text-gray-600">Terakhir Diupdate:</span>
                            <p class="font-medium"><?php echo formatDate($product['updated_at']); ?></p>
                        </div>
                        <div>
                            <span class="text-gray-600">Slug:</span>
                            <p class="font-medium text-blue-600"><?php echo htmlspecialchars($product['slug']); ?></p>
                        </div>
                        <?php if ($product['meta_title']): ?>
                        <div>
                            <span class="text-gray-600">Meta Title:</span>
                            <p class="font-medium"><?php echo htmlspecialchars($product['meta_title']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($product['meta_description']): ?>
                        <div>
                            <span class="text-gray-600">Meta Description:</span>
                            <p class="font-medium"><?php echo htmlspecialchars($product['meta_description']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <?php if (hasPermission('manage_products')): ?>
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Aksi Cepat</h3>
                    <div class="space-y-3">
                        <button onclick="openStockModal(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['stock_quantity']; ?>)" 
                                class="w-full bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600 transition-colors">
                            <i class="fas fa-boxes mr-2"></i>Ubah Stok
                        </button>
                        <a href="product.php?action=edit&id=<?php echo $product['id']; ?>" 
                           class="w-full bg-primary text-white py-2 px-4 rounded-lg hover:bg-secondary transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-edit mr-2"></i>Edit Produk
                        </a>
                        <button onclick="toggleProductStatus(<?php echo $product['id']; ?>, <?php echo $product['is_active'] ? 'false' : 'true'; ?>)" 
                                class="w-full <?php echo $product['is_active'] ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600'; ?> text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas <?php echo $product['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?> mr-2"></i>
                            <?php echo $product['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>
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

    <!-- Stock Modal -->
    <div id="stockModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Ubah Stok Produk</h3>
                    <form id="stockForm">
                        <input type="hidden" id="stock_product_id" name="product_id">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Produk</label>
                            <p id="stock_product_name" class="text-sm text-gray-600"></p>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Stok Saat Ini</label>
                            <p id="current_stock" class="text-sm font-bold text-gray-900"></p>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Perubahan Stok</label>
                            <input type="number" id="stock_quantity" name="quantity" 
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                   placeholder="Contoh: +10 atau -5">
                            <p class="text-xs text-gray-500 mt-1">Gunakan angka positif untuk menambah, negatif untuk mengurangi</p>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
                            <textarea id="stock_notes" name="notes" rows="3" 
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                      placeholder="Alasan perubahan stok..."></textarea>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeStockModal()" 
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                                Batal
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-secondary">
                                Update Stok
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openImageModal(imagePath) {
            document.getElementById('modalImage').src = '../../' + imagePath;
            document.getElementById('imageModal').classList.remove('hidden');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.add('hidden');
        }

        function openStockModal(productId, productName, currentStock) {
            document.getElementById('stock_product_id').value = productId;
            document.getElementById('stock_product_name').textContent = productName;
            document.getElementById('current_stock').textContent = currentStock;
            document.getElementById('stock_quantity').value = '';
            document.getElementById('stock_notes').value = '';
            document.getElementById('stockModal').classList.remove('hidden');
        }

        function closeStockModal() {
            document.getElementById('stockModal').classList.add('hidden');
        }

        document.getElementById('stockForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('process_action', 'update_stock');
            formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
            
            fetch('product.php?action=process', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeStockModal();
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan sistem');
            });
        });

        function toggleProductStatus(productId, newStatus) {
            const action = newStatus === 'true' ? 'mengaktifkan' : 'menonaktifkan';
            if (confirm(`Apakah Anda yakin ingin ${action} produk ini?`)) {
                // Implement AJAX call to toggle status
                const formData = new FormData();
                formData.append('process_action', 'toggle_status');
                formData.append('id', productId);
                formData.append('is_active', newStatus);
                formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
                
                fetch('product.php?action=process', {
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

        // Close modals when clicking outside
        document.getElementById('stockModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStockModal();
            }
        });

        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
                closeStockModal();
            }
        });
    </script>
</body>
</html>