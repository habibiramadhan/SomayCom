<!-- admin/reports/sales.php -->
<?php
// Detailed Sales Report
$page_title = 'Laporan Penjualan Detail';

// Get filters
$period = $_GET['period'] ?? 'month';
$date_start = $_GET['date_start'] ?? date('Y-m-01');
$date_end = $_GET['date_end'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$payment = $_GET['payment'] ?? '';
$area = $_GET['area'] ?? '';

$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    $pdo = getDB();
    
    // Build WHERE clause
    $where = "WHERE DATE(o.created_at) BETWEEN ? AND ?";
    $params = [$date_start, $date_end];
    
    if (!empty($status)) {
        $where .= " AND o.order_status = ?";
        $params[] = $status;
    }
    
    if (!empty($payment)) {
        $where .= " AND o.payment_method = ?";
        $params[] = $payment;
    }
    
    if (!empty($area)) {
        $where .= " AND o.shipping_area_id = ?";
        $params[] = $area;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM orders o $where";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalOrders = $stmt->fetchColumn();
    $totalPages = ceil($totalOrders / $limit);
    
    // Get orders
    $sql = "
        SELECT 
            o.*,
            sa.area_name,
            COUNT(oi.id) as total_items
        FROM orders o
        LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        $where
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // Get shipping areas for filter
    $stmt = $pdo->prepare("SELECT * FROM shipping_areas WHERE is_active = 1 ORDER BY area_name");
    $stmt->execute();
    $shippingAreas = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching sales report: " . $e->getMessage());
    $orders = [];
    $shippingAreas = [];
    $totalOrders = 0;
    $totalPages = 0;
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
                <a href="reports.php" class="text-gray-400 hover:text-gray-600 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Laporan Penjualan Detail</h1>
                    <p class="text-gray-600 mt-2">Analisa detail semua transaksi penjualan</p>
                </div>
            </div>
        </div>

        <!-- Flash Message -->
        <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-xl border-l-4 <?php echo $flash['type'] === 'success' ? 'bg-green-50 border-green-400 text-green-700' : 'bg-red-50 border-red-400 text-red-700'; ?>">
            <div class="flex items-center">
                <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-triangle text-red-500'; ?> mr-3 text-lg"></i>
                <span class="font-medium"><?php echo htmlspecialchars($flash['message']); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                <input type="hidden" name="action" value="sales">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Periode</label>
                    <select name="period" onchange="toggleCustomDate()" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-200">
                        <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                        <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
                        <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Bulan Ini</option>
                        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Tahun Ini</option>
                        <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Kustom</option>
                    </select>
                </div>
                
                <div id="customDateStart" class="<?php echo $period !== 'custom' ? 'hidden' : ''; ?>">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Dari</label>
                    <input type="date" name="date_start" value="<?php echo $date_start; ?>" 
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-200">
                </div>
                
                <div id="customDateEnd" class="<?php echo $period !== 'custom' ? 'hidden' : ''; ?>">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Sampai</label>
                    <input type="date" name="date_end" value="<?php echo $date_end; ?>"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-200">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status Order</label>
                    <select name="status" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-200">
                        <option value="">Semua Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                        <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                        <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Diproses</option>
                        <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>Dikirim</option>
                        <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Pembayaran</label>
                    <select name="payment" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-200">
                        <option value="">Semua Metode</option>
                        <option value="cod" <?php echo $payment === 'cod' ? 'selected' : ''; ?>>COD</option>
                        <option value="transfer" <?php echo $payment === 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Area</label>
                    <select name="area" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-200">
                        <option value="">Semua Area</option>
                        <?php foreach ($shippingAreas as $shippingArea): ?>
                        <option value="<?php echo $shippingArea['id']; ?>" <?php echo $area == $shippingArea['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($shippingArea['area_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex space-x-2">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="reports.php?action=sales" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                        <i class="fas fa-times mr-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Data Penjualan (<?php echo number_format($totalOrders); ?> transaksi)
                    </h3>
                    <a href="reports.php?action=export&<?php echo http_build_query($_GET); ?>" 
                       class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors">
                        <i class="fas fa-download mr-2"></i>Export Excel
                    </a>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pelanggan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Area</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pembayaran</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-4 text-gray-300"></i>
                                <p class="text-lg">Tidak ada data penjualan</p>
                                <p class="text-sm">Sesuaikan filter untuk melihat data yang berbeda</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div>
                                    <div class="text-sm font-medium text-gray-900"><?php echo $order['order_number']; ?></div>
                                    <div class="text-sm text-gray-500"><?php echo formatDate($order['created_at'], 'd/m/Y H:i'); ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div>
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo $order['customer_phone']; ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($order['area_name'] ?? '-'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo number_format($order['total_items']); ?> item</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $order['payment_method'] === 'cod' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                        <?php echo $order['payment_method'] === 'cod' ? 'COD' : 'Transfer'; ?>
                                    </span>
                                    <span class="text-xs text-gray-500 mt-1">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo formatRupiah($order['total_amount']); ?></div>
                                <?php if ($order['shipping_cost'] > 0): ?>
                                <div class="text-xs text-gray-500">+ <?php echo formatRupiah($order['shipping_cost']); ?> ongkir</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getOrderStatusColor($order['order_status']); ?>">
                                    <?php echo getOrderStatusText($order['order_status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-500"><?php echo formatDate($order['created_at'], 'd/m/Y'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <a href="orders.php?action=view&id=<?php echo $order['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-900" title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
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
                        Menampilkan <?php echo number_format($offset + 1); ?> sampai <?php echo number_format(min($page * $limit, $totalOrders)); ?> dari <?php echo number_format($totalOrders); ?> transaksi
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
                           class="px-3 py-2 text-sm <?php echo $i == $page ? 'bg-blue-500 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-md">
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
        function toggleCustomDate() {
            const period = document.querySelector('select[name="period"]').value;
            const customStart = document.getElementById('customDateStart');
            const customEnd = document.getElementById('customDateEnd');
            
            if (period === 'custom') {
                customStart.classList.remove('hidden');
                customEnd.classList.remove('hidden');
            } else {
                customStart.classList.add('hidden');
                customEnd.classList.add('hidden');
            }
        }
    </script>
</body>
</html>

<?php
// Helper functions
function getOrderStatusColor($status) {
    $colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'confirmed' => 'bg-blue-100 text-blue-800',
        'processing' => 'bg-purple-100 text-purple-800',
        'shipped' => 'bg-indigo-100 text-indigo-800',
        'delivered' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}

function getOrderStatusText($status) {
    $texts = [
        'pending' => 'Menunggu',
        'confirmed' => 'Dikonfirmasi',
        'processing' => 'Diproses',
        'shipped' => 'Dikirim',
        'delivered' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];
    return $texts[$status] ?? ucfirst($status);
}
?>