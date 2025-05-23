<?php
require_once 'auth_check.php';
hasAccess(); // Pastikan admin sudah login

// Set judul halaman
$page_title = 'Dashboard';

// Ambil flash message jika ada
$flash = getFlashMessage();

// Filter tanggal
$period = isset($_GET['period']) ? $_GET['period'] : 'week';
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : date('Y-m-d', strtotime('-7 days'));
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : date('Y-m-d');

// Set default date range berdasarkan period
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
        AND order_status NOT IN ('cancelled')
    ");
    $stmt->execute();
    $today_stats = $stmt->fetch();
    
    // Total produk aktif
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
    
    // Total pelanggan
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT customer_phone) as total_customers FROM orders");
    $stmt->execute();
    $total_customers = $stmt->fetch()['total_customers'];
    
    // Pesanan menunggu konfirmasi
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_orders FROM orders WHERE order_status = 'pending'");
    $stmt->execute();
    $pending_orders = $stmt->fetch()['pending_orders'];
    
    // Pesanan terbaru
    $stmt = $pdo->prepare("
        SELECT o.*, sa.area_name
        FROM orders o
        LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_orders = $stmt->fetchAll();
    
    // Produk terlaris dalam periode
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
        WHERE (o.created_at BETWEEN ? AND ? OR o.created_at IS NULL)
        AND (o.order_status NOT IN ('cancelled') OR o.order_status IS NULL)
        AND p.is_active = 1
        GROUP BY p.id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $stmt->execute([$date_start . ' 00:00:00', $date_end . ' 23:59:59']);
    $top_products = $stmt->fetchAll();
    
    // Penjualan per area dalam periode
    $stmt = $pdo->prepare("
        SELECT 
            sa.area_name,
            COUNT(o.id) as total_orders,
            COALESCE(SUM(o.total_amount), 0) as total_revenue
        FROM shipping_areas sa
        LEFT JOIN orders o ON sa.id = o.shipping_area_id 
        WHERE (o.created_at BETWEEN ? AND ? OR o.created_at IS NULL)
        AND (o.order_status NOT IN ('cancelled') OR o.order_status IS NULL)
        GROUP BY sa.id, sa.area_name
        ORDER BY total_revenue DESC
        LIMIT 5
    ");
    $stmt->execute([$date_start . ' 00:00:00', $date_end . ' 23:59:59']);
    $sales_by_area = $stmt->fetchAll();

    // Data untuk grafik (7 hari terakhir)
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
    $stmt->execute([date('Y-m-d', strtotime('-7 days')) . ' 00:00:00', date('Y-m-d') . ' 23:59:59']);
    $chart_data = $stmt->fetchAll();

    // Format data untuk Chart.js
    $dates = [];
    $orders = [];
    $revenue = [];
    
    // Buat array 7 hari terakhir dengan nilai 0
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dates[] = date('d/m', strtotime($date));
        $orders[] = 0;
        $revenue[] = 0;
    }
    
    // Isi data yang ada
    foreach ($chart_data as $data) {
        $key = array_search(date('d/m', strtotime($data['date'])), $dates);
        if ($key !== false) {
            $orders[$key] = (int)$data['total_orders'];
            $revenue[$key] = (float)$data['total_revenue'];
        }
    }

} catch (Exception $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
    // Set default values jika terjadi error
    $today_stats = ['total_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0];
    $total_products = 0;
    $low_stock = 0;
    $total_customers = 0;
    $pending_orders = 0;
    $recent_orders = [];
    $top_products = [];
    $sales_by_area = [];
    $dates = [];
    $orders = [];
    $revenue = [];
}

// Mendapatkan info admin yang sedang login
$current_admin = getCurrentAdmin();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <!-- Tailwind CSS -->
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
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
                <p class="text-gray-600">Selamat datang, <?php echo htmlspecialchars($current_admin['name']); ?>!</p>
            </div>
            
            <!-- Filter & User Info -->
            <div class="flex items-center space-x-4">
                <!-- Filter Periode -->
                <div class="flex items-center space-x-4">
                    <select id="period" onchange="applyFilter()" class="rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                        <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
                        <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Bulan Ini</option>
                        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Tahun Ini</option>
                        <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Kustom</option>
                    </select>
                    
                    <div id="customDateRange" class="<?php echo $period !== 'custom' ? 'hidden' : ''; ?> space-x-2">
                        <input type="date" id="date_start" value="<?php echo $date_start; ?>" 
                               class="rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <input type="date" id="date_end" value="<?php echo $date_end; ?>"
                               class="rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <button onclick="applyFilter()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary">
                            <i class="fas fa-filter mr-2"></i>Terapkan
                        </button>
                    </div>
                </div>

                <!-- Admin Info & Logout -->
                <div class="flex items-center space-x-4 border-l border-gray-200 pl-4">
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($current_admin['name']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo ucfirst($current_admin['role']); ?></p>
                    </div>
                    <a href="logout.php" class="text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50">
                        <i class="fas fa-sign-out-alt text-xl"></i>
                    </a>
                </div>
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Today's Orders -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Pesanan Hari Ini</p>
                        <h3 class="text-2xl font-bold text-gray-900"><?php echo number_format($today_stats['total_orders']); ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
                        <i class="fas fa-shopping-cart text-primary text-xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Today's Revenue -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Pendapatan Hari Ini</p>
                        <h3 class="text-2xl font-bold text-gray-900"><?php echo formatRupiah($today_stats['total_revenue']); ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-money-bill-wave text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Total Products -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Produk</p>
                        <h3 class="text-2xl font-bold text-gray-900"><?php echo number_format($total_products); ?></h3>
                        <?php if ($low_stock > 0): ?>
                        <p class="text-xs text-red-500"><?php echo $low_stock; ?> stok menipis</p>
                        <?php endif; ?>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-box text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Pending Orders -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Pesanan Menunggu</p>
                        <h3 class="text-2xl font-bold text-gray-900"><?php echo number_format($pending_orders); ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Sales Trend -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Tren Penjualan (7 Hari Terakhir)</h3>
                <div class="relative h-80">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            
            <!-- Sales by Area -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Penjualan per Area</h3>
                <div class="relative h-80">
                    <canvas id="areaChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tables -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Orders -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Pesanan Terbaru</h3>
                        <a href="orders.php" class="text-primary hover:text-secondary text-sm font-medium">
                            Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pelanggan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($recent_orders)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-inbox text-3xl mb-2"></i>
                                    <p>Belum ada pesanan</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo $order['order_number']; ?></div>
                                    <div class="text-sm text-gray-500"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></div>
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
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Products -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Produk Terlaris</h3>
                        <a href="products.php" class="text-primary hover:text-secondary text-sm font-medium">
                            Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
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
                            <?php if (empty($top_products)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-box-open text-3xl mb-2"></i>
                                    <p>Belum ada data penjualan</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($top_products as $product): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo $product['sku']; ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900"><?php echo number_format($product['total_sold']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo formatRupiah($product['total_revenue']); ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart.js configuration untuk responsive charts
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;

        // Sales Trend Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'Jumlah Pesanan',
                    data: <?php echo json_encode($orders); ?>,
                    borderColor: '#FF6B35',
                    backgroundColor: 'rgba(255, 107, 53, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointBackgroundColor: '#FF6B35',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }, {
                    label: 'Pendapatan (Rp)',
                    data: <?php echo json_encode($revenue); ?>,
                    borderColor: '#F7931E',
                    backgroundColor: 'rgba(247, 147, 30, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointBackgroundColor: '#F7931E',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#FF6B35',
                        borderWidth: 1
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6B7280'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Jumlah Pesanan',
                            color: '#374151',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(107, 114, 128, 0.1)'
                        },
                        ticks: {
                            color: '#6B7280',
                            beginAtZero: true
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Pendapatan (Rp)',
                            color: '#374151',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            color: '#6B7280',
                            beginAtZero: true,
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });

        // Sales by Area Chart
        const areaCtx = document.getElementById('areaChart').getContext('2d');
        const areaChart = new Chart(areaCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($sales_by_area, 'area_name')); ?>,
                datasets: [{
                    label: 'Pendapatan per Area',
                    data: <?php echo json_encode(array_column($sales_by_area, 'total_revenue')); ?>,
                    backgroundColor: [
                        '#FF6B35',
                        '#F7931E', 
                        '#FFD23F',
                        '#4F46E5',
                        '#059669'
                    ],
                    borderColor: [
                        '#FF6B35',
                        '#F7931E',
                        '#FFD23F', 
                        '#4F46E5',
                        '#059669'
                    ],
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
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
                        borderColor: '#FF6B35',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return 'Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        display: true,
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6B7280',
                            maxRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Pendapatan (Rp)',
                            color: '#374151',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
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

        // Resize charts when window is resized
        window.addEventListener('resize', function() {
            salesChart.resize();
            areaChart.resize();
        });

        // Filter handling
        document.getElementById('period').addEventListener('change', function() {
            const customDateRange = document.getElementById('customDateRange');
            if (this.value === 'custom') {
                customDateRange.classList.remove('hidden');
            } else {
                customDateRange.classList.add('hidden');
                applyFilter();
            }
        });

        function applyFilter() {
            const period = document.getElementById('period').value;
            let url = 'dashboard.php?period=' + period;
            
            if (period === 'custom') {
                const dateStart = document.getElementById('date_start').value;
                const dateEnd = document.getElementById('date_end').value;
                if (dateStart && dateEnd) {
                    url += '&date_start=' + dateStart + '&date_end=' + dateEnd;
                } else {
                    alert('Silakan pilih tanggal mulai dan akhir');
                    return;
                }
            }
            
            window.location.href = url;
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