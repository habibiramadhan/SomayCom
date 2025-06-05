<?php
// admin/stock_movements/export.php
// Get filters from GET parameters (same as index)
$filters = [
    'search' => $_GET['search'] ?? '',
    'product_id' => $_GET['product_id'] ?? '',
    'movement_type' => $_GET['movement_type'] ?? '',
    'reference_type' => $_GET['reference_type'] ?? '',
    'date_start' => $_GET['date_start'] ?? '',
    'date_end' => $_GET['date_end'] ?? '',
    'admin_id' => $_GET['admin_id'] ?? ''
];

try {
    $pdo = getDB();
    
    // Build WHERE clause (same as index)
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR sm.notes LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($filters['product_id'])) {
        $where .= " AND sm.product_id = ?";
        $params[] = $filters['product_id'];
    }
    
    if (!empty($filters['movement_type'])) {
        $where .= " AND sm.movement_type = ?";
        $params[] = $filters['movement_type'];
    }
    
    if (!empty($filters['reference_type'])) {
        $where .= " AND sm.reference_type = ?";
        $params[] = $filters['reference_type'];
    }
    
    if (!empty($filters['date_start'])) {
        $where .= " AND DATE(sm.created_at) >= ?";
        $params[] = $filters['date_start'];
    }
    
    if (!empty($filters['date_end'])) {
        $where .= " AND DATE(sm.created_at) <= ?";
        $params[] = $filters['date_end'];
    }
    
    if (!empty($filters['admin_id'])) {
        $where .= " AND sm.admin_id = ?";
        $params[] = $filters['admin_id'];
    }
    
    // Get all stock movements for export
    $sql = "
        SELECT sm.*, 
               p.name as product_name, p.sku as product_sku,
               a.full_name as admin_name,
               o.order_number
        FROM stock_movements sm
        LEFT JOIN products p ON sm.product_id = p.id
        LEFT JOIN admins a ON sm.admin_id = a.id
        LEFT JOIN orders o ON sm.reference_id = o.id AND sm.reference_type = 'sale'
        $where
        ORDER BY sm.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movements = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error exporting stock movements: " . $e->getMessage());
    redirectWithMessage('stock_movements.php', 'Gagal mengekspor data: ' . $e->getMessage(), 'error');
}

// Set headers for Excel download
$filename = 'stock_movements_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Create file handle
$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
$headers = [
    'Tanggal',
    'Waktu', 
    'Produk',
    'SKU',
    'Jenis Pergerakan',
    'Kuantitas',
    'Stok Sebelumnya',
    'Stok Sesudah',
    'Jenis Referensi',
    'Nomor Referensi',
    'Admin',
    'Catatan'
];
fputcsv($output, $headers);

// Add data
foreach ($movements as $movement) {
    $row = [
        formatDate($movement['created_at'], 'd/m/Y'),
        formatDate($movement['created_at'], 'H:i:s'),
        $movement['product_name'] ?? 'Produk Dihapus',
        $movement['product_sku'] ?? '-',
        getMovementTypeTextForExport($movement['movement_type']),
        $movement['quantity'],
        $movement['previous_stock'],
        $movement['current_stock'],
        getReferenceTextForExport($movement['reference_type']),
        $movement['order_number'] ?? $movement['reference_id'] ?? '-',
        $movement['admin_name'] ?? 'System',
        $movement['notes'] ?? '-'
    ];
    fputcsv($output, $row);
}

fclose($output);
exit;

function getMovementTypeTextForExport($type) {
    $texts = [
        'in' => 'Masuk',
        'out' => 'Keluar', 
        'adjustment' => 'Penyesuaian'
    ];
    return $texts[$type] ?? ucfirst($type);
}

function getReferenceTextForExport($type) {
    $texts = [
        'purchase' => 'Pembelian',
        'sale' => 'Penjualan',
        'adjustment' => 'Penyesuaian',
        'return' => 'Retur'
    ];
    return $texts[$type] ?? ucfirst($type);
}