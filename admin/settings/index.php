<!-- admin/settings/index.php -->
<?php
// Set page title
$page_title = 'Pengaturan Aplikasi';

try {
    $pdo = getDB();
    
    // Get all settings
    $stmt = $pdo->prepare("SELECT * FROM app_settings WHERE is_editable = 1 ORDER BY setting_key");
    $stmt->execute();
    $settingsData = $stmt->fetchAll();
    
    // Convert to key-value pairs
    $settings = [];
    foreach ($settingsData as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Get admin info
    $current_admin = getCurrentAdmin();
    
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage());
    $settings = [];
}

// Get form data and errors from session (if any)
$formData = $_SESSION['form_data'] ?? $settings;
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
    <style>
        .settings-card {
            transition: all 0.3s ease;
        }
        .settings-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .icon-bg {
            background: linear-gradient(135deg, #FF6B35 0%, #F7931E 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                        <div class="w-10 h-10 icon-bg rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-cog text-white text-lg"></i>
                        </div>
                        Pengaturan Aplikasi
                    </h1>
                    <p class="text-gray-600 mt-2">Kelola konfigurasi dan pengaturan sistem secara terpusat</p>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="text-right text-sm text-gray-500">
                        <p>Terakhir diubah:</p>
                        <p class="font-medium"><?php echo date('d/m/Y H:i'); ?></p>
                    </div>
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

        <!-- Form Errors -->
        <?php if (!empty($formErrors)): ?>
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle text-red-500 mr-3 mt-0.5"></i>
                <div>
                    <h4 class="font-semibold text-red-800 mb-2">Terdapat kesalahan:</h4>
                    <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                        <?php foreach ($formErrors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Settings Form -->
        <form method="POST" action="settings.php?action=update" id="settingsForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <!-- Settings Cards Grid -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-8">
                
                <!-- 1. General Settings Card -->
                <div class="settings-card bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6 text-white">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                                <i class="fas fa-store text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">Pengaturan Umum</h3>
                                <p class="text-blue-100 text-sm">Informasi dasar toko dan kontak</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 space-y-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag text-blue-500 mr-2"></i>Nama Website
                            </label>
                            <input type="text" name="site_name" value="<?php echo htmlspecialchars($formData['site_name'] ?? ''); ?>" 
                                   required maxlength="100"
                                   class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                                   placeholder="Contoh: Somay Ecommerce">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-phone text-blue-500 mr-2"></i>Nomor Telepon
                                </label>
                                <input type="text" name="site_phone" value="<?php echo htmlspecialchars($formData['site_phone'] ?? ''); ?>" 
                                       required maxlength="20"
                                       class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                                       placeholder="081234567890">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-envelope text-blue-500 mr-2"></i>Email
                                </label>
                                <input type="email" name="site_email" value="<?php echo htmlspecialchars($formData['site_email'] ?? ''); ?>" 
                                       required maxlength="100"
                                       class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                                       placeholder="info@somay.com">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt text-blue-500 mr-2"></i>Alamat Lengkap
                            </label>
                            <textarea name="site_address" rows="3" maxlength="255"
                                      class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all resize-none"
                                      placeholder="Alamat lengkap toko..."><?php echo htmlspecialchars($formData['site_address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- 2. Order Settings Card -->
                <div class="settings-card bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 p-6 text-white">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                                <i class="fas fa-shopping-cart text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">Pengaturan Pesanan</h3>
                                <p class="text-green-100 text-sm">Konfigurasi order dan pembayaran</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-wallet text-green-500 mr-2"></i>Minimal Pembelian
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-500">Rp</span>
                                    <input type="number" name="min_order_amount" value="<?php echo $formData['min_order_amount'] ?? ''; ?>" 
                                           required min="0" step="500"
                                           class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-200 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-all"
                                           placeholder="25000">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-truck text-green-500 mr-2"></i>Gratis Ongkir Min
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-500">Rp</span>
                                    <input type="number" name="free_shipping_min" value="<?php echo $formData['free_shipping_min'] ?? ''; ?>" 
                                           required min="0" step="1000"
                                           class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-200 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-all"
                                           placeholder="100000">
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-hashtag text-green-500 mr-2"></i>Prefix Nomor Order
                                </label>
                                <input type="text" name="order_prefix" value="<?php echo htmlspecialchars($formData['order_prefix'] ?? ''); ?>" 
                                       required maxlength="10"
                                       class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-all"
                                       placeholder="ORD">
                                <p class="text-xs text-gray-500 mt-1">Contoh: ORD → ORD240523001</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-clock text-green-500 mr-2"></i>Kadaluarsa Order (Jam)
                                </label>
                                <input type="number" name="order_expiry_hours" value="<?php echo $formData['order_expiry_hours'] ?? ''; ?>" 
                                       required min="1" max="168"
                                       class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-all"
                                       placeholder="24">
                                <p class="text-xs text-gray-500 mt-1">Auto cancel jika belum bayar</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 3. WhatsApp Settings Card -->
                <div class="settings-card bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-green-600 to-green-700 p-6 text-white">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                                <i class="fab fa-whatsapp text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">Pengaturan WhatsApp</h3>
                                <p class="text-green-100 text-sm">Customer service dan notifikasi</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 space-y-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fab fa-whatsapp text-green-600 mr-2"></i>Nomor WhatsApp
                            </label>
                            <input type="text" name="whatsapp_number" value="<?php echo htmlspecialchars($formData['whatsapp_number'] ?? ''); ?>" 
                                   required maxlength="20" id="whatsapp_input"
                                   class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-green-600 focus:ring-2 focus:ring-green-200 transition-all"
                                   placeholder="6281234567890">
                            <p class="text-xs text-gray-500 mt-1">Format internasional tanpa tanda +</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-comment-alt text-green-600 mr-2"></i>Template Pesan Order
                            </label>
                            <textarea name="whatsapp_order_message" rows="4" maxlength="500"
                                      class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-green-600 focus:ring-2 focus:ring-green-200 transition-all resize-none"
                                      placeholder="Halo, saya ingin memesan produk dengan nomor order: {order_number}"><?php echo htmlspecialchars($formData['whatsapp_order_message'] ?? ''); ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                <span class="font-medium">Variabel:</span> 
                                <code class="bg-gray-100 px-1 rounded">{order_number}</code>, 
                                <code class="bg-gray-100 px-1 rounded">{customer_name}</code>, 
                                <code class="bg-gray-100 px-1 rounded">{total}</code>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 4. SEO Settings Card -->
                <div class="settings-card bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-6 text-white">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                                <i class="fas fa-search text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">Pengaturan SEO</h3>
                                <p class="text-purple-100 text-sm">Optimasi mesin pencari</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 space-y-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-heading text-purple-500 mr-2"></i>Meta Title
                            </label>
                            <input type="text" name="meta_title" value="<?php echo htmlspecialchars($formData['meta_title'] ?? ''); ?>" 
                                   maxlength="60" id="meta_title_input"
                                   class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                                   placeholder="Somay Ecommerce - Distributor Somay Terlengkap">
                            <p class="text-xs text-gray-500 mt-1">Optimal: 50-60 karakter | <span id="title_count">0</span>/60</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-paragraph text-purple-500 mr-2"></i>Meta Description
                            </label>
                            <textarea name="meta_description" rows="3" maxlength="160" id="meta_desc_input"
                                      class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all resize-none"
                                      placeholder="Distributor somay dan siomay terpercaya dengan pengiriman cepat se-Tangerang Selatan..."><?php echo htmlspecialchars($formData['meta_description'] ?? ''); ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Optimal: 150-160 karakter | <span id="desc_count">0</span>/160</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tags text-purple-500 mr-2"></i>Meta Keywords
                            </label>
                            <input type="text" name="meta_keywords" value="<?php echo htmlspecialchars($formData['meta_keywords'] ?? ''); ?>" 
                                   maxlength="255"
                                   class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                                   placeholder="somay, siomay, distributor, tangerang selatan">
                            <p class="text-xs text-gray-500 mt-1">Pisahkan dengan koma</p>
                        </div>
                    </div>
                </div>

                <!-- 5. Email Settings Card -->
                <div class="settings-card bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-red-500 to-red-600 p-6 text-white">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                                <i class="fas fa-envelope text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">Pengaturan Email</h3>
                                <p class="text-red-100 text-sm">Konfigurasi SMTP server</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-server text-red-500 mr-2"></i>SMTP Host
                                </label>
                                <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($formData['smtp_host'] ?? ''); ?>" 
                                       maxlength="100"
                                       class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all"
                                       placeholder="smtp.gmail.com">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-plug text-red-500 mr-2"></i>SMTP Port
                                </label>
                                <input type="number" name="smtp_port" value="<?php echo $formData['smtp_port'] ?? ''; ?>" 
                                       min="1" max="65535"
                                       class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all"
                                       placeholder="587">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user text-red-500 mr-2"></i>SMTP Username
                            </label>
                            <input type="email" name="smtp_username" value="<?php echo htmlspecialchars($formData['smtp_username'] ?? ''); ?>" 
                                   maxlength="100"
                                   class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all"
                                   placeholder="your-email@gmail.com">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-key text-red-500 mr-2"></i>SMTP Password
                            </label>
                            <div class="relative">
                                <input type="password" name="smtp_password" value="<?php echo htmlspecialchars($formData['smtp_password'] ?? ''); ?>" 
                                       maxlength="100" id="smtp_password"
                                       class="w-full px-4 py-3 pr-12 rounded-lg border border-gray-200 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all"
                                       placeholder="your-app-password">
                                <button type="button" onclick="togglePassword('smtp_password')" 
                                        class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye" id="smtp_password_icon"></i>
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ingin mengubah</p>
                        </div>
                    </div>
                </div>

                <!-- 6. Social Media Settings Card -->
                <div class="settings-card bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 p-6 text-white">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                                <i class="fas fa-share-alt text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">Media Sosial</h3>
                                <p class="text-indigo-100 text-sm">Link akun media sosial</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 space-y-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fab fa-facebook text-blue-600 mr-2"></i>Facebook URL
                            </label>
                            <input type="url" name="facebook_url" value="<?php echo htmlspecialchars($formData['facebook_url'] ?? ''); ?>" 
                                   maxlength="255"
                                   class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                                   placeholder="https://facebook.com/your-page">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fab fa-instagram text-pink-500 mr-2"></i>Instagram URL
                            </label>
                            <input type="url" name="instagram_url" value="<?php echo htmlspecialchars($formData['instagram_url'] ?? ''); ?>" 
                                   maxlength="255"
                                   class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                                   placeholder="https://instagram.com/your-account">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fab fa-twitter text-blue-400 mr-2"></i>Twitter URL
                            </label>
                            <input type="url" name="twitter_url" value="<?php echo htmlspecialchars($formData['twitter_url'] ?? ''); ?>" 
                                   maxlength="255"
                                   class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                                   placeholder="https://twitter.com/your-account">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-8">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-info-circle text-blue-500 mr-3 text-lg"></i>
                        <span class="text-sm">Perubahan akan diterapkan segera setelah disimpan</span>
                    </div>
                    <div class="flex space-x-3">
                        <button type="button" onclick="resetForm()" 
                                class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all font-medium">
                            <i class="fas fa-undo mr-2"></i>Reset Form
                        </button>
                        <button type="submit" class="px-8 py-3 bg-gradient-to-r from-primary to-secondary text-white rounded-lg hover:from-secondary hover:to-primary transition-all font-semibold shadow-lg">
                            <i class="fas fa-save mr-2"></i>Simpan Pengaturan
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Backup & Restore Section -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-gray-800 to-gray-900 p-6 text-white">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-database text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Backup & Restore</h3>
                        <p class="text-gray-300 text-sm">Kelola backup pengaturan sistem</p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Backup Settings -->
                    <div class="text-center p-6 border-2 border-dashed border-gray-200 rounded-xl hover:border-blue-300 hover:bg-blue-50 transition-all">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-download text-blue-600 text-2xl"></i>
                        </div>
                        <h4 class="font-bold text-gray-900 mb-2">Backup Pengaturan</h4>
                        <p class="text-sm text-gray-600 mb-4">Download file backup pengaturan saat ini dalam format JSON</p>
                        <a href="settings/backup_restore.php?action=backup" 
                           class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors font-medium">
                            <i class="fas fa-download mr-2"></i>Download Backup
                        </a>
                    </div>
                    
                    <!-- Restore Settings -->
                    <div class="text-center p-6 border-2 border-dashed border-gray-200 rounded-xl hover:border-green-300 hover:bg-green-50 transition-all">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-upload text-green-600 text-2xl"></i>
                        </div>
                        <h4 class="font-bold text-gray-900 mb-2">Restore Pengaturan</h4>
                        <p class="text-sm text-gray-600 mb-4">Upload file backup untuk mengembalikan pengaturan</p>
                        <button onclick="openRestoreModal()" 
                                class="inline-flex items-center px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors font-medium">
                            <i class="fas fa-upload mr-2"></i>Restore Backup
                        </button>
                    </div>
                    
                    <!-- Reset Settings -->
                    <div class="text-center p-6 border-2 border-dashed border-gray-200 rounded-xl hover:border-red-300 hover:bg-red-50 transition-all">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-undo text-red-600 text-2xl"></i>
                        </div>
                        <h4 class="font-bold text-gray-900 mb-2">Reset ke Default</h4>
                        <p class="text-sm text-gray-600 mb-4">Kembalikan semua pengaturan ke nilai default</p>
                        <button onclick="confirmReset()" 
                                class="inline-flex items-center px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors font-medium">
                            <i class="fas fa-undo mr-2"></i>Reset Default
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="mt-8 bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-cyan-500 to-cyan-600 p-6 text-white">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-server text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Informasi Sistem</h3>
                        <p class="text-cyan-100 text-sm">Detail konfigurasi server dan aplikasi</p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 text-center">
                        <i class="fab fa-php text-blue-600 text-2xl mb-2"></i>
                        <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">PHP Version</h4>
                        <p class="text-lg font-bold text-gray-900"><?php echo PHP_VERSION; ?></p>
                    </div>
                    
                    <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 text-center">
                        <i class="fas fa-database text-green-600 text-2xl mb-2"></i>
                        <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Database</h4>
                        <p class="text-lg font-bold text-gray-900">MySQL</p>
                    </div>
                    
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 text-center">
                        <i class="fas fa-upload text-purple-600 text-2xl mb-2"></i>
                        <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Max Upload</h4>
                        <p class="text-lg font-bold text-gray-900"><?php echo ini_get('upload_max_filesize'); ?></p>
                    </div>
                    
                    <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl p-4 text-center">
                        <i class="fas fa-memory text-yellow-600 text-2xl mb-2"></i>
                        <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Memory Limit</h4>
                        <p class="text-lg font-bold text-gray-900"><?php echo ini_get('memory_limit'); ?></p>
                    </div>
                    
                    <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-xl p-4 text-center">
                        <i class="fas fa-globe text-indigo-600 text-2xl mb-2"></i>
                        <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Timezone</h4>
                        <p class="text-lg font-bold text-gray-900"><?php echo date_default_timezone_get(); ?></p>
                    </div>
                    
                    <div class="bg-gradient-to-br from-pink-50 to-pink-100 rounded-xl p-4 text-center">
                        <i class="fas fa-clock text-pink-600 text-2xl mb-2"></i>
                        <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Server Time</h4>
                        <p class="text-lg font-bold text-gray-900"><?php echo date('H:i'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Modal -->
    <div id="restoreModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all">
            <div class="bg-gradient-to-r from-green-500 to-green-600 p-6 text-white rounded-t-2xl">
                <h3 class="text-xl font-bold flex items-center">
                    <i class="fas fa-upload mr-3"></i>Restore Pengaturan
                </h3>
            </div>
            <form action="settings/backup_restore.php?action=restore" method="POST" enctype="multipart/form-data" class="p-6">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">
                        <i class="fas fa-file-code mr-2 text-green-500"></i>File Backup JSON
                    </label>
                    <div class="relative">
                        <input type="file" name="backup_file" accept=".json" required id="backup_file"
                               class="w-full px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-all file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Pilih file backup JSON yang telah didownload sebelumnya</p>
                </div>
                
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-amber-600 mr-3 mt-0.5 text-lg"></i>
                        <div class="text-sm text-amber-800">
                            <p class="font-semibold mb-1">Peringatan Penting:</p>
                            <p>Restore akan mengganti <strong>semua pengaturan yang ada</strong> dengan pengaturan dari file backup. Tindakan ini tidak dapat dibatalkan.</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeRestoreModal()" 
                            class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all font-medium">
                        <i class="fas fa-times mr-2"></i>Batal
                    </button>
                    <button type="submit" 
                            class="px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-all font-semibold">
                        <i class="fas fa-upload mr-2"></i>Restore Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Form (Hidden) -->
    <form id="resetForm" method="POST" action="settings/backup_restore.php?action=reset" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    </form>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-8 text-center">
            <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-primary mx-auto mb-4"></div>
            <p class="text-lg font-semibold text-gray-900">Menyimpan pengaturan...</p>
            <p class="text-sm text-gray-600">Mohon tunggu sebentar</p>
        </div>
    </div>

    <script>
        // Character counters for SEO fields
        function updateCharCount(inputId, countId, maxLength) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(countId);
            
            if (input && counter) {
                const updateCount = () => {
                    const length = input.value.length;
                    counter.textContent = length;
                    counter.className = length > maxLength * 0.9 ? 'text-red-500 font-semibold' : 'text-gray-500';
                };
                
                input.addEventListener('input', updateCount);
                updateCount(); // Initial count
            }
        }
        
        // Initialize character counters
        updateCharCount('meta_title_input', 'title_count', 60);
        updateCharCount('meta_desc_input', 'desc_count', 160);

        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '_icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // WhatsApp number formatting
        document.getElementById('whatsapp_input').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value.startsWith('0')) {
                value = '62' + value.substring(1);
            }
            e.target.value = value;
        });

        // Form validation and submission
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            // Show loading overlay
            document.getElementById('loadingOverlay').classList.remove('hidden');
            
            const siteName = document.querySelector('input[name="site_name"]').value.trim();
            const sitePhone = document.querySelector('input[name="site_phone"]').value.trim();
            const siteEmail = document.querySelector('input[name="site_email"]').value.trim();
            const minOrder = document.querySelector('input[name="min_order_amount"]').value;
            const freeShipping = document.querySelector('input[name="free_shipping_min"]').value;
            
            if (!siteName) {
                e.preventDefault();
                hideLoading();
                showAlert('Nama website harus diisi', 'error');
                return;
            }
            
            if (!sitePhone) {
                e.preventDefault();
                hideLoading();
                showAlert('Nomor telepon harus diisi', 'error');
                return;
            }
            
            if (!siteEmail) {
                e.preventDefault();
                hideLoading();
                showAlert('Email harus diisi', 'error');
                return;
            }
            
            if (!minOrder || minOrder <= 0) {
                e.preventDefault();
                hideLoading();
                showAlert('Minimal pembelian harus diisi dan lebih dari 0', 'error');
                return;
            }
            
            if (!freeShipping || freeShipping <= 0) {
                e.preventDefault();
                hideLoading();
                showAlert('Minimal gratis ongkir harus diisi dan lebih dari 0', 'error');
                return;
            }
            
            if (parseInt(freeShipping) <= parseInt(minOrder)) {
                e.preventDefault();
                hideLoading();
                showAlert('Minimal gratis ongkir harus lebih besar dari minimal pembelian', 'error');
                return;
            }
        });

        // Reset form function
        function resetForm() {
            if (confirm('Apakah Anda yakin ingin mereset form? Semua perubahan yang belum disimpan akan hilang.')) {
                document.getElementById('settingsForm').reset();
                
                // Reset character counters
                document.getElementById('title_count').textContent = '0';
                document.getElementById('desc_count').textContent = '0';
            }
        }

        // Restore modal functions
        function openRestoreModal() {
            document.getElementById('restoreModal').classList.remove('hidden');
        }

        function closeRestoreModal() {
            document.getElementById('restoreModal').classList.add('hidden');
        }

        // Reset confirmation
        function confirmReset() {
            const modal = createConfirmModal(
                'Reset Pengaturan ke Default',
                'Apakah Anda yakin ingin mereset semua pengaturan ke nilai default? Tindakan ini akan menghapus semua pengaturan kustom yang telah Anda buat.',
                'Konfirmasi sekali lagi: Reset semua pengaturan ke default?',
                function() {
                    document.getElementById('resetForm').submit();
                }
            );
            document.body.appendChild(modal);
        }

        // File validation for restore
        document.getElementById('backup_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
                    showAlert('File harus berformat JSON', 'error');
                    e.target.value = '';
                    return;
                }
                
                if (file.size > 1024 * 1024) { // 1MB
                    showAlert('File terlalu besar. Maksimal 1MB', 'error');
                    e.target.value = '';
                    return;
                }
            }
        });

        // Utility functions
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.add('hidden');
        }

        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `fixed top-4 right-4 z-50 p-4 rounded-xl shadow-lg border-l-4 ${
                type === 'error' ? 'bg-red-50 border-red-400 text-red-700' : 
                type === 'success' ? 'bg-green-50 border-green-400 text-green-700' :
                'bg-blue-50 border-blue-400 text-blue-700'
            } transform translate-x-full transition-transform`;
            
            alertDiv.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'error' ? 'fa-exclamation-triangle' : type === 'success' ? 'fa-check-circle' : 'fa-info-circle'} mr-3"></i>
                    <span class="font-medium">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Show alert
            setTimeout(() => alertDiv.classList.remove('translate-x-full'), 100);
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                alertDiv.classList.add('translate-x-full');
                setTimeout(() => alertDiv.remove(), 300);
            }, 5000);
        }

        function createConfirmModal(title, message, confirmMessage, callback) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
            modal.innerHTML = `
                <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">${title}</h3>
                        <p class="text-gray-600 mb-6">${message}</p>
                        <div class="flex justify-end space-x-3">
                            <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium">
                                Batal
                            </button>
                            <button onclick="if(confirm('${confirmMessage}')) { ${callback.toString()}(); this.closest('.fixed').remove(); }" 
                                    class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 font-semibold">
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            `;
            return modal;
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.id === 'restoreModal') {
                closeRestoreModal();
            }
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loading on page load
            hideLoading();
            
            // Initialize tooltips or other components here if needed
            console.log('Settings page initialized');
        });
    </script>
</body>
</html>