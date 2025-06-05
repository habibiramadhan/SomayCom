<!-- admin/reports/index.php -->
<?php
// Set page title
$page_title = 'Laporan & Analisa';

// Get date filters
$period = $_GET['period'] ?? 'month';
$date_start = $_GET['date_start'] ?? date('Y-m-01');
$date_end = $_GET['date_end'] ?? date('Y-m-d');

// Set default date range based on period
switch ($period) {
    case 'today':
        $date_start = $date_end = date('Y-m-d');
        break;
    case 'week':
        $date_start = date('Y-m-d', strtotime('-7 days'));
        $date_end = date('Y-m-d');
        break;
    case 'month':
        $date_start = date('Y-m-01');
        $date_end = date('Y-m-d');
        break;
    case 'year':
        $date_start = date('Y-01-01');
        $date_end = date('Y-m-d');
        break;
}

try {
    $pdo = getDB();
    
    // Sales Summary
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN order_status = 'delivered' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled_orders,
            COALESCE(SUM(CASE WHEN order_status NOT IN ('cancelled') THEN total_amount END), 0) as total_revenue,
            COALESCE(AVG(CASE WHEN order_status NOT IN ('cancelled') THEN total_amount END), 0) as avg_order_value
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$date_start, $date_end]);
    $salesSummary = $stmt->fetch();
    
    // Top Products
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.price,
            SUM(oi.quantity) as total_sold,
            SUM(oi.subtotal) as total_revenue
        FROM products p
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND o.order_status NOT IN ('cancelled')
        GROUP BY p.id, p.name, p.sku, p.price
        ORDER BY total_sold DESC
        LIMIT 10
    ");
    $stmt->execute([$date_start, $date_end]);
    $topProducts = $stmt->fetchAll();
    
    // Sales by Area
    $stmt = $pdo->prepare("
        SELECT 
            sa.area_name,
            COUNT(o.id) as total_orders,
            COALESCE(SUM(o.total_amount), 0) as total_revenue
        FROM shipping_areas sa
        LEFT JOIN orders o ON sa.id = o.shipping_area_id 
        WHERE (DATE(o.created_at) BETWEEN ? AND ? OR o.created_at IS NULL)
        AND (o.order_status NOT IN ('cancelled') OR o.order_status IS NULL)
        GROUP BY sa.id, sa.area_name
        HAVING total_orders > 0
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$date_start, $date_end]);
    $salesByArea = $stmt->fetchAll();
    
    // Daily Sales (for chart)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_revenue
        FROM orders
        WHERE order_status NOT IN ('cancelled')
        AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([$date_start, $date_end]);
    $dailySales = $stmt->fetchAll();
    
    // Payment Methods Summary
    $stmt = $pdo->prepare("
        SELECT 
            payment_method,
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_revenue
        FROM orders
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND order_status NOT IN ('cancelled')
        GROUP BY payment_method
        ORDER BY total_revenue DESC
    ");
    $stmt->execute([$date_start, $date_end]);
    $paymentMethods = $stmt->fetchAll();
    
    // Recent Activity
    $stmt = $pdo->prepare("
        SELECT 
            o.order_number,
            o.customer_name,
            o.total_amount,
            o.order_status,
            o.created_at
        FROM orders o
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$date_start, $date_end]);
    $recentOrders = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching reports data: " . $e->getMessage());
    $salesSummary = ['total_orders' => 0, 'completed_orders' => 0, 'cancelled_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0];
    $topProducts = [];
    $salesByArea = [];
    $dailySales = [];
    $paymentMethods = [];
    $recentOrders = [];
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-chart-bar text-white text-lg"></i>
                    </div>
                    Laporan & Analisa
                </h1>
                <p class="text-gray-600 mt-2">Dashboard analisa penjualan dan performa bisnis</p>
            </div>
            
            <!-- Export Button -->
            <div class="flex items-center space-x-3">
                <a href="reports.php?action=export&period=<?php echo $period; ?>&date_start=<?php echo $date_start; ?>&date_end=<?php echo $date_end; ?>" 
                   class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors">
                    <i class="fas fa-download mr-2"></i>Export Excel
                </a>
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

        <!-- Filter Period -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 mb-8">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Periode Laporan</label>
                    <select name="period" onchange="toggleCustomDate()" class="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-200">
                        <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                        <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
                        <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Bulan Ini</option>
                        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Tahun Ini</option>
                        <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Kustom</option>
                    </select>
                </div>
                
                <div id="customDateRange" class="flex items-end gap-2 <?php echo $period !== 'custom' ? 'hidden' : ''; ?>">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Dari Tanggal</label>
                        <input type="date" name="date_start" value="<?php echo $date_start; ?>" 
                               class="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Sampai Tanggal</label>
                        <input type="date" name="date_end" value="<?php echo $date_end; ?>"
                               class="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-200">
                    </div>
                </div>
                
                <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                    <i class="fas fa-filter mr-2"></i>Terapkan Filter
                </button>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Orders -->
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Total Pesanan</p>
                        <h3 class="text-3xl font-bold"><?php echo number_format($salesSummary['total_orders']); ?></h3>
                        <p class="text-blue-200 text-xs mt-1">
                            <?php echo number_format($salesSummary['completed_orders']); ?> selesai, 
                            <?php echo number_format($salesSummary['cancelled_orders']); ?> dibatalkan
                        </p>
                    </div>
                    <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-shopping-cart text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Total Revenue -->
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Total Pendapatan</p>
                        <h3 class="text-3xl font-bold"><?php echo formatRupiah($salesSummary['total_revenue']); ?></h3>
                        <p class="text-green-200 text-xs mt-1">Dari pesanan yang tidak dibatalkan</p>
                    </div>
                    <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-money-bill-wave text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Average Order Value -->
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">Rata-rata Order</p>
                        <h3 class="text-3xl font-bold"><?php echo formatRupiah($salesSummary['avg_order_value']); ?></h3>
                        <p class="text-purple-200 text-xs mt-1">Per transaksi</p>
                    </div>
                    <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-calculator text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Success Rate -->
            <div class="bg-gradient-to-br from-orange-500 to-red-500 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">Tingkat Sukses</p>
                        <h3 class="text-3xl font-bold">
                            <?php 
                            $successRate = $salesSummary['total_orders'] > 0 ? 
                                round(($salesSummary['completed_orders'] / $salesSummary['total_orders']) * 100, 1) : 0;
                            echo $successRate; 
                            ?>%
                        </h3>
                        <p class="text-orange-200 text-xs mt-1">Pesanan berhasil diselesaikan</p>
                    </div>
                    <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Tables -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Daily Sales Chart -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Penjualan Harian</h3>
                <div class="relative h-80">
                    <canvas id="dailySalesChart"></canvas>
                </div>
            </div>
            
            <!-- Payment Methods Chart -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Metode Pembayaran</h3>
                <div class="relative h-80">
                    <canvas id="paymentChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Data Tables -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Top Products -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Produk Terlaris</h3>
                        <a href="reports.php?action=products&period=<?php echo $period; ?>&date_start=<?php echo $date_start; ?>&date_end=<?php echo $date_end; ?>" 
                           class="text-blue-500 hover:text-blue-700 text-sm font-medium">
                            Lihat Detail <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Terjual</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($topProducts)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-box-open text-3xl mb-2"></i>
                                    <p>Belum ada data penjualan produk</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($topProducts as $index => $product): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                            <span class="text-blue-600 font-bold text-sm"><?php echo $index + 1; ?></span>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo $product['sku']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo number_format($product['total_sold']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-green-600"><?php echo formatRupiah($product['total_revenue']); ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sales by Area -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Penjualan per Area</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pesanan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($salesByArea)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-map-marker-alt text-3xl mb-2"></i>
                                    <p>Belum ada data penjualan per area</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($salesByArea as $index => $area): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                            <span class="text-green-600 font-bold text-sm"><?php echo $index + 1; ?></span>
                                        </div>
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($area['area_name']); ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo number_format($area['total_orders']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-green-600"><?php echo formatRupiah($area['total_revenue']); ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="mt-8 bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Aktivitas Terbaru</h3>
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
                                <p>Belum ada aktivitas pesanan</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $order['order_number']; ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></div>
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
                                <div class="text-sm text-gray-500"><?php echo formatDate($order['created_at'], 'd/m/Y H:i'); ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Function to toggle custom date range
        function toggleCustomDate() {
            const period = document.querySelector('select[name="period"]').value;
            const customRange = document.getElementById('customDateRange');
            
            if (period === 'custom') {
                customRange.classList.remove('hidden');
            } else {
                customRange.classList.add('hidden');
            }
        }

        // Chart configurations
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;

        // Daily Sales Chart
        const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
        const dailySalesData = <?php echo json_encode($dailySales); ?>;
        
        const dailySalesChart = new Chart(dailySalesCtx, {
            type: 'line',
            data: {
                labels: dailySalesData.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit' });
                }),
                datasets: [{
                    label: 'Pendapatan',
                    data: dailySalesData.map(item => item.total_revenue),
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointBackgroundColor: '#3B82F6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        callbacks: {
                            label: function(context) {
                                return 'Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6B7280'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(107, 114, 128, 0.1)'
                        },
                        ticks: {
                            color: '#6B7280',
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });

        // Payment Methods Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        const paymentData = <?php echo json_encode($paymentMethods); ?>;
        
        const paymentChart = new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: paymentData.map(item => item.payment_method === 'cod' ? 'COD' : 'Transfer'),
                datasets: [{
                    data: paymentData.map(item => item.total_revenue),
                    backgroundColor: [
                        '#10B981',
                        '#F59E0B',
                        '#EF4444',
                        '#8B5CF6'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = 'Rp ' + context.parsed.toLocaleString('id-ID');
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>

<?php
// Helper functions for order status
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