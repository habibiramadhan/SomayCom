<?php
require_once 'auth_check.php';
hasAccess();

// Set judul halaman
$page_title = 'Dashboard';

// Filter tanggal
$period = isset($_GET['period']) ? $_GET['period'] : 'week';
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : date('Y-m-d', strtotime('-7 days'));
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : date('Y-m-d');

// Ambil data untuk dashboard
try {
    $pdo = getDB();
    
    // Hitung total penjualan hari ini
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(AVG(total_amount), 0) as avg_order_value
        FROM orders 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $today_stats = $stmt->fetch();
    
    // Total produk
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_products FROM products WHERE is_active = 1");
    $stmt->execute();
    $total_products = $stmt->fetch()['total_products'];
    
    // Produk dengan stok menipis
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as low_stock 
        FROM products 
        WHERE stock_quantity <= min_stock AND is_active = 1
    ");
    $stmt->execute();
    $low_stock = $stmt->fetch()['low_stock'];
    
    // Pesanan terbaru
    $stmt = $pdo->prepare("
        SELECT o.*, sa.area_name
        FROM orders o
        LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
        WHERE o.order_status NOT IN ('cancelled')
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_orders = $stmt->fetchAll();
    
    // Ambil 5 produk terlaris
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.price,
            COALESCE(SUM(oi.quantity), 0) as total_sold,
            COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE o.order_status NOT IN ('cancelled')
        AND p.is_active = 1
        AND o.created_at BETWEEN ? AND ?
        GROUP BY p.id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $stmt->execute([$date_start, $date_end]);
    $top_products = $stmt->fetchAll();
    
    // Hitung penjualan per area
    $stmt = $pdo->prepare("
        SELECT 
            sa.name as area_name,
            COUNT(*) as total_orders,
            COALESCE(SUM(o.total_amount), 0) as total_revenue
        FROM orders o
        JOIN shipping_areas sa ON o.shipping_area_id = sa.id
        WHERE o.order_status NOT IN ('cancelled')
        AND o.created_at BETWEEN ? AND ?
        GROUP BY sa.id
        ORDER BY total_revenue DESC
        LIMIT 5
    ");
    $stmt->execute([$date_start, $date_end]);
    $sales_by_area = $stmt->fetchAll();

    // Data untuk grafik
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_revenue
        FROM orders
        WHERE order_status NOT IN ('cancelled')
        AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([$date_start, $date_end]);
    $chart_data = $stmt->fetchAll();

    // Format data untuk Chart.js
    $dates = [];
    $orders = [];
    $revenue = [];
    foreach ($chart_data as $data) {
        $dates[] = formatDate($data['date']);
        $orders[] = $data['total_orders'];
        $revenue[] = $data['total_revenue'];
    }

} catch (Exception $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
    $today_stats = ['total_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0];
    $total_products = 0;
    $low_stock = 0;
    $recent_orders = [];
    $top_products = [];
    $sales_by_area = [];
    $dates = [];
    $orders = [];
    $revenue = [];
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
        <div>
            <h1 class="text-2xl font-bold text-dark">Dashboard</h1>
            <p class="text-gray-600">Selamat datang, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></p>
        </div>
        
        <div class="flex items-center space-x-4">
            <!-- Filter Periode -->
            <div class="flex items-center space-x-4">
                <select id="period" class="rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                    <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Minggu Ini</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Bulan Ini</option>
                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Tahun Ini</option>
                    <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Kustom</option>
                </select>
                
                <div id="customDateRange" class="hidden space-x-2">
                    <input type="date" id="date_start" value="<?php echo $date_start; ?>" 
                           class="rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                    <input type="date" id="date_end" value="<?php echo $date_end; ?>"
                           class="rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                </div>
                
                <button onclick="applyFilter()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary">
                    <i class="fas fa-filter mr-2"></i>Terapkan
                </button>
            </div>

            <!-- Admin Info & Logout -->
            <div class="flex items-center space-x-4 border-l border-gray-200 pl-4">
                <div class="text-right">
                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></p>
                </div>
                <a href="logout.php" class="text-red-500 hover:text-red-700">
                    <i class="fas fa-sign-out-alt text-xl"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Today's Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Pesanan Hari Ini</p>
                    <h3 class="text-2xl font-bold text-dark"><?php echo number_format($today_stats['total_orders']); ?></h3>
                </div>
                <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
                    <i class="fas fa-shopping-cart text-primary text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Pendapatan Hari Ini</p>
                    <h3 class="text-2xl font-bold text-dark">Rp <?php echo number_format($today_stats['total_revenue']); ?></h3>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Rata-rata Nilai Pesanan</p>
                    <h3 class="text-2xl font-bold text-dark">Rp <?php echo number_format($today_stats['avg_order_value']); ?></h3>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-chart-line text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Sales Trend -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold text-dark mb-4">Tren Penjualan</h3>
            <canvas id="salesChart" height="300"></canvas>
        </div>
        
        <!-- Sales by Area -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold text-dark mb-4">Penjualan per Area</h3>
            <canvas id="areaChart" height="300"></canvas>
        </div>
    </div>

    <!-- Top Products -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-dark">Produk Terlaris</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Terjual</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pendapatan</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($top_products as $product): ?>
                    <tr>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo $product['sku']; ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo number_format($product['total_sold']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">Rp <?php echo number_format($product['total_revenue']); ?></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Sales Trend Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($dates); ?>,
        datasets: [{
            label: 'Jumlah Pesanan',
            data: <?php echo json_encode($orders); ?>,
            borderColor: '#FF6B35',
            backgroundColor: '#FF6B3510',
            tension: 0.4,
            fill: true
        }, {
            label: 'Pendapatan (Rp)',
            data: <?php echo json_encode($revenue); ?>,
            borderColor: '#F7931E',
            backgroundColor: '#F7931E10',
            tension: 0.4,
            fill: true,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Jumlah Pesanan'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Pendapatan (Rp)'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});

// Sales by Area Chart
const areaCtx = document.getElementById('areaChart').getContext('2d');
new Chart(areaCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($sales_by_area, 'area_name')); ?>,
        datasets: [{
            label: 'Pendapatan per Area',
            data: <?php echo json_encode(array_column($sales_by_area, 'total_revenue')); ?>,
            backgroundColor: '#FF6B35',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Pendapatan (Rp)'
                }
            }
        }
    }
});

// Filter handling
document.getElementById('period').addEventListener('change', function() {
    const customDateRange = document.getElementById('customDateRange');
    if (this.value === 'custom') {
        customDateRange.classList.remove('hidden');
    } else {
        customDateRange.classList.add('hidden');
    }
});

function applyFilter() {
    const period = document.getElementById('period').value;
    let url = 'dashboard.php?period=' + period;
    
    if (period === 'custom') {
        const dateStart = document.getElementById('date_start').value;
        const dateEnd = document.getElementById('date_end').value;
        url += '&date_start=' + dateStart + '&date_end=' + dateEnd;
    }
    
    window.location.href = url;
}
</script>

</body>
</html> 