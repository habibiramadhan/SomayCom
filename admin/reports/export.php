<!-- admin/reports/export.php -->
<?php
// Export reports to Excel
require_once '../auth_check.php';
hasAccess();

if (!hasPermission('view_reports')) {
    redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
}

// Get filters
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
    
    // Get detailed sales data
    $stmt = $pdo->prepare("
        SELECT 
            o.order_number,
            o.customer_name,
            o.customer_phone,
            o.customer_email,
            sa.area_name as shipping_area,
            o.payment_method,
            o.payment_status,
            o.order_status,
            o.subtotal,
            o.shipping_cost,
            o.total_amount,
            o.created_at,
            o.confirmed_at,
            o.shipped_at,
            o.delivered_at
        FROM orders o
        LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$date_start, $date_end]);
    $orders = $stmt->fetchAll();
    
    // Get order items details
    $stmt = $pdo->prepare("
        SELECT 
            o.order_number,
            oi.product_name,
            oi.product_sku,
            oi.price,
            oi.quantity,
            oi.subtotal
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        ORDER BY o.created_at DESC, oi.id
    ");
    $stmt->execute([$date_start, $date_end]);
    $orderItems = $stmt->fetchAll();
    
    // Get products summary
    $stmt = $pdo->prepare("
        SELECT 
            p.name as product_name,
            p.sku,
            p.price as current_price,
            SUM(oi.quantity) as total_sold,
            SUM(oi.subtotal) as total_revenue,
            AVG(oi.price) as avg_selling_price
        FROM products p
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND o.order_status NOT IN ('cancelled')
        GROUP BY p.id, p.name, p.sku, p.price
        ORDER BY total_sold DESC
    ");
    $stmt->execute([$date_start, $date_end]);
    $productsSummary = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching export data: " . $e->getMessage());
    redirectWithMessage('reports.php', 'Gagal mengekspor data', 'error');
}

// Generate filename
$filename = 'laporan_penjualan_' . date('Y-m-d_H-i-s') . '.csv';

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Summary Information
fputcsv($output, ['LAPORAN PENJUALAN']);
fputcsv($output, ['Periode', $date_start . ' s/d ' . $date_end]);
fputcsv($output, ['Digenerate pada', date('Y-m-d H:i:s')]);
fputcsv($output, ['']);

// Summary statistics
$totalOrders = count($orders);
$totalRevenue = array_sum(array_column($orders, 'total_amount'));
$completedOrders = count(array_filter($orders, function($o) { return $o['order_status'] == 'delivered'; }));
$cancelledOrders = count(array_filter($orders, function($o) { return $o['order_status'] == 'cancelled'; }));
$avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

fputcsv($output, ['RINGKASAN']);
fputcsv($output, ['Total Pesanan', number_format($totalOrders)]);
fputcsv($output, ['Pesanan Selesai', number_format($completedOrders)]);
fputcsv($output, ['Pesanan Dibatalkan', number_format($cancelledOrders)]);
fputcsv($output, ['Total Pendapatan', 'Rp ' . number_format($totalRevenue, 0, ',', '.')]);
fputcsv($output, ['Rata-rata Order', 'Rp ' . number_format($avgOrderValue, 0, ',', '.')]);
fputcsv($output, ['']);

// Orders data
fputcsv($output, ['DETAIL PESANAN']);
fputcsv($output, [
    'No Order',
    'Tanggal',
    'Nama Pelanggan',
    'Telepon',
    'Email',
    'Area Pengiriman',
    'Metode Bayar',
    'Status Bayar',
    'Status Order',
    'Subtotal',
    'Ongkir',
    'Total',
    'Konfirmasi',
    'Dikirim',
    'Selesai'
]);

foreach ($orders as $order) {
    fputcsv($output, [
        $order['order_number'],
        date('Y-m-d H:i', strtotime($order['created_at'])),
        $order['customer_name'],
        $order['customer_phone'],
        $order['customer_email'] ?: '-',
        $order['shipping_area'] ?: '-',
        $order['payment_method'] == 'cod' ? 'COD' : 'Transfer',
        ucfirst($order['payment_status']),
        ucfirst($order['order_status']),
        'Rp ' . number_format($order['subtotal'], 0, ',', '.'),
        'Rp ' . number_format($order['shipping_cost'], 0, ',', '.'),
        'Rp ' . number_format($order['total_amount'], 0, ',', '.'),
        $order['confirmed_at'] ? date('Y-m-d H:i', strtotime($order['confirmed_at'])) : '-',
        $order['shipped_at'] ? date('Y-m-d H:i', strtotime($order['shipped_at'])) : '-',
        $order['delivered_at'] ? date('Y-m-d H:i', strtotime($order['delivered_at'])) : '-'
    ]);
}

fputcsv($output, ['']);

// Order items
fputcsv($output, ['DETAIL ITEM PESANAN']);
fputcsv($output, [
    'No Order',
    'Nama Produk',
    'SKU',
    'Harga Satuan',
    'Quantity',
    'Subtotal'
]);

foreach ($orderItems as $item) {
    fputcsv($output, [
        $item['order_number'],
        $item['product_name'],
        $item['product_sku'],
        'Rp ' . number_format($item['price'], 0, ',', '.'),
        number_format($item['quantity']),
        'Rp ' . number_format($item['subtotal'], 0, ',', '.')
    ]);
}

fputcsv($output, ['']);

// Products summary
fputcsv($output, ['RINGKASAN PRODUK TERLARIS']);
fputcsv($output, [
    'Nama Produk',
    'SKU',
    'Harga Saat Ini',
    'Total Terjual',
    'Harga Rata-rata Jual',
    'Total Pendapatan'
]);

foreach ($productsSummary as $product) {
    fputcsv($output, [
        $product['product_name'],
        $product['sku'],
        'Rp ' . number_format($product['current_price'], 0, ',', '.'),
        number_format($product['total_sold']),
        'Rp ' . number_format($product['avg_selling_price'], 0, ',', '.'),
        'Rp ' . number_format($product['total_revenue'], 0, ',', '.')
    ]);
}

// Close output stream
fclose($output);

// Log activity
logActivity('reports_export', "Exported sales report for period {$date_start} to {$date_end}", $_SESSION['admin_id']);

exit;