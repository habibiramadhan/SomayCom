<?php
// admin/stock_movements/index.php
// Set page title
$page_title = 'Riwayat Pergerakan Stok';

// Get filters from GET parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'product_id' => $_GET['product_id'] ?? '',
    'movement_type' => $_GET['movement_type'] ?? '',
    'reference_type' => $_GET['reference_type'] ?? '',
    'date_start' => $_GET['date_start'] ?? '',
    'date_end' => $_GET['date_end'] ?? '',
    'admin_id' => $_GET['admin_id'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['per_page'] ?? 20);

try {
    $pdo = getDB();
    
    // Build WHERE clause
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR sm.notes LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($filters['product_id'])) {
        $where .= " AND sm.product_id = ?";
        $params[] = $filters['product_id'];
    }
    
    if (!empty($filters['movement_type'])) {
        $where .= " AND sm.movement_type = ?";
        $params[] = $filters['movement_type'];
    }
    
    if (!empty($filters['reference_type'])) {
        $where .= " AND sm.reference_type = ?";
        $params[] = $filters['reference_type'];
    }
    
    if (!empty($filters['date_start'])) {
        $where .= " AND DATE(sm.created_at) >= ?";
        $params[] = $filters['date_start'];
    }
    
    if (!empty($filters['date_end'])) {
        $where .= " AND DATE(sm.created_at) <= ?";
        $params[] = $filters['date_end'];
    }
    
    if (!empty($filters['admin_id'])) {
        $where .= " AND sm.admin_id = ?";
        $params[] = $filters['admin_id'];
    }
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) 
        FROM stock_movements sm
        LEFT JOIN products p ON sm.product_id = p.id
        LEFT JOIN admins a ON sm.admin_id = a.id
        $where
    ";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalMovements = $stmt->fetchColumn();
    $totalPages = ceil($totalMovements / $limit);
    
    // Get stock movements
    $offset = ($page - 1) * $limit;
    $sql = "
        SELECT sm.*, 
               p.name as product_name, p.sku as product_sku,
               a.full_name as admin_name,
               o.order_number
        FROM stock_movements sm
        LEFT JOIN products p ON sm.product_id = p.id
        LEFT JOIN admins a ON sm.admin_id = a.id
        LEFT JOIN orders o ON sm.reference_id = o.id AND sm.reference_type = 'sale'
        $where
        ORDER BY sm.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movements = $stmt->fetchAll();
    
    // Get products for filter
    $stmt = $pdo->prepare("SELECT id, name, sku FROM products WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $products = $stmt->fetchAll();
    
    // Get admins for filter
    $stmt = $pdo->prepare("SELECT id, full_name FROM admins WHERE is_active = 1 ORDER BY full_name");
    $stmt->execute();
    $admins = $stmt->fetchAll();
    
    // Get summary statistics
    $summaryParams = array_slice($params, 0, -2); // Remove limit and offset
    $summarySql = "
        SELECT 
            SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END) as total_in,
            SUM(CASE WHEN sm.movement_type = 'out' THEN ABS(sm.quantity) ELSE 0 END) as total_out,
            COUNT(*) as total_movements
        FROM stock_movements sm
        LEFT JOIN products p ON sm.product_id = p.id
        LEFT JOIN admins a ON sm.admin_id = a.id
        $where
    ";
    $stmt = $pdo->prepare($summarySql);
    $stmt->execute($summaryParams);
    $summary = $stmt->fetch();
    
} catch (Exception $e) {
    error_log("Error fetching stock movements: " . $e->getMessage());
    $movements = [];
    $products = [];
    $admins = [];
    $totalMovements = 0;
    $totalPages = 0;
    $summary = ['total_in' => 0, 'total_out' => 0, 'total_movements' => 0];
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
                <h1 class="text-3xl font-bold text-gray-900">Riwayat Pergerakan Stok</h1>
                <p class="text-gray-600">Kelola dan monitor pergerakan stok produk</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="stock_movements.php?action=export&<?php echo http_build_query($filters); ?>" 
                   class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors">
                    <i class="fas fa-download mr-2"></i>Export Excel
                </a>
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

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Stok Masuk</p>
                        <h3 class="text-2xl font-bold text-green-600"><?php echo number_format($summary['total_in']); ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-up text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Stok Keluar</p>
                        <h3 class="text-2xl font-bold text-red-600"><?php echo number_format($summary['total_out']); ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-down text-red-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Transaksi</p>
                        <h3 class="text-2xl font-bold text-gray-900"><?php echo number_format($summary['total_movements']); ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exchange-alt text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-8 gap-4 items-end">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cari</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                           placeholder="Produk, SKU, atau catatan..." 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Produk</label>
                    <select name="product_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Semua Produk</option>
                        <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>" <?php echo $filters['product_id'] == $product['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($product['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Jenis</label>
                    <select name="movement_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Semua Jenis</option>
                        <option value="in" <?php echo $filters['movement_type'] === 'in' ? 'selected' : ''; ?>>Masuk</option>
                        <option value="out" <?php echo $filters['movement_type'] === 'out' ? 'selected' : ''; ?>>Keluar</option>
                        <option value="adjustment" <?php echo $filters['movement_type'] === 'adjustment' ? 'selected' : ''; ?>>Penyesuaian</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Referensi</label>
                    <select name="reference_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Semua Referensi</option>
                        <option value="purchase" <?php echo $filters['reference_type'] === 'purchase' ? 'selected' : ''; ?>>Pembelian</option>
                        <option value="sale" <?php echo $filters['reference_type'] === 'sale' ? 'selected' : ''; ?>>Penjualan</option>
                        <option value="adjustment" <?php echo $filters['reference_type'] === 'adjustment' ? 'selected' : ''; ?>>Penyesuaian</option>
                        <option value="return" <?php echo $filters['reference_type'] === 'return' ? 'selected' : ''; ?>>Retur</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Dari Tanggal</label>
                    <input type="date" name="date_start" value="<?php echo htmlspecialchars($filters['date_start']); ?>"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sampai Tanggal</label>
                    <input type="date" name="date_end" value="<?php echo htmlspecialchars($filters['date_end']); ?>"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                </div>
                
                <div class="flex space-x-2">
                    <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition-colors">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                    <a href="stock_movements.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                        <i class="fas fa-times mr-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Stock Movements Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Daftar Pergerakan Stok (<?php echo number_format($totalMovements); ?>)
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kuantitas</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referensi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($movements)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-exchange-alt text-4xl mb-4 text-gray-300"></i>
                                <p class="text-lg">Belum ada pergerakan stok</p>
                                <p class="text-sm">Data pergerakan stok akan muncul di sini</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($movements as $movement): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo formatDate($movement['created_at'], 'd/m/Y'); ?></div>
                                <div class="text-xs text-gray-500"><?php echo formatDate($movement['created_at'], 'H:i'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($movement['product_name'] ?? 'Produk Dihapus'); ?></div>
                                <div class="text-sm text-gray-500">SKU: <?php echo htmlspecialchars($movement['product_sku'] ?? '-'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getMovementTypeColor($movement['movement_type']); ?>">
                                    <i class="fas <?php echo getMovementTypeIcon($movement['movement_type']); ?> mr-1"></i>
                                    <?php echo getMovementTypeText($movement['movement_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-medium <?php echo $movement['quantity'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $movement['quantity'] > 0 ? '+' : ''; ?><?php echo number_format($movement['quantity']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">
                                    <?php echo number_format($movement['previous_stock']); ?> → 
                                    <span class="font-medium"><?php echo number_format($movement['current_stock']); ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo getReferenceText($movement['reference_type']); ?></div>
                                <?php if ($movement['order_number']): ?>
                                <div class="text-xs text-blue-600"><?php echo htmlspecialchars($movement['order_number']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($movement['admin_name'] ?? 'System'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-600 max-w-xs truncate" title="<?php echo htmlspecialchars($movement['notes']); ?>">
                                    <?php echo htmlspecialchars($movement['notes'] ?: '-'); ?>
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
                        Menampilkan <?php echo (($page - 1) * $limit) + 1; ?> sampai <?php echo min($page * $limit, $totalMovements); ?> dari <?php echo $totalMovements; ?> pergerakan
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

    <script>
        function changePerPage(value) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', value);
            url.searchParams.set('page', '1');
            window.location = url;
        }
    </script>
</body>
</html>

<?php
// Helper functions
function getMovementTypeColor($type) {
    $colors = [
        'in' => 'bg-green-100 text-green-800',
        'out' => 'bg-red-100 text-red-800',
        'adjustment' => 'bg-blue-100 text-blue-800'
    ];
    return $colors[$type] ?? 'bg-gray-100 text-gray-800';
}

function getMovementTypeIcon($type) {
    $icons = [
        'in' => 'fa-arrow-up',
        'out' => 'fa-arrow-down',
        'adjustment' => 'fa-adjust'
    ];
    return $icons[$type] ?? 'fa-exchange-alt';
}

function getMovementTypeText($type) {
    $texts = [
        'in' => 'Masuk',
        'out' => 'Keluar',
        'adjustment' => 'Penyesuaian'
    ];
    return $texts[$type] ?? ucfirst($type);
}

function getReferenceText($type) {
    $texts = [
        'purchase' => 'Pembelian',
        'sale' => 'Penjualan',
        'adjustment' => 'Penyesuaian',
        'return' => 'Retur'
    ];
    return $texts[$type] ?? ucfirst($type);
}
?>