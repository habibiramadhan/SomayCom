<?php
// Set page title
$page_title = 'Kelola Produk';

// Get filters from GET parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'category_id' => $_GET['category_id'] ?? '',
    'is_active' => $_GET['is_active'] ?? '',
    'is_featured' => $_GET['is_featured'] ?? '',
    'low_stock' => $_GET['low_stock'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['per_page'] ?? 20);

try {
    $pdo = getDB();
    
    // Build WHERE clause
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($filters['category_id'])) {
        $where .= " AND p.category_id = ?";
        $params[] = $filters['category_id'];
    }
    
    if ($filters['is_active'] !== '') {
        $where .= " AND p.is_active = ?";
        $params[] = $filters['is_active'];
    }
    
    if ($filters['is_featured'] !== '') {
        $where .= " AND p.is_featured = ?";
        $params[] = $filters['is_featured'];
    }
    
    if (!empty($filters['low_stock'])) {
        $where .= " AND p.stock_quantity <= p.min_stock";
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
        SELECT p.*, c.name as category_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
               CASE 
                   WHEN p.discount_price IS NOT NULL AND p.discount_price > 0 THEN p.discount_price 
                   ELSE p.price 
               END as final_price
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        $where
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // DEBUG: Check if products have images
    foreach ($products as &$product) {
        // Debug: Check what's in primary_image
        if (empty($product['primary_image'])) {
            // Try to get any image for this product
            $imgStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC LIMIT 1");
            $imgStmt->execute([$product['id']]);
            $anyImage = $imgStmt->fetchColumn();
            if ($anyImage) {
                $product['primary_image'] = $anyImage;
            }
        }
    }
    unset($product); // Break reference
    
    // Get categories for filter
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching products: " . $e->getMessage());
    $products = [];
    $categories = [];
    $totalProducts = 0;
    $totalPages = 0;
}

// Flash message
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
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Kelola Produk</h1>
                <p class="text-gray-600">Kelola produk somay dan siomay</p>
            </div>
            <?php if (hasPermission('manage_products')): ?>
            <a href="product.php?action=create" class="bg-primary text-white px-6 py-3 rounded-lg font-semibold hover:bg-secondary transition-colors inline-flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Tambah Produk
            </a>
            <?php endif; ?>
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

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                <input type="hidden" name="action" value="index">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cari Produk</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                           placeholder="Nama, SKU, atau deskripsi..." 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                    <select name="category_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $filters['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="is_active" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Semua Status</option>
                        <option value="1" <?php echo $filters['is_active'] === '1' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="0" <?php echo $filters['is_active'] === '0' ? 'selected' : ''; ?>>Nonaktif</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Featured</label>
                    <select name="is_featured" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Semua</option>
                        <option value="1" <?php echo $filters['is_featured'] === '1' ? 'selected' : ''; ?>>Featured</option>
                        <option value="0" <?php echo $filters['is_featured'] === '0' ? 'selected' : ''; ?>>Non-Featured</option>
                    </select>
                </div>
                
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="low_stock" value="1" <?php echo $filters['low_stock'] ? 'checked' : ''; ?> 
                               class="rounded border-gray-300 text-primary focus:border-primary focus:ring-primary">
                        <span class="ml-2 text-sm text-gray-700">Stok Menipis</span>
                    </label>
                </div>
                
                <div class="flex space-x-2">
                    <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition-colors">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                    <a href="product.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                        <i class="fas fa-times mr-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Products Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Daftar Produk (<?php echo number_format($totalProducts); ?>)
                    </h3>
                    <div class="flex items-center space-x-4">
                        <select onchange="changePerPage(this.value)" class="rounded-md border-gray-300 text-sm">
                            <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20 per halaman</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 per halaman</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 per halaman</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-box-open text-4xl mb-4 text-gray-300"></i>
                                <p class="text-lg">Belum ada produk</p>
                                <p class="text-sm">Klik tombol "Tambah Produk" untuk menambah produk baru</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="h-16 w-16 flex-shrink-0">
                                        <?php if (!empty($product['primary_image'])): ?>
                                        <img class="h-16 w-16 rounded-lg object-cover" 
                                             src="../uploads/products/<?php echo htmlspecialchars($product['primary_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             onerror="this.onerror=null; this.src='../uploads/products/placeholder.jpg'; this.parentElement.innerHTML='<div class=\'h-16 w-16 rounded-lg bg-gray-200 flex items-center justify-center\'><i class=\'fas fa-image text-gray-400 text-2xl\'></i></div>';">
                                        <?php else: ?>
                                        <div class="h-16 w-16 rounded-lg bg-gray-200 flex items-center justify-center">
                                            <i class="fas fa-image text-gray-400 text-2xl"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                            <?php if ($product['is_featured']): ?>
                                            <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-star mr-1"></i>Featured
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-500">SKU: <?php echo htmlspecialchars($product['sku']); ?></div>
                                        <!-- Debug: Show image path -->
                                        <?php if (!empty($product['primary_image'])): ?>
                                        <div class="text-xs text-gray-400">Image: <?php echo htmlspecialchars($product['primary_image']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($product['category_name'] ?? 'Tanpa Kategori'); ?></div>
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
                                    <span class="text-sm font-medium text-gray-900 mr-2"><?php echo number_format($product['stock_quantity']); ?></span>
                                    <?php if ($product['stock_quantity'] <= $product['min_stock']): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Menipis
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (hasPermission('manage_products')): ?>
                                <button onclick="openStockModal(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['stock_quantity']; ?>)" 
                                        class="text-xs text-primary hover:text-secondary mt-1">
                                    <i class="fas fa-edit mr-1"></i>Ubah Stok
                                </button>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $product['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $product['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <a href="product.php?action=view&id=<?php echo $product['id']; ?>" 
                                       class="text-gray-400 hover:text-gray-600" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (hasPermission('manage_products')): ?>
                                    <a href="product.php?action=edit&id=<?php echo $product['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>')" 
                                            class="text-red-600 hover:text-red-900" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="bg-white px-6 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Menampilkan <?php echo (($page - 1) * $limit) + 1; ?> sampai <?php echo min($page * $limit, $totalProducts); ?> dari <?php echo $totalProducts; ?> produk
                    </div>
                    <div class="flex items-center space-x-2">
                        <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                           class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            <i class="fas fa-chevron-left mr-1"></i>Sebelumnya
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                           class="px-3 py-2 text-sm <?php echo $i == $page ? 'bg-primary text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-md">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                           class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Selanjutnya<i class="fas fa-chevron-right ml-1"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
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

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" action="product.php?action=process" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="process_action" value="delete">
        <input type="hidden" name="id" id="delete_product_id">
    </form>

    <script>
        function changePerPage(value) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', value);
            url.searchParams.set('page', '1');
            window.location = url;
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

        function deleteProduct(id, name) {
            if (confirm(`Apakah Anda yakin ingin menghapus produk "${name}"?\n\nTindakan ini tidak dapat dibatalkan.`)) {
                document.getElementById('delete_product_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modal when clicking outside
        document.getElementById('stockModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStockModal();
            }
        });
    </script>
</body>
</html>