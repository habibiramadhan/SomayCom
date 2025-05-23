<?php
require_once 'auth_check.php';
hasAccess();

// Ambil data pesanan dengan pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

try {
    $pdo = getDB();
    
    // Hitung total pesanan
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $total_orders = $stmt->fetchColumn();
    $total_pages = ceil($total_orders / $limit);

    // Ambil data pesanan
    $stmt = $pdo->prepare("
        SELECT o.*, sa.area_name, sa.shipping_cost
        FROM orders o
        LEFT JOIN shipping_areas sa ON o.shipping_area_id = sa.id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    $orders = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
    $total_pages = 1;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - <?php echo SITE_NAME; ?></title>
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
    <style>
        .modal {
            transition: opacity 0.25s ease;
        }
        .modal.active {
            opacity: 1;
            pointer-events: auto;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 bg-dark text-white">
        <div class="flex items-center justify-center h-16 border-b border-gray-700">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                    <i class="fas fa-utensils text-white"></i>
                </div>
                <span class="text-xl font-bold"><?php echo SITE_NAME; ?></span>
            </div>
        </div>
        
        <nav class="mt-6">
            <a href="dashboard.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white">
                <i class="fas fa-home w-6"></i>
                <span>Dashboard</span>
            </a>
            <a href="products.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white">
                <i class="fas fa-box w-6"></i>
                <span>Produk</span>
            </a>
            <a href="orders.php" class="flex items-center px-6 py-3 text-white bg-primary/20 border-l-4 border-primary">
                <i class="fas fa-shopping-cart w-6"></i>
                <span>Pesanan</span>
            </a>
            <a href="categories.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white">
                <i class="fas fa-tags w-6"></i>
                <span>Kategori</span>
            </a>
            <a href="customers.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white">
                <i class="fas fa-users w-6"></i>
                <span>Pelanggan</span>
            </a>
            <a href="settings.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white">
                <i class="fas fa-cog w-6"></i>
                <span>Pengaturan</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-dark">Kelola Pesanan</h1>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Pesanan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pelanggan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pembayaran</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['order_number']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo formatRupiah($order['total_amount']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php
                                    switch($order['order_status']) {
                                        case 'pending':
                                            echo 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'confirmed':
                                            echo 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'processing':
                                            echo 'bg-purple-100 text-purple-800';
                                            break;
                                        case 'shipped':
                                            echo 'bg-indigo-100 text-indigo-800';
                                            break;
                                        case 'delivered':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        case 'cancelled':
                                            echo 'bg-red-100 text-red-800';
                                            break;
                                        default:
                                            echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php
                                    switch($order['order_status']) {
                                        case 'pending':
                                            echo 'Menunggu';
                                            break;
                                        case 'confirmed':
                                            echo 'Dikonfirmasi';
                                            break;
                                        case 'processing':
                                            echo 'Diproses';
                                            break;
                                        case 'shipped':
                                            echo 'Dikirim';
                                            break;
                                        case 'delivered':
                                            echo 'Selesai';
                                            break;
                                        case 'cancelled':
                                            echo 'Dibatalkan';
                                            break;
                                        default:
                                            echo ucfirst($order['order_status']);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo $order['payment_status'] === 'paid' ? 'Lunas' : 'Menunggu'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="viewOrder(<?php echo $order['id']; ?>)" 
                                        class="text-primary hover:text-secondary mr-3">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="updateStatus(<?php echo $order['id']; ?>)" 
                                        class="text-primary hover:text-secondary mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($order['order_status'] === 'pending'): ?>
                                <button onclick="deleteOrder(<?php echo $order['id']; ?>)" 
                                        class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                    </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                            <span class="font-medium"><?php echo min($offset + $limit, $total_orders); ?></span> of 
                            <span class="font-medium"><?php echo $total_orders; ?></span> results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Previous</span>
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium 
                                      <?php echo $i === $page ? 'text-primary bg-primary/10' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Next</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Order Modal -->
    <div id="viewOrderModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-3/4 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Detail Pesanan</h3>
                <div id="orderDetails" class="space-y-4">
                    <!-- Order details will be loaded here -->
                </div>
                <div class="flex justify-end mt-5">
                    <button onclick="closeViewModal()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateStatusModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Update Status Pesanan</h3>
                <form id="updateStatusForm" class="space-y-4">
                    <input type="hidden" id="orderId" name="id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status Pesanan</label>
                        <select id="orderStatus" name="order_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                            <option value="pending">Menunggu</option>
                            <option value="confirmed">Dikonfirmasi</option>
                            <option value="processing">Diproses</option>
                            <option value="shipped">Dikirim</option>
                            <option value="delivered">Selesai</option>
                            <option value="cancelled">Dibatalkan</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status Pembayaran</label>
                        <select id="paymentStatus" name="payment_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                            <option value="pending">Menunggu</option>
                            <option value="paid">Lunas</option>
                            <option value="failed">Gagal</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Catatan Admin</label>
                        <textarea id="adminNotes" name="admin_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 mt-5">
                        <button type="button" onclick="closeUpdateModal()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                            Batal
                        </button>
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // View Order Modal functions
        function viewOrder(id) {
            fetch(`api/get_order.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const order = data.order;
                        const items = data.items;
                        
                        let itemsHtml = '';
                        items.forEach(item => {
                            itemsHtml += `
                                <div class="flex items-center justify-between py-2 border-b">
                                    <div class="flex items-center">
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">${item.product_name}</div>
                                            <div class="text-sm text-gray-500">${item.product_sku}</div>
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-900">
                                        ${item.quantity} x ${formatRupiah(item.price)}
                                    </div>
                                    <div class="text-sm font-medium text-gray-900">
                                        ${formatRupiah(item.subtotal)}
                                    </div>
                                </div>
                            `;
                        });

                        document.getElementById('orderDetails').innerHTML = `
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <h4 class="font-medium text-gray-900">Informasi Pesanan</h4>
                                    <p class="text-sm text-gray-500">No. Pesanan: ${order.order_number}</p>
                                    <p class="text-sm text-gray-500">Tanggal: ${formatDate(order.created_at)}</p>
                                    <p class="text-sm text-gray-500">Status: ${getStatusText(order.order_status)}</p>
                                    <p class="text-sm text-gray-500">Pembayaran: ${order.payment_status === 'paid' ? 'Lunas' : 'Menunggu'}</p>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Informasi Pelanggan</h4>
                                    <p class="text-sm text-gray-500">Nama: ${order.customer_name}</p>
                                    <p class="text-sm text-gray-500">Telepon: ${order.customer_phone}</p>
                                    <p class="text-sm text-gray-500">Email: ${order.customer_email || '-'}</p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h4 class="font-medium text-gray-900">Alamat Pengiriman</h4>
                                <p class="text-sm text-gray-500">${order.shipping_address}</p>
                                <p class="text-sm text-gray-500">Area: ${order.area_name}</p>
                            </div>
                            <div class="mt-4">
                                <h4 class="font-medium text-gray-900">Detail Produk</h4>
                                <div class="mt-2">
                                    ${itemsHtml}
                                </div>
                            </div>
                            <div class="mt-4 border-t pt-4">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-500">Subtotal</span>
                                    <span class="text-sm font-medium text-gray-900">${formatRupiah(order.subtotal)}</span>
                                </div>
                                <div class="flex justify-between mt-2">
                                    <span class="text-sm text-gray-500">Ongkos Kirim</span>
                                    <span class="text-sm font-medium text-gray-900">${formatRupiah(order.shipping_cost)}</span>
                                </div>
                                <div class="flex justify-between mt-2">
                                    <span class="text-sm font-medium text-gray-900">Total</span>
                                    <span class="text-sm font-medium text-gray-900">${formatRupiah(order.total_amount)}</span>
                                </div>
                            </div>
                        `;
                        
                        document.getElementById('viewOrderModal').classList.remove('hidden');
                    } else {
                        alert('Gagal memuat detail pesanan: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memuat detail pesanan');
                });
        }

        function closeViewModal() {
            document.getElementById('viewOrderModal').classList.add('hidden');
        }

        // Update Status Modal functions
        function updateStatus(id) {
            fetch(`api/get_order.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const order = data.order;
                        document.getElementById('orderId').value = order.id;
                        document.getElementById('orderStatus').value = order.order_status;
                        document.getElementById('paymentStatus').value = order.payment_status;
                        document.getElementById('adminNotes').value = order.admin_notes || '';
                        
                        document.getElementById('updateStatusModal').classList.remove('hidden');
                    } else {
                        alert('Gagal memuat data pesanan: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memuat data pesanan');
                });
        }

        function closeUpdateModal() {
            document.getElementById('updateStatusModal').classList.add('hidden');
        }

        function deleteOrder(id) {
            if (confirm('Apakah Anda yakin ingin menghapus pesanan ini?')) {
                fetch('api/delete_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Gagal menghapus pesanan: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat menghapus pesanan');
                });
            }
        }

        // Helper functions
        function formatRupiah(amount) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR'
            }).format(amount);
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function getStatusText(status) {
            const statusMap = {
                'pending': 'Menunggu',
                'confirmed': 'Dikonfirmasi',
                'processing': 'Diproses',
                'shipped': 'Dikirim',
                'delivered': 'Selesai',
                'cancelled': 'Dibatalkan'
            };
            return statusMap[status] || status;
        }

        // Form submission
        document.getElementById('updateStatusForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            fetch('api/update_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Gagal memperbarui status pesanan: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memperbarui status pesanan');
            });
        });
    </script>
</body>
</html> 