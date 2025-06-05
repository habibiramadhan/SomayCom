<?php
// admin/stock_movements/view.php
// Set page title
$page_title = 'Detail Pergerakan Stok';

// Get movement ID
$movementId = (int)$id;

try {
    $pdo = getDB();
    
    // Get movement data
    $stmt = $pdo->prepare("
        SELECT sm.*, 
               p.name as product_name, p.sku as product_sku, p.price,
               c.name as category_name,
               a.full_name as admin_name, a.username as admin_username,
               o.order_number, o.customer_name, o.total_amount
        FROM stock_movements sm
        LEFT JOIN products p ON sm.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN admins a ON sm.admin_id = a.id
        LEFT JOIN orders o ON sm.reference_id = o.id AND sm.reference_type = 'sale'
        WHERE sm.id = ?
    ");
    $stmt->execute([$movementId]);
    $movement = $stmt->fetch();
    
    if (!$movement) {
        redirectWithMessage('stock_movements.php', 'Data pergerakan stok tidak ditemukan', 'error');
    }
    
    // Get related movements for the same product (last 10)
    $stmt = $pdo->prepare("
        SELECT sm.*, a.full_name as admin_name
        FROM stock_movements sm
        LEFT JOIN admins a ON sm.admin_id = a.id
        WHERE sm.product_id = ? AND sm.id != ?
        ORDER BY sm.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$movement['product_id'], $movementId]);
    $relatedMovements = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error loading stock movement detail: " . $e->getMessage());
    redirectWithMessage('stock_movements.php', 'Terjadi kesalahan saat memuat data', 'error');
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
                <a href="stock_movements.php" class="text-gray-400 hover:text-gray-600 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Detail Pergerakan Stok</h1>
                    <p class="text-gray-600">ID: #<?php echo $movement['id']; ?></p>
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Movement Details -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Informasi Pergerakan</h3>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo getMovementTypeColor($movement['movement_type']); ?>">
                            <i class="fas <?php echo getMovementTypeIcon($movement['movement_type']); ?> mr-1"></i>
                            <?php echo getMovementTypeText($movement['movement_type']); ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Tanggal & Waktu</h4>
                            <p class="text-gray-600"><?php echo formatDate($movement['created_at'], 'd/m/Y H:i:s'); ?></p>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Jenis Referensi</h4>
                            <p class="text-gray-600"><?php echo getReferenceText($movement['reference_type']); ?></p>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Kuantitas</h4>
                            <div class="flex items-center space-x-2">
                                <span class="text-2xl font-bold <?php echo $movement['quantity'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $movement['quantity'] > 0 ? '+' : ''; ?><?php echo number_format($movement['quantity']); ?>
                                </span>
                                <span class="text-gray-500">unit</span>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Perubahan Stok</h4>
                            <div class="flex items-center space-x-2">
                                <span class="px-3 py-1 bg-gray-100 rounded-lg text-gray-700"><?php echo number_format($movement['previous_stock']); ?></span>
                                <i class="fas fa-arrow-right text-gray-400"></i>
                                <span class="px-3 py-1 bg-primary/10 text-primary rounded-lg font-semibold"><?php echo number_format($movement['current_stock']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($movement['notes']): ?>
                    <div class="mt-6">
                        <h4 class="font-medium text-gray-900 mb-2">Catatan</h4>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($movement['notes'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Product Information -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Produk</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Nama Produk</h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($movement['product_name'] ?? 'Produk Dihapus'); ?></p>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">SKU</h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($movement['product_sku'] ?? '-'); ?></p>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Kategori</h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($movement['category_name'] ?? 'Tanpa Kategori'); ?></p>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Harga Saat Ini</h4>
                            <p class="text-gray-600"><?php echo $movement['price'] ? formatRupiah($movement['price']) : '-'; ?></p>
                        </div>
                    </div>
                    
                    <?php if ($movement['product_name']): ?>
                    <div class="mt-6">
                        <a href="../product.php?action=view&id=<?php echo $movement['product_id']; ?>" 
                           class="inline-flex items-center text-primary hover:text-secondary">
                            <i class="fas fa-external-link-alt mr-2"></i>
                            Lihat Detail Produk
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Reference Information -->
                <?php if ($movement['reference_type'] === 'sale' && $movement['order_number']): ?>
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pesanan</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Nomor Pesanan</h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($movement['order_number']); ?></p>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Pelanggan</h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($movement['customer_name'] ?? '-'); ?></p>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Total Pesanan</h4>
                            <p class="text-gray-600"><?php echo $movement['total_amount'] ? formatRupiah($movement['total_amount']) : '-'; ?></p>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <a href="../orders.php?action=view&id=<?php echo $movement['reference_id']; ?>" 
                           class="inline-flex items-center text-primary hover:text-secondary">
                            <i class="fas fa-external-link-alt mr-2"></i>
                            Lihat Detail Pesanan
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Related Movements -->
                <?php if (!empty($relatedMovements)): ?>
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Pergerakan Terkait (Produk yang Sama)</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left">Tanggal</th>
                                    <th class="px-4 py-2 text-left">Jenis</th>
                                    <th class="px-4 py-2 text-left">Kuantitas</th>
                                    <th class="px-4 py-2 text-left">Stok</th>
                                    <th class="px-4 py-2 text-left">Admin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($relatedMovements as $related): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2"><?php echo formatDate($related['created_at'], 'd/m/Y H:i'); ?></td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo getMovementTypeColor($related['movement_type']); ?>">
                                            <i class="fas <?php echo getMovementTypeIcon($related['movement_type']); ?> mr-1"></i>
                                            <?php echo getMovementTypeText($related['movement_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="font-medium <?php echo $related['quantity'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $related['quantity'] > 0 ? '+' : ''; ?><?php echo number_format($related['quantity']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2"><?php echo number_format($related['previous_stock']); ?> → <?php echo number_format($related['current_stock']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($related['admin_name'] ?? 'System'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Admin Information -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Admin</h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($movement['admin_name'] ?? 'System'); ?></p>
                                <?php if ($movement['admin_username']): ?>
                                <p class="text-sm text-gray-500">@<?php echo htmlspecialchars($movement['admin_username']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Movement Summary -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Ringkasan Pergerakan</h3>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">ID Movement</span>
                            <span class="font-medium text-gray-900">#<?php echo $movement['id']; ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Reference ID</span>
                            <span class="font-medium text-gray-900"><?php echo $movement['reference_id'] ?? '-'; ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Dibuat</span>
                            <span class="font-medium text-gray-900"><?php echo formatDate($movement['created_at'], 'd/m/Y H:i'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Aksi Cepat</h3>
                    
                    <div class="space-y-3">
                        <a href="stock_movements.php" 
                           class="w-full bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600 transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-list mr-2"></i>Kembali ke Daftar
                        </a>
                        
                        <?php if ($movement['product_name']): ?>
                        <a href="../product.php?action=view&id=<?php echo $movement['product_id']; ?>" 
                           class="w-full bg-primary text-white py-2 px-4 rounded-lg hover:bg-secondary transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-box mr-2"></i>Lihat Produk
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($movement['reference_type'] === 'sale' && $movement['reference_id']): ?>
                        <a href="../orders.php?action=view&id=<?php echo $movement['reference_id']; ?>" 
                           class="w-full bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600 transition-colors inline-flex items-center justify-center">
                            <i class="fas fa-shopping-cart mr-2"></i>Lihat Pesanan
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php
// Helper functions (same as index)
function getMovementTypeColor($type) {
    $colors = [
        'in' => 'bg-green-100 text-green-800',
        'out' => 'bg-red-100 text-red-800',
        'adjustment' => 'bg-blue-100 text-blue-800'
    ];
    return $colors[$type] ?? 'bg-gray-100 text-gray-800';
}

function getMovementTypeIcon($type) {
    $icons = [
        'in' => 'fa-arrow-up',
        'out' => 'fa-arrow-down',
        'adjustment' => 'fa-adjust'
    ];
    return $icons[$type] ?? 'fa-exchange-alt';
}

function getMovementTypeText($type) {
    $texts = [
        'in' => 'Masuk',
        'out' => 'Keluar',
        'adjustment' => 'Penyesuaian'
    ];
    return $texts[$type] ?? ucfirst($type);
}

function getReferenceText($type) {
    $texts = [
        'purchase' => 'Pembelian',
        'sale' => 'Penjualan',
        'adjustment' => 'Penyesuaian',
        'return' => 'Retur'
    ];
    return $texts[$type] ?? ucfirst($type);
}
?>