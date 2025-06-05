<?php
// Set page title
$page_title = 'Tambah Produk';

try {
    $pdo = getDB();
    
    // Get categories
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    // Generate SKU
    $generatedSKU = generateSKU();
    
} catch (Exception $e) {
    error_log("Error loading create product page: " . $e->getMessage());
    $categories = [];
    $generatedSKU = 'PRD' . date('y') . rand(1000, 9999);
}

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
    <style>
        .preview-image {
            max-width: 150px;
            max-height: 150px;
            object-fit: cover;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="flex items-center mb-8">
            <a href="product.php" class="text-gray-400 hover:text-gray-600 mr-4">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Tambah Produk Baru</h1>
                <p class="text-gray-600">Tambahkan produk somay atau siomay baru</p>
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
        <form method="POST" action="product.php?action=process" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="process_action" value="create">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Product Information -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Dasar</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nama Produk *</label>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>" 
                                       required maxlength="200"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="Contoh: Somay Original Isi 10">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">SKU *</label>
                                <input type="text" name="sku" value="<?php echo htmlspecialchars($formData['sku'] ?? $generatedSKU); ?>" 
                                       required maxlength="50"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="Contoh: SMY001">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                                <select name="category_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo ($formData['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi Singkat</label>
                            <textarea name="short_description" rows="3" maxlength="500"
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                      placeholder="Deskripsi singkat untuk preview produk..."><?php echo htmlspecialchars($formData['short_description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi Lengkap</label>
                            <textarea name="description" rows="5"
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                      placeholder="Deskripsi lengkap produk..."><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Pricing & Stock -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Harga & Stok</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Harga Normal (Rp) *</label>
                                <input type="number" name="price" value="<?php echo htmlspecialchars($formData['price'] ?? ''); ?>" 
                                       required min="0" step="0.01"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="25000">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Harga Diskon (Rp)</label>
                                <input type="number" name="discount_price" value="<?php echo htmlspecialchars($formData['discount_price'] ?? ''); ?>" 
                                       min="0" step="0.01"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="20000">
                                <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ada diskon</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Berat (gram)</label>
                                <input type="number" name="weight" value="<?php echo htmlspecialchars($formData['weight'] ?? ''); ?>" 
                                       min="0" step="0.01"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Stok Awal</label>
                                <input type="number" name="stock_quantity" value="<?php echo htmlspecialchars($formData['stock_quantity'] ?? '0'); ?>" 
                                       min="0"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="50">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Stok</label>
                                <input type="number" name="min_stock" value="<?php echo htmlspecialchars($formData['min_stock'] ?? '5'); ?>" 
                                       min="0"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="5">
                                <p class="text-xs text-gray-500 mt-1">Alert ketika stok mencapai jumlah ini</p>
                            </div>
                        </div>
                    </div>

                    <!-- Product Images -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Gambar Produk</h3>
                        
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <input type="file" name="images[]" id="images" multiple accept="image/*" class="hidden">
                            <label for="images" class="cursor-pointer">
                                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                                <p class="text-lg font-medium text-gray-700">Klik untuk upload gambar</p>
                                <p class="text-sm text-gray-500">atau drag & drop gambar di sini</p>
                                <p class="text-xs text-gray-400 mt-2">JPG, PNG, GIF hingga 5MB. Gambar pertama akan menjadi gambar utama.</p>
                            </label>
                        </div>
                        
                        <div id="image-preview" class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 hidden">
                            <!-- Image previews will be inserted here -->
                        </div>
                    </div>

                    <!-- SEO Settings -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">SEO & Meta</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Meta Title</label>
                                <input type="text" name="meta_title" value="<?php echo htmlspecialchars($formData['meta_title'] ?? ''); ?>" 
                                       maxlength="200"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="Judul untuk mesin pencari">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Meta Description</label>
                                <textarea name="meta_description" rows="3" maxlength="300"
                                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                          placeholder="Deskripsi untuk mesin pencari..."><?php echo htmlspecialchars($formData['meta_description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Publish Settings -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Pengaturan Publikasi</h3>
                        
                        <div class="space-y-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" value="1" 
                                       <?php echo ($formData['is_active'] ?? '1') ? 'checked' : ''; ?>
                                       class="rounded border-gray-300 text-primary focus:border-primary focus:ring-primary">
                                <span class="ml-2 text-sm font-medium text-gray-700">Aktif</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="is_featured" value="1" 
                                       <?php echo ($formData['is_featured'] ?? '') ? 'checked' : ''; ?>
                                       class="rounded border-gray-300 text-primary focus:border-primary focus:ring-primary">
                                <span class="ml-2 text-sm font-medium text-gray-700">Produk Unggulan</span>
                            </label>
                        </div>
                        
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="flex space-x-3">
                                <button type="submit" class="flex-1 bg-primary text-white py-2 px-4 rounded-lg font-semibold hover:bg-secondary transition-colors">
                                    <i class="fas fa-save mr-2"></i>Simpan Produk
                                </button>
                                <a href="product.php" class="flex-1 bg-gray-500 text-white py-2 px-4 rounded-lg font-semibold hover:bg-gray-600 transition-colors text-center">
                                    <i class="fas fa-times mr-2"></i>Batal
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Help -->
                    <div class="bg-blue-50 rounded-xl p-6">
                        <h4 class="text-lg font-semibold text-blue-900 mb-3">
                            <i class="fas fa-info-circle mr-2"></i>Tips
                        </h4>
                        <ul class="text-sm text-blue-800 space-y-2">
                            <li>• Gunakan nama produk yang jelas dan deskriptif</li>
                            <li>• Upload gambar berkualitas tinggi untuk menarik pelanggan</li>
                            <li>• Set harga diskon jika ada promosi</li>
                            <li>• Atur minimum stok untuk alert otomatis</li>
                            <li>• Centang "Produk Unggulan" untuk tampil di homepage</li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Image preview functionality
        document.getElementById('images').addEventListener('change', function(e) {
            const files = e.target.files;
            const previewContainer = document.getElementById('image-preview');
            
            if (files.length > 0) {
                previewContainer.classList.remove('hidden');
                previewContainer.innerHTML = '';
                
                Array.from(files).forEach((file, index) => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.className = 'relative';
                            div.innerHTML = `
                                <img src="${e.target.result}" class="preview-image w-full h-32 rounded-lg border">
                                ${index === 0 ? '<div class="absolute top-2 left-2 bg-primary text-white text-xs px-2 py-1 rounded">Utama</div>' : ''}
                            `;
                            previewContainer.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            } else {
                previewContainer.classList.add('hidden');
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.querySelector('input[name="name"]').value.trim();
            const sku = document.querySelector('input[name="sku"]').value.trim();
            const price = document.querySelector('input[name="price"]').value;
            const discountPrice = document.querySelector('input[name="discount_price"]').value;
            
            if (!name) {
                alert('Nama produk harus diisi');
                e.preventDefault();
                return;
            }
            
            if (!sku) {
                alert('SKU harus diisi');
                e.preventDefault();
                return;
            }
            
            if (!price || price <= 0) {
                alert('Harga harus diisi dan lebih dari 0');
                e.preventDefault();
                return;
            }
            
            if (discountPrice && parseFloat(discountPrice) >= parseFloat(price)) {
                alert('Harga diskon harus lebih kecil dari harga normal');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>