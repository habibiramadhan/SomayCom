<?php
// admin/shipping_area/view.php
// Set page title
$page_title = 'Detail Area Pengiriman';

// Get area ID
$areaId = (int)$id;

try {
    $pdo = getDB();
    
    // Get area data
    $stmt = $pdo->prepare("SELECT * FROM shipping_areas WHERE id = ?");
    $stmt->execute([$areaId]);
    $area = $stmt->fetch();
    
    if (!$area) {
        redirectWithMessage('shipping_area.php', 'Area tidak ditemukan', 'error');
    }
    
    // Get area statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(o.id) as total_orders,
            COUNT(DISTINCT o.customer_phone) as total_customers,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COALESCE(AVG(o.total_amount), 0) as avg_order_value,
            MAX(o.created_at) as last_order_date
        FROM orders o
        WHERE o.shipping_area_id = ? AND o.order_status NOT IN ('cancelled')
    ");
    $stmt->execute([$areaId]);
    $stats = $stmt->fetch();
    
    // Get recent orders
    $stmt = $pdo->prepare("
        SELECT o.*, 
               COUNT(oi.id) as total_items
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.shipping_area_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$areaId]);
    $recentOrders = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error loading area view: " . $e->getMessage());
    redirectWithMessage('shipping_area.php', 'Terjadi kesalahan saat memuat data area', 'error');
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
                <a href="shipping_area.php" class="text-gray-400 hover:text-gray-600 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($area['area_name']); ?></h1>
                    <p class="text-gray-600">Detail area pengiriman</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-3">
                <?php if (hasPermission('manage_products')): ?>
                <a href="shipping_area.php?action=edit&id=<?php echo $area['id']; ?>" 
                   class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition-colors">
                    <i class="fas fa-edit mr-2"></i>Edit Area
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
                <!-- Area Overview -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Informasi Area</h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $area['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $area['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Nama Area</h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($area['area_name']); ?></p>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Kode Pos</h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($area['postal_code'] ?? '-'); ?></p>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Ongkos Kirim</h4>
                            <p class="text-xl font-bold text-primary"><?php echo formatRupiah($area['shipping_cost']); ?></p>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Estimasi Pengiriman</h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($area['estimated_delivery'] ?? '-'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Pesanan Terbaru</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pelanggan</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-3xl mb-2"></i>
                                        <p>Belum ada pesanan di area ini</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $order['order_number']; ?></div>
                                        <div class="text-sm text-gray-500"><?php echo $order['total_items']; ?> item</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo formatRupiah($order['total_amount']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo getOrderStatusColor($order['order_status']); ?>">
                                            <?php echo getOrderStatusText($order['order_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?php echo formatDate($order['created_at'], 'd/m/Y'); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo formatDate($order['created_at'], 'H:i'); ?></div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Statistics -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Statistik Area</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Pesanan</span>
                            <span class="font-bold text-gray-900"><?php echo number_format($stats['total_orders'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Pelanggan</span>
                            <span class="font-bold text-gray-900"><?php echo number_format($stats['total_customers'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Pendapatan</span>
                            <span class="font-bold text-primary"><?php echo formatRupiah($stats['total_revenue'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Rata-rata Pesanan</span>
                            <span class="font-bold text-gray-900"><?php echo formatRupiah($stats['avg_order_value'] ?? 0); ?></span>
                        </div>
                        <?php if ($stats['last_order_date']): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Pesanan Terakhir</span>
                            <span class="font-bold text-gray-900"><?php echo formatDate($stats['last_order_date'], 'd/m/Y'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Area Meta -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Meta</h3>
                    <div class="space-y-4 text-sm">
                        <div>
                            <span class="text-gray-600">Dibuat:</span>
                            <p class="font-medium"><?php echo formatDate($area['created_at']); ?></p>
                        </div>
                        <div>
                            <span class="text-gray-600">Terakhir Diupdate:</span>
                            <p class="font-medium"><?php echo formatDate($area['updated_at']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <?php if (hasPermission('manage_products')): ?>
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Aksi Cepat</h3>
                    <div class="space-y-3">
                        <a href="shipping_area.php?action=edit&id=<?php echo $area['id']; ?>" 
                           class="w-full bg-primary text-white py-2 px-4 rounded-lg hover:bg-secondary transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-edit mr-2"></i>Edit Area
                        </a>
                        <button onclick="toggleAreaStatus(<?php echo $area['id']; ?>, <?php echo $area['is_active'] ? 'false' : 'true'; ?>)" 
                                class="w-full <?php echo $area['is_active'] ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600'; ?> text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas <?php echo $area['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?> mr-2"></i>
                            <?php echo $area['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleAreaStatus(areaId, newStatus) {
            const action = newStatus === 'true' ? 'mengaktifkan' : 'menonaktifkan';
            if (confirm(`Apakah Anda yakin ingin ${action} area ini?`)) {
                const formData = new FormData();
                formData.append('process_action', 'toggle_status');
                formData.append('id', areaId);
                formData.append('is_active', newStatus);
                formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
                
                fetch('shipping_area.php?action=process', {
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