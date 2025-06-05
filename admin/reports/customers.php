<!-- admin/reports/customers.php -->
<?php
// Customer Analysis Report
$page_title = 'Laporan Analisa Pelanggan';

// Get filters
$period = $_GET['period'] ?? 'month';
$date_start = $_GET['date_start'] ?? date('Y-m-01');
$date_end = $_GET['date_end'] ?? date('Y-m-d');
$area = $_GET['area'] ?? '';

try {
    $pdo = getDB();
    
    // Build WHERE clause
    $where = "WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.order_status NOT IN ('cancelled')";
    $params = [$date_start, $date_end];
    
    if (!empty($area)) {
        $where .= " AND o.shipping_area_id = ?";
        $params[] = $area;
    }
    
    // Get customer statistics
    $sql = "
        SELECT 
            o.customer_name,
            o.customer_phone,
            o.customer_email,
            sa.area_name,
            COUNT(o.id) as total_orders,
            SUM(o.total_amount) as total_spent,
            AVG(o.total_amount) as avg_order_value,
            MIN(o.created_at) as first_order,
            MAX(o.created_at) as last_order,
            DATEDIFF(MAX(o.created_at), MIN(o.created_at)) as customer_lifetime_days
        FROM orders o
        LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
        $where
        GROUP BY o.customer_phone, o.customer_name, o.customer_email, sa.area_name
        ORDER BY total_spent DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
    
    // Get summary statistics
    $totalCustomers = count($customers);
    $totalRevenue = array_sum(array_column($customers, 'total_spent'));
    $totalOrders = array_sum(array_column($customers, 'total_orders'));
    $avgOrdersPerCustomer = $totalCustomers > 0 ? $totalOrders / $totalCustomers : 0;
    $avgRevenuePerCustomer = $totalCustomers > 0 ? $totalRevenue / $totalCustomers : 0;
    
    // Categorize customers
    $newCustomers = count(array_filter($customers, function($c) { return $c['total_orders'] == 1; }));
    $regularCustomers = count(array_filter($customers, function($c) { return $c['total_orders'] >= 2 && $c['total_orders'] <= 5; }));
    $loyalCustomers = count(array_filter($customers, function($c) { return $c['total_orders'] > 5; }));
    
    // Get shipping areas for filter
    $stmt = $pdo->prepare("SELECT * FROM shipping_areas WHERE is_active = 1 ORDER BY area_name");
    $stmt->execute();
    $shippingAreas = $stmt->fetchAll();
    
    // Customer segmentation by spending
    $highValueCustomers = array_filter($customers, function($c) use ($avgRevenuePerCustomer) { 
        return $c['total_spent'] > ($avgRevenuePerCustomer * 2); 
    });
    $mediumValueCustomers = array_filter($customers, function($c) use ($avgRevenuePerCustomer) { 
        return $c['total_spent'] >= $avgRevenuePerCustomer && $c['total_spent'] <= ($avgRevenuePerCustomer * 2); 
    });
    $lowValueCustomers = array_filter($customers, function($c) use ($avgRevenuePerCustomer) { 
        return $c['total_spent'] < $avgRevenuePerCustomer; 
    });
    
} catch (Exception $e) {
    error_log("Error fetching customers report: " . $e->getMessage());
    $customers = [];
    $shippingAreas = [];
    $totalCustomers = 0;
    $totalRevenue = 0;
    $totalOrders = 0;
    $avgOrdersPerCustomer = 0;
    $avgRevenuePerCustomer = 0;
    $newCustomers = 0;
    $regularCustomers = 0;
    $loyalCustomers = 0;
    $highValueCustomers = [];
    $mediumValueCustomers = [];
    $lowValueCustomers = [];
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
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center">
                <a href="reports.php" class="text-gray-400 hover:text-gray-600 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Laporan Analisa Pelanggan</h1>
                    <p class="text-gray-600 mt-2">Analisa perilaku dan segmentasi pelanggan</p>
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

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Total Pelanggan</p>
                        <h3 class="text-3xl font-bold"><?php echo number_format($totalCustomers); ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Pelanggan Baru</p>
                        <h3 class="text-3xl font-bold"><?php echo number_format($newCustomers); ?></h3>
                        <p class="text-green-200 text-xs">1 pesanan</p>
                    </div>
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-plus text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">Pelanggan Reguler</p>
                        <h3 class="text-3xl font-bold"><?php echo number_format($regularCustomers); ?></h3>
                        <p class="text-purple-200 text-xs">2-5 pesanan</p>
                    </div>
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-check text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-orange-500 to-red-500 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">Pelanggan Loyal</p>
                        <h3 class="text-3xl font-bold"><?php echo number_format($loyalCustomers); ?></h3>
                        <p class="text-orange-200 text-xs">>5 pesanan</p>
                    </div>
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-crown text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-indigo-100 text-sm font-medium">Rata-rata Belanja</p>
                        <h3 class="text-2xl font-bold"><?php echo formatRupiah($avgRevenuePerCustomer); ?></h3>
                        <p class="text-indigo-200 text-xs"><?php echo number_format($avgOrdersPerCustomer, 1); ?> order/customer</p>
                    </div>
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-calculator text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <input type="hidden" name="action" value="customers">
                
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
                    <a href="reports.php?action=customers" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                        <i class="fas fa-times mr-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Customer Segmentation Chart -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Segmentasi Pelanggan</h3>
                <div class="relative h-80">
                    <canvas id="segmentationChart"></canvas>
                </div>
            </div>
            
            <!-- Value Segmentation Chart -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Segmentasi Berdasarkan Nilai</h3>
                <div class="relative h-80">
                    <canvas id="valueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Customers Table -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Top Pelanggan</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pelanggan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Area</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Belanja</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rata-rata/Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Periode Aktif</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-users text-4xl mb-4 text-gray-300"></i>
                                <p class="text-lg">Tidak ada data pelanggan</p>
                                <p class="text-sm">Sesuaikan filter untuk melihat data yang berbeda</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach (array_slice($customers, 0, 20) as $index => $customer): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-blue-600 font-bold text-sm"><?php echo $index + 1; ?></span>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($customer['customer_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo $customer['customer_phone']; ?></div>
                                        <?php if ($customer['customer_email']): ?>
                                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($customer['customer_email']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($customer['area_name'] ?? '-'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-blue-600"><?php echo number_format($customer['total_orders']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-green-600"><?php echo formatRupiah($customer['total_spent']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo formatRupiah($customer['avg_order_value']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">
                                    <?php if ($customer['customer_lifetime_days'] > 0): ?>
                                        <?php echo $customer['customer_lifetime_days']; ?> hari
                                    <?php else: ?>
                                        Baru
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo formatDate($customer['first_order'], 'd/m/Y'); ?> - <?php echo formatDate($customer['last_order'], 'd/m/Y'); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($customer['total_orders'] == 1): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Baru
                                    </span>
                                <?php elseif ($customer['total_orders'] <= 5): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Reguler
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-crown mr-1"></i>Loyal
                                    </span>
                                <?php endif; ?>
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

        // Customer Segmentation Chart
        const segmentationCtx = document.getElementById('segmentationChart').getContext('2d');
        const segmentationChart = new Chart(segmentationCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pelanggan Baru', 'Pelanggan Reguler', 'Pelanggan Loyal'],
                datasets: [{
                    data: [<?php echo $newCustomers; ?>, <?php echo $regularCustomers; ?>, <?php echo $loyalCustomers; ?>],
                    backgroundColor: [
                        '#3B82F6',
                        '#10B981', 
                        '#F59E0B'
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
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Value Segmentation Chart
        const valueCtx = document.getElementById('valueChart').getContext('2d');
        const valueChart = new Chart(valueCtx, {
            type: 'doughnut',
            data: {
                labels: ['High Value', 'Medium Value', 'Low Value'],
                datasets: [{
                    data: [<?php echo count($highValueCustomers); ?>, <?php echo count($mediumValueCustomers); ?>, <?php echo count($lowValueCustomers); ?>],
                    backgroundColor: [
                        '#EF4444',
                        '#F59E0B',
                        '#6B7280'
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
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
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