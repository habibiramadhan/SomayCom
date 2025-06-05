<?php
// admin/shipping_area/create.php
// Set page title
$page_title = 'Tambah Area Pengiriman';

// Get form data and errors from session (if any)
$formData = $_SESSION['form_data'] ?? [];
$formErrors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);

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
        <div class="flex items-center mb-8">
            <a href="shipping_area.php" class="text-gray-400 hover:text-gray-600 mr-4">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Tambah Area Pengiriman</h1>
                <p class="text-gray-600">Tambahkan area baru untuk pengiriman</p>
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

        <!-- Form Errors -->
        <?php if (!empty($formErrors)): ?>
        <div class="mb-6 p-4 rounded-lg bg-red-100 border border-red-400 text-red-700">
            <div class="flex items-center mb-2">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span class="font-semibold">Terdapat kesalahan:</span>
            </div>
            <ul class="list-disc list-inside text-sm">
                <?php foreach ($formErrors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="max-w-2xl">
            <form method="POST" action="shipping_area.php?action=process" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="process_action" value="create">
                
                <!-- Main Information -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Area</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Area *</label>
                            <input type="text" name="area_name" value="<?php echo htmlspecialchars($formData['area_name'] ?? ''); ?>" 
                                   required maxlength="100"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                   placeholder="Contoh: Serpong, BSD City, Pondok Aren">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Kode Pos</label>
                            <input type="text" name="postal_code" value="<?php echo htmlspecialchars($formData['postal_code'] ?? ''); ?>" 
                                   maxlength="10"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                   placeholder="Contoh: 15310">
                        </div>
                    </div>
                </div>

                <!-- Shipping Information -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pengiriman</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Ongkos Kirim (Rp) *</label>
                            <input type="number" name="shipping_cost" value="<?php echo htmlspecialchars($formData['shipping_cost'] ?? ''); ?>" 
                                   required min="0" step="500"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                   placeholder="10000">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Estimasi Pengiriman</label>
                            <input type="text" name="estimated_delivery" value="<?php echo htmlspecialchars($formData['estimated_delivery'] ?? ''); ?>" 
                                   maxlength="50"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                   placeholder="Contoh: 1-2 jam, 2-3 hari kerja">
                        </div>
                    </div>
                </div>

                <!-- Settings -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Pengaturan</h3>
                    
                    <div class="space-y-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php echo ($formData['is_active'] ?? '1') ? 'checked' : ''; ?>
                                   class="rounded border-gray-300 text-primary focus:border-primary focus:ring-primary">
                            <span class="ml-2 text-sm font-medium text-gray-700">Area Aktif</span>
                        </label>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-4">
                    <button type="submit" class="bg-primary text-white px-6 py-3 rounded-lg font-semibold hover:bg-secondary transition-colors">
                        <i class="fas fa-save mr-2"></i>Simpan Area
                    </button>
                    <a href="shipping_area.php" class="bg-gray-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-600 transition-colors">
                        <i class="fas fa-times mr-2"></i>Batal
                    </a>
                </div>
            </form>
        </div>

        <!-- Help Panel -->
        <div class="mt-8 max-w-2xl">
            <div class="bg-blue-50 rounded-xl p-6">
                <h4 class="text-lg font-semibold text-blue-900 mb-3">
                    <i class="fas fa-info-circle mr-2"></i>Tips Area Pengiriman
                </h4>
                <ul class="text-sm text-blue-800 space-y-2">
                    <li>• Gunakan nama area yang jelas dan mudah dipahami pelanggan</li>
                    <li>• Sesuaikan ongkos kirim dengan jarak dan kondisi akses area</li>
                    <li>• Berikan estimasi yang realistis untuk menjaga kepercayaan pelanggan</li>
                    <li>• Kode pos membantu pelanggan memilih area yang tepat</li>
                    <li>• Area nonaktif tidak akan muncul di pilihan checkout</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const areaName = document.querySelector('input[name="area_name"]').value.trim();
            const shippingCost = document.querySelector('input[name="shipping_cost"]').value;
            
            if (!areaName) {
                alert('Nama area harus diisi');
                e.preventDefault();
                return;
            }
            
            if (!shippingCost || shippingCost < 0) {
                alert('Ongkos kirim harus diisi dan tidak boleh negatif');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>