<?php
require_once 'auth_check.php';
hasAccess();

// Set judul halaman
$page_title = 'Riwayat Stok';

// Ambil data riwayat stok dengan pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Filter
$type = isset($_GET['type']) ? $_GET['type'] : '';
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';

try {
    $pdo = getDB();
    
    // Query dasar
    $base_query = "
        FROM stock_movements sm
        JOIN products p ON sm.product_id = p.id
        WHERE 1=1
    ";
    $params = [];

    // Tambahkan filter
    if ($type) {
        $base_query .= " AND sm.type = ?";
        $params[] = $type;
    }
    if ($product_id) {
        $base_query .= " AND sm.product_id = ?";
        $params[] = $product_id;
    }
    if ($date_start) {
        $base_query .= " AND DATE(sm.created_at) >= ?";
        $params[] = $date_start;
    }
    if ($date_end) {
        $base_query .= " AND DATE(sm.created_at) <= ?";
        $params[] = $date_end;
    }

    // Hitung total
    $stmt = $pdo->prepare("SELECT COUNT(*) " . $base_query);
    $stmt->execute($params);
    $total_movements = $stmt->fetchColumn();
    $total_pages = ceil($total_movements / $limit);

    // Ambil data
    $query = "
        SELECT 
            sm.*,
            p.name as product_name,
            p.sku
        " . $base_query . "
        ORDER BY sm.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $movements = $stmt->fetchAll();

    // Ambil daftar produk untuk filter
    $stmt = $pdo->query("SELECT id, name, sku FROM products ORDER BY name");
    $products = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error fetching stock movements: " . $e->getMessage());
    $movements = [];
    $products = [];
    $total_pages = 1;
}

// Include header
require_once 'includes/header.php';
// Include sidebar
require_once 'includes/sidebar.php';
?>

<!-- Main Content -->
<div class="ml-64 p-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold text-dark">Riwayat Stok</h1>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-md p-6 mb-8">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
                <select name="type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Semua</option>
                    <option value="in" <?php echo $type === 'in' ? 'selected' : ''; ?>>Stok Masuk</option>
                    <option value="out" <?php echo $type === 'out' ? 'selected' : ''; ?>>Stok Keluar</option>
                    <option value="adjustment" <?php echo $type === 'adjustment' ? 'selected' : ''; ?>>Penyesuaian</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Produk</label>
                <select name="product_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Semua Produk</option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>" <?php echo $product_id === $product['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($product['name']); ?> (<?php echo $product['sku']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                <input type="date" name="date_start" value="<?php echo $date_start; ?>"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
                <input type="date" name="date_end" value="<?php echo $date_end; ?>"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            </div>
            <div class="md:col-span-4 flex justify-end">
                <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Movements Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referensi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($movements as $movement): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo formatDate($movement['created_at']); ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($movement['product_name']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo $movement['sku']; ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo getMovementTypeColor($movement['type']); ?>">
                                <?php echo getMovementTypeText($movement['type']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php echo $movement['type'] === 'out' ? '-' : '+'; ?>
                                <?php echo $movement['quantity']; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($movement['reference']); ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($movement['notes']); ?></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $type; ?>&product_id=<?php echo $product_id; ?>&date_start=<?php echo $date_start; ?>&date_end=<?php echo $date_end; ?>" 
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Previous
                </a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $type; ?>&product_id=<?php echo $product_id; ?>&date_start=<?php echo $date_start; ?>&date_end=<?php echo $date_end; ?>" 
                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Next
                </a>
                <?php endif; ?>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                        <span class="font-medium"><?php echo min($offset + $limit, $total_movements); ?></span> of 
                        <span class="font-medium"><?php echo $total_movements; ?></span> results
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $type; ?>&product_id=<?php echo $product_id; ?>&date_start=<?php echo $date_start; ?>&date_end=<?php echo $date_end; ?>" 
                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Previous</span>
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&type=<?php echo $type; ?>&product_id=<?php echo $product_id; ?>&date_start=<?php echo $date_start; ?>&date_end=<?php echo $date_end; ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium 
                                  <?php echo $i === $page ? 'text-primary bg-primary/10' : 'text-gray-700 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $type; ?>&product_id=<?php echo $product_id; ?>&date_start=<?php echo $date_start; ?>&date_end=<?php echo $date_end; ?>" 
                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Next</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

<?php
function getMovementTypeColor($type) {
    $colors = [
        'in' => 'bg-green-100 text-green-800',
        'out' => 'bg-red-100 text-red-800',
        'adjustment' => 'bg-yellow-100 text-yellow-800'
    ];
    return $colors[$type] ?? 'bg-gray-100 text-gray-800';
}

function getMovementTypeText($type) {
    $texts = [
        'in' => 'Stok Masuk',
        'out' => 'Stok Keluar',
        'adjustment' => 'Penyesuaian'
    ];
    return $texts[$type] ?? $type;
}
?> 