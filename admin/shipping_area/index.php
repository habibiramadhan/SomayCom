<?php
// admin/shipping_area/index.php
// Set page title
$page_title = 'Kelola Area Pengiriman';

// Get filters from GET parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'is_active' => $_GET['is_active'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['per_page'] ?? 20);

try {
    $pdo = getDB();
    
    // Build WHERE clause
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $where .= " AND (area_name LIKE ? OR postal_code LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($filters['is_active'] !== '') {
        $where .= " AND is_active = ?";
        $params[] = $filters['is_active'];
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM shipping_areas $where";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalAreas = $stmt->fetchColumn();
    $totalPages = ceil($totalAreas / $limit);
    
    // Get shipping areas
    $offset = ($page - 1) * $limit;
    $sql = "
        SELECT sa.*,
               COUNT(o.id) as total_orders,
               COALESCE(SUM(o.total_amount), 0) as total_revenue
        FROM shipping_areas sa
        LEFT JOIN orders o ON sa.id = o.shipping_area_id AND o.order_status NOT IN ('cancelled')
        $where
        GROUP BY sa.id
        ORDER BY sa.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $areas = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching shipping areas: " . $e->getMessage());
    $areas = [];
    $totalAreas = 0;
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
                <h1 class="text-3xl font-bold text-gray-900">Kelola Area Pengiriman</h1>
                <p class="text-gray-600">Kelola area dan ongkos kirim Tangerang Selatan</p>
            </div>
            <?php if (hasPermission('manage_products')): ?>
            <a href="shipping_area.php?action=create" class="bg-primary text-white px-6 py-3 rounded-lg font-semibold hover:bg-secondary transition-colors inline-flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Tambah Area
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
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <input type="hidden" name="action" value="index">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cari Area</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                           placeholder="Nama area atau kode pos..." 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="is_active" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Semua Status</option>
                        <option value="1" <?php echo $filters['is_active'] === '1' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="0" <?php echo $filters['is_active'] === '0' ? 'selected' : ''; ?>>Nonaktif</option>
                    </select>
                </div>
                
                <div class="flex space-x-2">
                    <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition-colors">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                    <a href="shipping_area.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                        <i class="fas fa-times mr-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Areas Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Daftar Area Pengiriman (<?php echo number_format($totalAreas); ?>)
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Area</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Pos</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ongkir</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estimasi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Pesanan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($areas)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-map text-4xl mb-4 text-gray-300"></i>
                                <p class="text-lg">Belum ada area pengiriman</p>
                                <p class="text-sm">Klik tombol "Tambah Area" untuk menambah area baru</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($areas as $area): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($area['area_name']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($area['postal_code'] ?? '-'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo formatRupiah($area['shipping_cost']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($area['estimated_delivery'] ?? '-'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo number_format($area['total_orders']); ?> pesanan</div>
                                <div class="text-sm text-gray-500"><?php echo formatRupiah($area['total_revenue']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $area['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $area['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <a href="shipping_area.php?action=view&id=<?php echo $area['id']; ?>" 
                                       class="text-gray-400 hover:text-gray-600" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (hasPermission('manage_products')): ?>
                                    <a href="shipping_area.php?action=edit&id=<?php echo $area['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="deleteArea(<?php echo $area['id']; ?>, '<?php echo addslashes($area['area_name']); ?>')" 
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
                        Menampilkan <?php echo (($page - 1) * $limit) + 1; ?> sampai <?php echo min($page * $limit, $totalAreas); ?> dari <?php echo $totalAreas; ?> area
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

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" action="shipping_area.php?action=process" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="process_action" value="delete">
        <input type="hidden" name="id" id="delete_area_id">
    </form>

    <script>
        function changePerPage(value) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', value);
            url.searchParams.set('page', '1');
            window.location = url;
        }

        function deleteArea(id, name) {
            if (confirm(`Apakah Anda yakin ingin menghapus area "${name}"?\n\nTindakan ini tidak dapat dibatalkan.`)) {
                document.getElementById('delete_area_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>