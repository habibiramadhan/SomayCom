<?php
// Set page title
$page_title = 'Edit Produk';

// Get product ID
$productId = (int)$id;

try {
    $pdo = getDB();
    
    // Get product data
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        redirectWithMessage('product.php', 'Produk tidak ditemukan', 'error');
    }
    
    // Get categories
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    // Get product images
    $stmt = $pdo->prepare("
        SELECT * FROM product_images 
        WHERE product_id = ? 
        ORDER BY is_primary DESC, sort_order ASC
    ");
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error loading edit product page: " . $e->getMessage());
    redirectWithMessage('product.php', 'Terjadi kesalahan saat memuat data produk', 'error');
}

// Get form data and errors from session (if any)
$formData = $_SESSION['form_data'] ?? $product;
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
                <h1 class="text-3xl font-bold text-gray-900">Edit Produk</h1>
                <p class="text-gray-600">Edit produk: <?php echo htmlspecialchars($product['name']); ?></p>
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
            <input type="hidden" name="process_action" value="update">
            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Product Information -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Dasar</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nama Produk *</label>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($formData['name']); ?>" 
                                       required maxlength="200"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="Contoh: Somay Original Isi 10">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">SKU *</label>
                                <input type="text" name="sku" value="<?php echo htmlspecialchars($formData['sku']); ?>" 
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
                                            <?php echo $formData['category_id'] == $category['id'] ? 'selected' : ''; ?>>
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
                                <input type="number" name="price" value="<?php echo $formData['price']; ?>" 
                                       required min="0" step="0.01"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="25000">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Harga Diskon (Rp)</label>
                                <input type="number" name="discount_price" value="<?php echo $formData['discount_price'] ?? ''; ?>" 
                                       min="0" step="0.01"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="20000">
                                <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ada diskon</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Berat (gram)</label>
                                <input type="number" name="weight" value="<?php echo $formData['weight'] ?? ''; ?>" 
                                       min="0" step="0.01"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Stok Saat Ini</label>
                                <input type="number" name="stock_quantity" value="<?php echo $formData['stock_quantity']; ?>" 
                                       min="0"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="50">
                                <p class="text-xs text-gray-500 mt-1">Ubah untuk menyesuaikan stok</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Stok</label>
                                <input type="number" name="min_stock" value="<?php echo $formData['min_stock']; ?>" 
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
                        
                        <!-- Existing Images -->
                        <?php if (!empty($images)): ?>
                        <div class="mb-6">
                            <h4 class="text-md font-medium text-gray-700 mb-3">Gambar Saat Ini</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <?php foreach ($images as $image): ?>
                                <div class="relative group">
                                    <img src="../uploads/products/<?php echo htmlspecialchars($image['image_path']); ?>" 
                                         class="w-full h-32 object-cover rounded-lg border"
                                         onerror="this.onerror=null; this.style.display='none'; this.parentElement.innerHTML='<div class=\'w-full h-32 bg-gray-200 rounded-lg border flex items-center justify-center\'><i class=\'fas fa-image text-gray-400 text-2xl\'></i></div>';">
                                    <?php if ($image['is_primary']): ?>
                                    <div class="absolute top-2 left-2 bg-primary text-white text-xs px-2 py-1 rounded">Utama</div>
                                    <?php endif; ?>
                                    <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center">
                                        <label class="cursor-pointer text-white hover:text-red-300">
                                            <input type="checkbox" name="delete_images[]" value="<?php echo $image['id']; ?>" class="sr-only">
                                            <i class="fas fa-trash text-xl"></i>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Centang gambar untuk menghapus</p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Upload New Images -->
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <input type="file" name="images[]" id="images" multiple accept="image/*" class="hidden">
                            <label for="images" class="cursor-pointer">
                                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                                <p class="text-lg font-medium text-gray-700">Tambah gambar baru</p>
                                <p class="text-sm text-gray-500">Klik untuk upload atau drag & drop</p>
                                <p class="text-xs text-gray-400 mt-2">JPG, PNG, GIF hingga 5MB</p>
                            </label>
                        </div>
                        
                        <div id="image-preview" class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 hidden">
                            <!-- New image previews will be inserted here -->
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
                                       <?php echo $formData['is_active'] ? 'checked' : ''; ?>
                                       class="rounded border-gray-300 text-primary focus:border-primary focus:ring-primary">
                                <span class="ml-2 text-sm font-medium text-gray-700">Aktif</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="is_featured" value="1" 
                                       <?php echo $formData['is_featured'] ? 'checked' : ''; ?>
                                       class="rounded border-gray-300 text-primary focus:border-primary focus:ring-primary">
                                <span class="ml-2 text-sm font-medium text-gray-700">Produk Unggulan</span>
                            </label>
                        </div>
                        
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="flex space-x-3">
                                <button type="submit" class="flex-1 bg-primary text-white py-2 px-4 rounded-lg font-semibold hover:bg-secondary transition-colors">
                                    <i class="fas fa-save mr-2"></i>Update Produk
                                </button>
                                <a href="product.php" class="flex-1 bg-gray-500 text-white py-2 px-4 rounded-lg font-semibold hover:bg-gray-600 transition-colors text-center">
                                    <i class="fas fa-times mr-2"></i>Batal
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Product Info -->
                    <div class="bg-blue-50 rounded-xl p-6">
                        <h4 class="text-lg font-semibold text-blue-900 mb-3">
                            <i class="fas fa-info-circle mr-2"></i>Info Produk
                        </h4>
                        <div class="text-sm text-blue-800 space-y-2">
                            <p><strong>Dibuat:</strong> <?php echo formatDate($product['created_at']); ?></p>
                            <p><strong>Diupdate:</strong> <?php echo formatDate($product['updated_at']); ?></p>
                            <p><strong>Slug:</strong> <?php echo htmlspecialchars($product['slug']); ?></p>
                            <div class="pt-2">
                                <a href="../pages/product-detail.php?slug=<?php echo $product['slug']; ?>" target="_blank" 
                                   class="inline-flex items-center text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-external-link-alt mr-1"></i>Lihat di Website
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Handle image deletion checkbox styling
        document.querySelectorAll('input[name="delete_images[]"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const imageContainer = this.closest('.relative');
                if (this.checked) {
                    imageContainer.classList.add('opacity-50');
                    imageContainer.querySelector('img').style.filter = 'grayscale(100%)';
                } else {
                    imageContainer.classList.remove('opacity-50');
                    imageContainer.querySelector('img').style.filter = 'none';
                }
            });
        });

        // Image preview functionality for new uploads
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
                                <div class="absolute top-2 left-2 bg-green-500 text-white text-xs px-2 py-1 rounded">Baru</div>
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