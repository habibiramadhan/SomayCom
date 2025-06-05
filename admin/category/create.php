<?php
// admin/category/create.php
// Set page title
$page_title = 'Tambah Kategori';

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
            max-width: 200px;
            max-height: 200px;
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
            <a href="category.php" class="text-gray-400 hover:text-gray-600 mr-4">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Tambah Kategori Baru</h1>
                <p class="text-gray-600">Tambahkan kategori produk somay atau siomay baru</p>
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
        <form method="POST" action="category.php?action=process" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="process_action" value="create">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Category Information -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Dasar</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nama Kategori *</label>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>" 
                                       required maxlength="100"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="Contoh: Somay Original">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                                <textarea name="description" rows="4"
                                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                          placeholder="Deskripsi kategori..."><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Category Image -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Gambar Kategori</h3>
                        
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <input type="file" name="image" id="image" accept="image/*" class="hidden">
                            <label for="image" class="cursor-pointer">
                                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                                <p class="text-lg font-medium text-gray-700">Klik untuk upload gambar</p>
                                <p class="text-sm text-gray-500">atau drag & drop gambar di sini</p>
                                <p class="text-xs text-gray-400 mt-2">JPG, PNG, GIF hingga 5MB</p>
                            </label>
                        </div>
                        
                        <div id="image-preview" class="mt-4 hidden">
                            <img id="preview-img" src="" class="preview-image rounded-lg border mx-auto">
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Publish Settings -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Pengaturan</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Urutan Tampil</label>
                                <input type="number" name="sort_order" value="<?php echo htmlspecialchars($formData['sort_order'] ?? '0'); ?>" 
                                       min="0" step="1"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                       placeholder="0">
                                <p class="text-xs text-gray-500 mt-1">Semakin kecil angka, semakin di atas urutannya</p>
                            </div>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" value="1" 
                                       <?php echo ($formData['is_active'] ?? '1') ? 'checked' : ''; ?>
                                       class="rounded border-gray-300 text-primary focus:border-primary focus:ring-primary">
                                <span class="ml-2 text-sm font-medium text-gray-700">Aktif</span>
                            </label>
                        </div>
                        
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="flex space-x-3">
                                <button type="submit" class="flex-1 bg-primary text-white py-2 px-4 rounded-lg font-semibold hover:bg-secondary transition-colors">
                                    <i class="fas fa-save mr-2"></i>Simpan Kategori
                                </button>
                                <a href="category.php" class="flex-1 bg-gray-500 text-white py-2 px-4 rounded-lg font-semibold hover:bg-gray-600 transition-colors text-center">
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
                            <li>• Gunakan nama kategori yang jelas dan mudah dipahami</li>
                            <li>• Upload gambar untuk mempercantik tampilan kategori</li>
                            <li>• Atur urutan tampil untuk mengurutkan kategori</li>
                            <li>• Kategori aktif akan tampil di website</li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Image preview functionality
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewContainer = document.getElementById('image-preview');
            const previewImg = document.getElementById('preview-img');
            
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewContainer.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                previewContainer.classList.add('hidden');
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.querySelector('input[name="name"]').value.trim();
            
            if (!name) {
                alert('Nama kategori harus diisi');
                e.preventDefault();
                return;
            }
        });