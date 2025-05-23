<?php
require_once 'auth_check.php';
hasAccess();

// Ambil data pesan dengan pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

try {
    $pdo = getDB();
    
    // Hitung total pesan
    $stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages");
    $total_messages = $stmt->fetchColumn();
    $total_pages = ceil($total_messages / $limit);

    // Ambil data pesan
    $stmt = $pdo->prepare("
        SELECT 
            cm.*,
            a.full_name as replied_by_name
        FROM contact_messages cm
        LEFT JOIN admins a ON cm.replied_by = a.id
        ORDER BY 
            CASE 
                WHEN cm.status = 'new' THEN 1
                WHEN cm.status = 'read' THEN 2
                ELSE 3
            END,
            cm.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    $messages = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching contact messages: " . $e->getMessage());
    $messages = [];
    $total_pages = 1;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Kontak - <?php echo SITE_NAME; ?></title>
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
            <a href="orders.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white">
                <i class="fas fa-shopping-cart w-6"></i>
                <span>Pesanan</span>
            </a>
            <a href="categories.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white">
                <i class="fas fa-tags w-6"></i>
                <span>Kategori</span>
            </a>
            <a href="shipping_areas.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white">
                <i class="fas fa-truck w-6"></i>
                <span>Area Pengiriman</span>
            </a>
            <a href="customers.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white">
                <i class="fas fa-users w-6"></i>
                <span>Pelanggan</span>
            </a>
            <a href="contact_messages.php" class="flex items-center px-6 py-3 text-white bg-primary/20 border-l-4 border-primary">
                <i class="fas fa-envelope w-6"></i>
                <span>Pesan Kontak</span>
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
            <h1 class="text-2xl font-bold text-dark">Pesan Kontak</h1>
        </div>

        <!-- Messages Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kontak</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subjek</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($messages as $message): ?>
                        <tr class="<?php echo $message['status'] === 'new' ? 'bg-yellow-50' : ''; ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo getStatusColor($message['status']); ?>">
                                    <?php echo getStatusText($message['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($message['name']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    <?php if ($message['phone']): ?>
                                        <div><?php echo htmlspecialchars($message['phone']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($message['email']): ?>
                                        <div><?php echo htmlspecialchars($message['email']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($message['subject']); ?></div>
                                <div class="text-sm text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($message['message']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500"><?php echo formatDate($message['created_at']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="viewMessage(<?php echo $message['id']; ?>)" 
                                        class="text-primary hover:text-secondary">
                                    <i class="fas fa-eye"></i>
                                </button>
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
                            <span class="font-medium"><?php echo min($offset + $limit, $total_messages); ?></span> of 
                            <span class="font-medium"><?php echo $total_messages; ?></span> results
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

    <!-- Message Modal -->
    <div id="messageModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-3/4 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Detail Pesan</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="messageContent" class="space-y-4">
                    <!-- Message content will be loaded here -->
                </div>
                <div id="replyForm" class="mt-6 hidden">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Balas Pesan</h4>
                    <form id="replyMessageForm" class="space-y-4">
                        <input type="hidden" id="messageId" name="id">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Balasan</label>
                            <textarea id="adminReply" name="admin_reply" rows="4" required
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary">
                                Kirim Balasan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function closeModal() {
            document.getElementById('messageModal').classList.add('hidden');
        }

        function viewMessage(id) {
            fetch(`api/get_message.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const message = data.message;
                        const content = document.getElementById('messageContent');
                        const replyForm = document.getElementById('replyForm');
                        
                        content.innerHTML = `
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900">${message.subject}</h4>
                                        <p class="text-sm text-gray-500">Dari: ${message.name}</p>
                                        <p class="text-sm text-gray-500">Tanggal: ${formatDate(message.created_at)}</p>
                                    </div>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        ${getStatusColor(message.status)}">
                                        ${getStatusText(message.status)}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-700 whitespace-pre-wrap">${message.message}</div>
                            </div>
                            ${message.admin_reply ? `
                            <div class="bg-primary/10 p-4 rounded-lg">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="text-sm font-medium text-gray-900">Balasan Admin</h4>
                                    <p class="text-sm text-gray-500">${formatDate(message.replied_at)}</p>
                                </div>
                                <div class="text-sm text-gray-700 whitespace-pre-wrap">${message.admin_reply}</div>
                            </div>
                            ` : ''}
                        `;
                        
                        if (message.status !== 'replied') {
                            replyForm.classList.remove('hidden');
                            document.getElementById('messageId').value = message.id;
                        } else {
                            replyForm.classList.add('hidden');
                        }
                        
                        document.getElementById('messageModal').classList.remove('hidden');
                    } else {
                        alert('Gagal memuat pesan: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memuat pesan');
                });
        }

        document.getElementById('replyMessageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            fetch('api/reply_message.php', {
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
                    alert('Gagal mengirim balasan: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengirim balasan');
            });
        });

        function getStatusColor(status) {
            const colors = {
                'new': 'bg-yellow-100 text-yellow-800',
                'read': 'bg-blue-100 text-blue-800',
                'replied': 'bg-green-100 text-green-800'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        }

        function getStatusText(status) {
            const texts = {
                'new': 'Baru',
                'read': 'Dibaca',
                'replied': 'Dibalas'
            };
            return texts[status] || status;
        }

        function formatDate(dateString) {
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            return new Date(dateString).toLocaleDateString('id-ID', options);
        }
    </script>
</body>
</html>

<?php
function getStatusColor($status) {
    $colors = [
        'new' => 'bg-yellow-100 text-yellow-800',
        'read' => 'bg-blue-100 text-blue-800',
        'replied' => 'bg-green-100 text-green-800'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}

function getStatusText($status) {
    $texts = [
        'new' => 'Baru',
        'read' => 'Dibaca',
        'replied' => 'Dibalas'
    ];
    return $texts[$status] ?? $status;
}
?> 