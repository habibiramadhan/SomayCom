<!-- admin/orders/index.php -->
<?php
// Set page title
$page_title = 'Kelola Pesanan';

// Get filters from GET parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'order_status' => $_GET['order_status'] ?? '',
    'payment_status' => $_GET['payment_status'] ?? '',
    'payment_method' => $_GET['payment_method'] ?? '',
    'shipping_area_id' => $_GET['shipping_area_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['per_page'] ?? 20);

try {
    $pdo = getDB();
    
    // Build WHERE clause
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $where .= " AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.customer_email LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($filters['order_status'])) {
        $where .= " AND o.order_status = ?";
        $params[] = $filters['order_status'];
    }
    
    if (!empty($filters['payment_status'])) {
        $where .= " AND o.payment_status = ?";
        $params[] = $filters['payment_status'];
    }
    
    if (!empty($filters['payment_method'])) {
        $where .= " AND o.payment_method = ?";
        $params[] = $filters['payment_method'];
    }
    
    if (!empty($filters['shipping_area_id'])) {
        $where .= " AND o.shipping_area_id = ?";
        $params[] = $filters['shipping_area_id'];
    }
    
    if (!empty($filters['date_from'])) {
        $where .= " AND DATE(o.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where .= " AND DATE(o.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM orders o LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id $where";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalOrders = $stmt->fetchColumn();
    $totalPages = ceil($totalOrders / $limit);
    
    // Get orders
    $offset = ($page - 1) * $limit;
    $sql = "
        SELECT o.*, sa.area_name,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o
        LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
        $where
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
    
    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            order_status,
            COUNT(*) as count,
            SUM(total_amount) as total_amount
        FROM orders 
        GROUP BY order_status
    ");
    $stmt->execute();
    $statusStats = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
    $shippingAreas = [];
    $totalOrders = 0;
    $totalPages = 0;
    $statusStats = [];
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
                <h1 class="text-3xl font-bold text-gray-900">Kelola Pesanan</h1>
                <p class="text-gray-600">Kelola semua pesanan pelanggan</p>
            </div>
            
            <!-- Export Button -->
            <div class="flex space-x-3">
                <a href="orders.php?action=export&<?php echo http_build_query($filters); ?>" 
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

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <?php
            $statusConfig = [
                'pending' => ['label' => 'Menunggu', 'color' => 'yellow', 'icon' => 'fa-clock'],
                'confirmed' => ['label' => 'Dikonfirmasi', 'color' => 'blue', 'icon' => 'fa-check'],
                'processing' => ['label' => 'Diproses', 'color' => 'purple', 'icon' => 'fa-cog'],
                'shipped' => ['label' => 'Dikirim', 'color' => 'indigo', 'icon' => 'fa-truck'],
                'delivered' => ['label' => 'Selesai', 'color' => 'green', 'icon' => 'fa-check-circle'],
                'cancelled' => ['label' => 'Dibatalkan', 'color' => 'red', 'icon' => 'fa-times-circle']
            ];
            
            foreach ($statusConfig as $status => $config):
                $count = $statusStats[$status][0]['count'] ?? 0;
                $amount = $statusStats[$status][0]['total_amount'] ?? 0;
            ?>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500"><?php echo $config['label']; ?></p>
                        <h3 class="text-2xl font-bold text-gray-900"><?php echo number_format($count); ?></h3>
                        <p class="text-xs text-gray-500"><?php echo formatRupiah($amount); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-<?php echo $config['color']; ?>-100 rounded-full flex items-center justify-center">
                        <i class="fas <?php echo $config['icon']; ?> text-<?php echo $config['color']; ?>-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-7 gap-4 items-end">
                <input type="hidden" name="action" value="index">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cari Pesanan</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                           placeholder="Nomor, nama, atau telepon..." 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status Pesanan</label>
                    <select name="order_status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Semua Status</option>
                        <option value="pending" <?php echo $filters['order_status'] === 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                        <option value="confirmed" <?php echo $filters['order_status'] === 'confirmed' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                        <option value="processing" <?php echo $filters['order_status'] === 'processing' ? 'selected' : ''; ?>>Diproses</option>
                        <option value="shipped" <?php echo $filters['order_status'] === 'shipped' ? 'selected' : ''; ?>>Dikirim</option>
                        <option value="delivered" <?php echo $filters['order_status'] === 'delivered' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="cancelled" <?php echo $filters['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status Pembayaran</label>
                    <select name="payment_status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Semua</option>
                        <option value="pending" <?php echo $filters['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $filters['payment_status'] === 'paid' ? 'selected' : ''; ?>>Lunas</option>
                        <option value="failed" <?php echo $filters['payment_status'] === 'failed' ? 'selected' : ''; ?>>Gagal</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Metode Bayar</label>
                    <select name="payment_method" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Semua</option>
                        <option value="cod" <?php echo $filters['payment_method'] === 'cod' ? 'selected' : ''; ?>>COD</option>
                        <option value="transfer" <?php echo $filters['payment_method'] === 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Area</label>
                    <select name="shipping_area_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Semua Area</option>
                        <?php foreach ($shippingAreas as $area): ?>
                        <option value="<?php echo $area['id']; ?>" <?php echo $filters['shipping_area_id'] == $area['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($area['area_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Dari Tanggal</label>
                    <input type="date" name="date_from" value="<?php echo $filters['date_from']; ?>" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                </div>
                
                <div class="flex space-x-2">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sampai</label>
                        <input type="date" name="date_to" value="<?php echo $filters['date_to']; ?>" 
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition-colors">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="orders.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Daftar Pesanan (<?php echo number_format($totalOrders); ?>)
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pesanan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pelanggan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Area</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pembayaran</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-shopping-cart text-4xl mb-4 text-gray-300"></i>
                                <p class="text-lg">Belum ada pesanan</p>
                                <p class="text-sm">Pesanan akan muncul di sini setelah pelanggan melakukan pemesanan</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <a href="orders.php?action=view&id=<?php echo $order['id']; ?>" class="text-primary hover:underline">
                                        <?php echo htmlspecialchars($order['order_number']); ?>
                                    </a>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo formatDate($order['created_at'], 'd/m/Y H:i'); ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?php echo $order['item_count']; ?> item
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
                                <?php if ($order['customer_email']): ?>
                                <div class="text-xs text-gray-400"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($order['area_name'] ?? 'Area tidak diketahui'); ?></div>
                                <?php if ($order['shipping_cost'] > 0): ?>
                                <div class="text-xs text-gray-500">Ongkir: <?php echo formatRupiah($order['shipping_cost']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo formatRupiah($order['total_amount']); ?></div>
                                <?php if ($order['subtotal'] != $order['total_amount']): ?>
                                <div class="text-xs text-gray-500">Subtotal: <?php echo formatRupiah($order['subtotal']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col space-y-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getPaymentStatusColor($order['payment_status']); ?>">
                                        <?php echo getPaymentStatusText($order['payment_status']); ?>
                                    </span>
                                    <span class="text-xs text-gray-500"><?php echo ucfirst($order['payment_method']); ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getOrderStatusColor($order['order_status']); ?>">
                                    <?php echo getOrderStatusText($order['order_status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <a href="orders.php?action=view&id=<?php echo $order['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (hasPermission('manage_orders') && $order['order_status'] !== 'cancelled'): ?>
                                    <button onclick="openStatusModal(<?php echo $order['id']; ?>, '<?php echo $order['order_status']; ?>')" 
                                            class="text-green-600 hover:text-green-900" title="Update Status">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $order['customer_phone']); ?>?text=Halo,%20mengenai%20pesanan%20<?php echo $order['order_number']; ?>" 
                                       target="_blank" class="text-green-500 hover:text-green-700" title="WhatsApp">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
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
                        Menampilkan <?php echo (($page - 1) * $limit) + 1; ?> sampai <?php echo min($page * $limit, $totalOrders); ?> dari <?php echo $totalOrders; ?> pesanan
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

    <!-- Status Update Modal -->
    <div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Update Status Pesanan</h3>
                    <form id="statusForm">
                        <input type="hidden" id="status_order_id" name="order_id">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status Pesanan</label>
                            <select id="new_status" name="new_status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                                <option value="pending">Menunggu</option>
                                <option value="confirmed">Dikonfirmasi</option>
                                <option value="processing">Diproses</option>
                                <option value="shipped">Dikirim</option>
                                <option value="delivered">Selesai</option>
                                <option value="cancelled">Dibatalkan</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Catatan Admin</label>
                            <textarea id="admin_notes" name="admin_notes" rows="3" 
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                      placeholder="Catatan untuk perubahan status..."></textarea>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeStatusModal()" 
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                                Batal
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-secondary">
                                Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function changePerPage(value) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', value);
            url.searchParams.set('page', '1');
            window.location = url;
        }

        function openStatusModal(orderId, currentStatus) {
            document.getElementById('status_order_id').value = orderId;
            document.getElementById('new_status').value = currentStatus;
            document.getElementById('admin_notes').value = '';
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }

        document.getElementById('statusForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_status');
            formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
            
            fetch('orders.php?action=process', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeStatusModal();
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

        // Close modal when clicking outside
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStatusModal();
            }
        });
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

function getPaymentStatusColor($status) {
    $colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'paid' => 'bg-green-100 text-green-800',
        'failed' => 'bg-red-100 text-red-800'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}

function getPaymentStatusText($status) {
    $texts = [
        'pending' => 'Pending',
        'paid' => 'Lunas',
        'failed' => 'Gagal'
    ];
    return $texts[$status] ?? ucfirst($status);
}
?>