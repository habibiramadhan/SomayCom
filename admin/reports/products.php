<!-- admin/reports/products.php -->
<?php
// Products Performance Report
$page_title = 'Laporan Performa Produk';

// Get filters
$period = $_GET['period'] ?? 'month';
$date_start = $_GET['date_start'] ?? date('Y-m-01');
$date_end = $_GET['date_end'] ?? date('Y-m-d');
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'total_sold';

try {
    $pdo = getDB();
    
    // Build WHERE clause
    $where = "WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.order_status NOT IN ('cancelled')";
    $params = [$date_start, $date_end];
    
    if (!empty($category)) {
        $where .= " AND p.category_id = ?";
        $params[] = $category;
    }
    
    // Get products performance
    $orderBy = "ORDER BY total_sold DESC";
    switch ($sort) {
        case 'total_revenue':
            $orderBy = "ORDER BY total_revenue DESC";
            break;
        case 'avg_price':
            $orderBy = "ORDER BY avg_selling_price DESC";
            break;
        case 'profit_margin':
            $orderBy = "ORDER BY profit_margin DESC";
            break;
        case 'name':
            $orderBy = "ORDER BY p.name ASC";
            break;
    }
    
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.price as current_price,
            c.name as category_name,
            p.stock_quantity,
            SUM(oi.quantity) as total_sold,
            SUM(oi.subtotal) as total_revenue,
            AVG(oi.price) as avg_selling_price,
            COUNT(DISTINCT o.id) as total_orders,
            ROUND(((AVG(oi.price) - p.price) / p.price) * 100, 2) as profit_margin
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        $where
        GROUP BY p.id, p.name, p.sku, p.price, c.name, p.stock_quantity
        $orderBy
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Get categories for filter
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    // Get summary statistics
    $totalProducts = count($products);
    $totalRevenue = array_sum(array_column($products, 'total_revenue'));
    $totalItemsSold = array_sum(array_column($products, 'total_sold'));
    $avgRevenuePerProduct = $totalProducts > 0 ? $totalRevenue / $totalProducts : 0;
    
} catch (Exception $e) {
    error_log("Error fetching products report: " . $e->getMessage());
    $products = [];
    $categories = [];
    $totalProducts = 0;
    $totalRevenue = 0;
    $totalItemsSold = 0;
    $avgRevenuePerProduct = 0;
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
                    <h1 class="text-3xl font-bold text-gray-900">Laporan Performa Produk</h1>
                    <p class="text-gray-600 mt-2">Analisa penjualan dan performa setiap produk</p>
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
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Produk Terjual</p>
                        <h3 class="text-3xl font-bold"><?php echo number_format($totalProducts); ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-box text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Total Pendapatan</p>
                        <h3 class="text-2xl font-bold"><?php echo formatRupiah($totalRevenue); ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-money-bill-wave text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">Total Item Terjual</p>
                        <h3 class="text-3xl font-bold"><?php echo number_format($totalItemsSold); ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-shopping-basket text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-orange-500 to-red-500 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">Rata-rata per Produk</p>
                        <h3 class="text-2xl font-bold"><?php echo formatRupiah($avgRevenuePerProduct); ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-calculator text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <input type="hidden" name="action" value="products">
                
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
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Kategori</label>
                    <select name="category" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-200">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Urutkan</label>
                    <select name="sort" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-200">
                        <option value="total_sold" <?php echo $sort === 'total_sold' ? 'selected' : ''; ?>>Paling Laris</option>
                        <option value="total_revenue" <?php echo $sort === 'total_revenue' ? 'selected' : ''; ?>>Pendapatan Tertinggi</option>
                        <option value="avg_price" <?php echo $sort === 'avg_price' ? 'selected' : ''; ?>>Harga Rata-rata</option>
                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nama A-Z</option>
                    </select>
                </div>
                
                <div class="flex space-x-2">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="reports.php?action=products" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                        <i class="fas fa-times mr-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Products Chart -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top 10 Produk Terlaris</h3>
            <div class="relative h-80">
                <canvas id="productsChart"></canvas>
            </div>
        </div>

        <!-- Products Table -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Detail Performa Produk</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Terjual</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pendapatan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-box-open text-4xl mb-4 text-gray-300"></i>
                                <p class="text-lg">Tidak ada data penjualan produk</p>
                                <p class="text-sm">Sesuaikan filter untuk melihat data yang berbeda</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($products as $index => $product): ?>
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
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($product['category_name'] ?? '-'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo number_format($product['stock_quantity']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-green-600"><?php echo number_format($product['total_sold']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo number_format($product['total_orders']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">
                                    <div>Saat ini: <?php echo formatRupiah($product['current_price']); ?></div>
                                    <div class="text-xs text-gray-500">Rata-rata: <?php echo formatRupiah($product['avg_selling_price']); ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-green-600"><?php echo formatRupiah($product['total_revenue']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <div class="flex items-center mb-1">
                                        <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                            <?php 
                                            $maxSold = !empty($products) ? max(array_column($products, 'total_sold')) : 1;
                                            $percentage = $maxSold > 0 ? ($product['total_sold'] / $maxSold) * 100 : 0;
                                            ?>
                                            <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <span class="text-xs text-gray-500"><?php echo round($percentage); ?>%</span>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo number_format($product['total_sold'] / max($product['total_orders'], 1), 1); ?> item/order
                                    </div>
                                </div>
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

        // Products Chart
        const productsCtx = document.getElementById('productsChart').getContext('2d');
        const productsData = <?php echo json_encode(array_slice($products, 0, 10)); ?>;
        
        const productsChart = new Chart(productsCtx, {
            type: 'bar',
            data: {
                labels: productsData.map(item => item.name.length > 15 ? item.name.substring(0, 15) + '...' : item.name),
                datasets: [{
                    label: 'Total Terjual',
                    data: productsData.map(item => item.total_sold),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1,
                    borderRadius: 6,
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
                            title: function(context) {
                                const index = context[0].dataIndex;
                                return productsData[index].name;
                            },
                            label: function(context) {
                                const index = context.dataIndex;
                                const product = productsData[index];
                                return [
                                    'Terjual: ' + context.parsed.y + ' item',
                                    'Pendapatan: Rp ' + parseInt(product.total_revenue).toLocaleString('id-ID'),
                                    'Orders: ' + product.total_orders + ' transaksi'
                                ];
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
                            color: '#6B7280'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>