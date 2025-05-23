<?php
require_once 'auth_check.php';
hasAccess();

try {
    $pdo = getDB();
    
    // Ambil semua pengaturan
    $stmt = $pdo->query("SELECT * FROM app_settings ORDER BY setting_key");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching settings: " . $e->getMessage());
    $settings = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - <?php echo SITE_NAME; ?></title>
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
            <a href="settings.php" class="flex items-center px-6 py-3 text-white bg-primary/20 border-l-4 border-primary">
                <i class="fas fa-cog w-6"></i>
                <span>Pengaturan</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-dark">Pengaturan Aplikasi</h1>
        </div>

        <!-- Settings Form -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <form id="settingsForm" class="p-6 space-y-6">
                <?php foreach ($settings as $setting): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">
                            <?php echo htmlspecialchars($setting['description']); ?>
                        </label>
                        <p class="mt-1 text-sm text-gray-500">
                            <?php echo htmlspecialchars($setting['setting_key']); ?>
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <?php if ($setting['setting_type'] === 'boolean'): ?>
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="settings[<?php echo $setting['setting_key']; ?>]" 
                                       value="1"
                                       <?php echo $setting['setting_value'] ? 'checked' : ''; ?>
                                       class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                                <label class="ml-2 block text-sm text-gray-900">
                                    Aktif
                                </label>
                            </div>
                        <?php elseif ($setting['setting_type'] === 'number'): ?>
                            <input type="number" 
                                   name="settings[<?php echo $setting['setting_key']; ?>]" 
                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <?php else: ?>
                            <input type="text" 
                                   name="settings[<?php echo $setting['setting_key']; ?>]" 
                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="flex justify-end">
                    <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary">
                        Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = {};
            
            // Convert form data to object
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('settings[')) {
                    const settingKey = key.match(/\[(.*?)\]/)[1];
                    data[settingKey] = value;
                }
            }
            
            fetch('api/save_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Pengaturan berhasil disimpan');
                } else {
                    alert('Gagal menyimpan pengaturan: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menyimpan pengaturan');
            });
        });
    </script>
</body>
</html> 