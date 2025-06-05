<!-- admin/orders/view.php -->
<?php
// Set page title
$page_title = 'Detail Pesanan';

// Get order ID
$orderId = (int)$id;

try {
    $pdo = getDB();
    
    // Get order data
    $stmt = $pdo->prepare("
        SELECT o.*, sa.area_name, sa.estimated_delivery
        FROM orders o
        LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        redirectWithMessage('orders.php', 'Pesanan tidak ditemukan', 'error');
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as current_product_name, p.id as current_product_id,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ");
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll();
    
    // Get order history/logs (if needed)
    $stmt = $pdo->prepare("
        SELECT 
            'status_change' as type,
            order_status as new_value,
            updated_at as created_at,
            'System' as admin_name
        FROM orders 
        WHERE id = ? AND updated_at != created_at
        ORDER BY updated_at DESC
        LIMIT 10
    ");
    $stmt->execute([$orderId]);
    $orderHistory = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error loading order view: " . $e->getMessage());
    redirectWithMessage('orders.php', 'Terjadi kesalahan saat memuat data pesanan', 'error');
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
                <a href="orders.php" class="text-gray-400 hover:text-gray-600 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Detail Pesanan</h1>
                    <p class="text-gray-600"><?php echo htmlspecialchars($order['order_number']); ?></p>
                </div>
            </div>
            
            <div class="flex items-center space-x-3">
                <a href="orders.php?action=print&id=<?php echo $order['id']; ?>" target="_blank" 
                   class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fas fa-print mr-2"></i>Print
                </a>
                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $order['customer_phone']); ?>?text=Halo,%20mengenai%20pesanan%20<?php echo $order['order_number']; ?>" 
                   target="_blank" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors">
                    <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                </a>
                <?php if (hasPermission('manage_orders') && $order['order_status'] !== 'cancelled'): ?>
                <button onclick="openStatusModal(<?php echo $order['id']; ?>, '<?php echo $order['order_status']; ?>')" 
                        class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition-colors">
                    <i class="fas fa-edit mr-2"></i>Update Status
                </button>
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
                <!-- Order Items -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Item Pesanan</h3>
                    <div class="space-y-4">
                        <?php foreach ($orderItems as $item): ?>
                        <div class="flex items-center space-x-4 p-4 border rounded-lg">
                            <div class="w-16 h-16 flex-shrink-0">
                                <?php if (!empty($item['product_image'])): ?>
                                <img src="../uploads/products/<?php echo htmlspecialchars($item['product_image']); ?>" 
                                     class="w-16 h-16 rounded-lg object-cover" 
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                     onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-16 h-16 rounded-lg bg-gray-200 flex items-center justify-center\'><i class=\'fas fa-image text-gray-400\'></i></div>';">
                                <?php else: ?>
                                <div class="w-16 h-16 rounded-lg bg-gray-200 flex items-center justify-center">
                                    <i class="fas fa-image text-gray-400"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                <p class="text-sm text-gray-500">SKU: <?php echo htmlspecialchars($item['product_sku']); ?></p>
                                <?php if ($item['current_product_id'] && $item['current_product_name'] !== $item['product_name']): ?>
                                <p class="text-xs text-blue-600">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Nama saat ini: <?php echo htmlspecialchars($item['current_product_name']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-gray-900"><?php echo formatRupiah($item['price']); ?></p>
                                <p class="text-sm text-gray-500">x <?php echo number_format($item['quantity']); ?></p>
                                <p class="text-sm font-medium text-primary"><?php echo formatRupiah($item['subtotal']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Order Total -->
                    <div class="border-t pt-4 mt-4">
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Subtotal:</span>
                                <span class="font-medium"><?php echo formatRupiah($order['subtotal']); ?></span>
                            </div>
                            <?php if ($order['shipping_cost'] > 0): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Ongkos Kirim:</span>
                                <span class="font-medium"><?php echo formatRupiah($order['shipping_cost']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="flex justify-between text-lg font-bold border-t pt-2">
                                <span>Total:</span>
                                <span class="text-primary"><?php echo formatRupiah($order['total_amount']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shipping Information -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pengiriman</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Alamat Pengiriman</h4>
                            <div class="text-gray-600">
                                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Detail Pengiriman</h4>
                            <div class="space-y-1 text-sm">
                                <div><span class="text-gray-500">Area:</span> <?php echo htmlspecialchars($order['area_name'] ?? 'Tidak diketahui'); ?></div>
                                <div><span class="text-gray-500">Ongkir:</span> <?php echo formatRupiah($order['shipping_cost']); ?></div>
                                <?php if ($order['estimated_delivery']): ?>
                                <div><span class="text-gray-500">Estimasi:</span> <?php echo htmlspecialchars($order['estimated_delivery']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($order['notes']): ?>
                    <div class="mt-4 pt-4 border-t">
                        <h4 class="font-medium text-gray-900 mb-2">Catatan Pelanggan</h4>
                        <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($order['admin_notes']): ?>
                    <div class="mt-4 pt-4 border-t">
                        <h4 class="font-medium text-gray-900 mb-2">Catatan Admin</h4>
                        <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($order['admin_notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Payment Proof -->
                <?php if ($order['payment_method'] === 'transfer' && $order['payment_proof']): ?>
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Bukti Pembayaran</h3>
                    <div class="text-center">
                        <img src="../uploads/payments/<?php echo htmlspecialchars($order['payment_proof']); ?>" 
                             class="max-w-full h-auto rounded-lg border cursor-pointer hover:opacity-75 transition-opacity"
                             onclick="openImageModal('<?php echo htmlspecialchars($order['payment_proof']); ?>')"
                             onerror="this.onerror=null; this.style.display='none'; this.parentElement.innerHTML='<div class=\'bg-gray-200 rounded-lg p-8 text-gray-500\'><i class=\'fas fa-image text-4xl mb-2\'></i><p>Bukti pembayaran tidak dapat dimuat</p></div>';">
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Order Status -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Status Pesanan</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Status Pesanan:</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getOrderStatusColor($order['order_status']); ?>">
                                <?php echo getOrderStatusText($order['order_status']); ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Status Pembayaran:</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getPaymentStatusColor($order['payment_status']); ?>">
                                <?php echo getPaymentStatusText($order['payment_status']); ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Metode Pembayaran:</span>
                            <span class="font-medium"><?php echo $order['payment_method'] === 'cod' ? 'COD' : 'Transfer'; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pelanggan</h3>
                    <div class="space-y-3">
                        <div>
                            <span class="text-sm text-gray-500">Nama:</span>
                            <p class="font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">Telepon:</span>
                            <p class="font-medium"><?php echo htmlspecialchars($order['customer_phone']); ?></p>
                            <a href="tel:<?php echo htmlspecialchars($order['customer_phone']); ?>" 
                               class="text-xs text-blue-600 hover:underline">
                                <i class="fas fa-phone mr-1"></i>Hubungi
                            </a>
                        </div>
                        <?php if ($order['customer_email']): ?>
                        <div>
                            <span class="text-sm text-gray-500">Email:</span>
                            <p class="font-medium"><?php echo htmlspecialchars($order['customer_email']); ?></p>
                            <a href="mailto:<?php echo htmlspecialchars($order['customer_email']); ?>" 
                               class="text-xs text-blue-600 hover:underline">
                                <i class="fas fa-envelope mr-1"></i>Kirim Email
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Timeline -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Timeline Pesanan</h3>
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                            <div class="flex-1">
                                <p class="text-sm font-medium">Pesanan Dibuat</p>
                                <p class="text-xs text-gray-500"><?php echo formatDate($order['created_at']); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($order['confirmed_at']): ?>
                        <div class="flex items-center space-x-3">
                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                            <div class="flex-1">
                                <p class="text-sm font-medium">Pesanan Dikonfirmasi</p>
                                <p class="text-xs text-gray-500"><?php echo formatDate($order['confirmed_at']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($order['shipped_at']): ?>
                        <div class="flex items-center space-x-3">
                            <div class="w-3 h-3 bg-purple-500 rounded-full"></div>
                            <div class="flex-1">
                                <p class="text-sm font-medium">Pesanan Dikirim</p>
                                <p class="text-xs text-gray-500"><?php echo formatDate($order['shipped_at']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($order['delivered_at']): ?>
                        <div class="flex items-center space-x-3">
                            <div class="w-3 h-3 bg-green-600 rounded-full"></div>
                            <div class="flex-1">
                                <p class="text-sm font-medium">Pesanan Selesai</p>
                                <p class="text-xs text-gray-500"><?php echo formatDate($order['delivered_at']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Meta -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pesanan</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Nomor Pesanan:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($order['order_number']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Tanggal Dibuat:</span>
                            <span class="font-medium"><?php echo formatDate($order['created_at']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Terakhir Diupdate:</span>
                            <span class="font-medium"><?php echo formatDate($order['updated_at']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <?php if (hasPermission('manage_orders')): ?>
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Aksi Cepat</h3>
                    <div class="space-y-3">
                        <?php if ($order['order_status'] === 'pending'): ?>
                        <button onclick="quickStatusUpdate(<?php echo $order['id']; ?>, 'confirmed')" 
                                class="w-full bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600 transition-colors">
                            <i class="fas fa-check mr-2"></i>Konfirmasi Pesanan
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($order['order_status'] === 'confirmed'): ?>
                        <button onclick="quickStatusUpdate(<?php echo $order['id']; ?>, 'processing')" 
                                class="w-full bg-purple-500 text-white py-2 px-4 rounded-lg hover:bg-purple-600 transition-colors">
                            <i class="fas fa-cog mr-2"></i>Mulai Proses
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($order['order_status'] === 'processing'): ?>
                        <button onclick="quickStatusUpdate(<?php echo $order['id']; ?>, 'shipped')" 
                                class="w-full bg-indigo-500 text-white py-2 px-4 rounded-lg hover:bg-indigo-600 transition-colors">
                            <i class="fas fa-truck mr-2"></i>Kirim Pesanan
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($order['order_status'] === 'shipped'): ?>
                        <button onclick="quickStatusUpdate(<?php echo $order['id']; ?>, 'delivered')" 
                                class="w-full bg-green-500 text-white py-2 px-4 rounded-lg hover:bg-green-600 transition-colors">
                            <i class="fas fa-check-circle mr-2"></i>Tandai Selesai
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($order['order_status'] !== 'cancelled' && $order['order_status'] !== 'delivered'): ?>
                        <button onclick="quickStatusUpdate(<?php echo $order['id']; ?>, 'cancelled')" 
                                class="w-full bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600 transition-colors">
                            <i class="fas fa-times mr-2"></i>Batalkan Pesanan
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 hidden z-50 flex items-center justify-center">
        <div class="max-w-4xl max-h-full p-4">
            <img id="modalImage" src="" class="max-w-full max-h-full object-contain">
        </div>
        <button onclick="closeImageModal()" class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300">
            <i class="fas fa-times"></i>
        </button>
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
        function openImageModal(imagePath) {
            document.getElementById('modalImage').src = '../uploads/payments/' + imagePath;
            document.getElementById('imageModal').classList.remove('hidden');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.add('hidden');
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

        function quickStatusUpdate(orderId, newStatus) {
            if (confirm(`Apakah Anda yakin ingin mengubah status pesanan menjadi "${getStatusText(newStatus)}"?`)) {
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('order_id', orderId);
                formData.append('new_status', newStatus);
                formData.append('admin_notes', `Status diubah menjadi ${getStatusText(newStatus)} oleh admin`);
                formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
                
                fetch('orders.php?action=process', {
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

        function getStatusText(status) {
            const statusTexts = {
                'pending': 'Menunggu',
                'confirmed': 'Dikonfirmasi',
                'processing': 'Diproses',
                'shipped': 'Dikirim',
                'delivered': 'Selesai',
                'cancelled': 'Dibatalkan'
            };
            return statusTexts[status] || status;
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

        // Close modals when clicking outside
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStatusModal();
            }
        });

        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
                closeStatusModal();
            }
        });
    </script>
</body>
</html>

<?php
// Helper functions (reuse from index.php)
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