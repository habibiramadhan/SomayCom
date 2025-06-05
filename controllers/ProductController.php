<?php
require_once '../models/Product.php';

class ProductController {
    private $productModel;
    
    public function __construct() {
        $this->productModel = new Product();
    }
    
    /**
     * Display products list
     */
    public function index() {
        // Get filters from GET parameters
        $filters = [
            'search' => $_GET['search'] ?? '',
            'category_id' => $_GET['category_id'] ?? '',
            'is_active' => $_GET['is_active'] ?? '',
            'is_featured' => $_GET['is_featured'] ?? '',
            'low_stock' => $_GET['low_stock'] ?? ''
        ];
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = 20;
        
        // Get products
        $products = $this->productModel->getAllProducts($page, $limit, $filters);
        $totalProducts = $this->productModel->getTotalProducts($filters);
        $totalPages = ceil($totalProducts / $limit);
        
        // Get categories for filter
        $categories = $this->productModel->getCategories();
        
        // Pass data to view
        $data = [
            'products' => $products,
            'categories' => $categories,
            'filters' => $filters,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalProducts,
                'per_page' => $limit
            ]
        ];
        
        $this->renderView('products/index', $data);
    }
    
    /**
     * Show create product form
     */
    public function create() {
        $categories = $this->productModel->getCategories();
        $generatedSKU = $this->productModel->generateSKU();
        
        $data = [
            'categories' => $categories,
            'generated_sku' => $generatedSKU
        ];
        
        $this->renderView('products/create', $data);
    }
    
    /**
     * Store new product
     */
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirectWithMessage('products.php', 'Method not allowed', 'error');
        }
        
        // Validate CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            redirectWithMessage('products.php', 'Invalid CSRF token', 'error');
        }
        
        // Prepare data
        $data = [
            'category_id' => $_POST['category_id'] ?? null,
            'sku' => trim($_POST['sku'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'slug' => $this->productModel->generateSlug($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'short_description' => trim($_POST['short_description'] ?? ''),
            'price' => (float)($_POST['price'] ?? 0),
            'discount_price' => !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null,
            'weight' => (float)($_POST['weight'] ?? 0),
            'stock_quantity' => (int)($_POST['stock_quantity'] ?? 0),
            'min_stock' => (int)($_POST['min_stock'] ?? 5),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'meta_title' => trim($_POST['meta_title'] ?? ''),
            'meta_description' => trim($_POST['meta_description'] ?? '')
        ];
        
        // Validate data
        $errors = $this->productModel->validateProduct($data);
        if (!empty($errors)) {
            $_SESSION['form_data'] = $data;
            $_SESSION['form_errors'] = $errors;
            header('Location: product_create.php');
            exit;
        }
        
        try {
            $productId = $this->productModel->createProduct($data);
            
            if ($productId) {
                // Handle image uploads
                $this->handleImageUploads($productId);
                
                // Log activity
                logActivity('product_create', "Created product: {$data['name']}", $_SESSION['admin_id']);
                
                redirectWithMessage('products.php', 'Produk berhasil ditambahkan', 'success');
            } else {
                throw new Exception('Failed to create product');
            }
        } catch (Exception $e) {
            error_log("Error creating product: " . $e->getMessage());
            $_SESSION['form_data'] = $data;
            redirectWithMessage('product_create.php', 'Gagal menambahkan produk: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Show edit product form
     */
    public function edit($id) {
        $product = $this->productModel->getProductById($id);
        if (!$product) {
            redirectWithMessage('products.php', 'Produk tidak ditemukan', 'error');
        }
        
        $categories = $this->productModel->getCategories();
        $images = $this->productModel->getProductImages($id);
        
        $data = [
            'product' => $product,
            'categories' => $categories,
            'images' => $images
        ];
        
        $this->renderView('products/edit', $data);
    }
    
    /**
     * Update product
     */
    public function update($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirectWithMessage('products.php', 'Method not allowed', 'error');
        }
        
        // Validate CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            redirectWithMessage('products.php', 'Invalid CSRF token', 'error');
        }
        
        $product = $this->productModel->getProductById($id);
        if (!$product) {
            redirectWithMessage('products.php', 'Produk tidak ditemukan', 'error');
        }
        
        // Prepare data
        $data = [
            'category_id' => $_POST['category_id'] ?? null,
            'sku' => trim($_POST['sku'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'slug' => $this->productModel->generateSlug($_POST['name'] ?? '', $id),
            'description' => trim($_POST['description'] ?? ''),
            'short_description' => trim($_POST['short_description'] ?? ''),
            'price' => (float)($_POST['price'] ?? 0),
            'discount_price' => !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null,
            'weight' => (float)($_POST['weight'] ?? 0),
            'stock_quantity' => (int)($_POST['stock_quantity'] ?? 0),
            'min_stock' => (int)($_POST['min_stock'] ?? 5),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'meta_title' => trim($_POST['meta_title'] ?? ''),
            'meta_description' => trim($_POST['meta_description'] ?? '')
        ];
        
        // Validate data
        $errors = $this->productModel->validateProduct($data, $id);
        if (!empty($errors)) {
            $_SESSION['form_data'] = $data;
            $_SESSION['form_errors'] = $errors;
            header("Location: product_edit.php?id=$id");
            exit;
        }
        
        try {
            $success = $this->productModel->updateProduct($id, $data);
            
            if ($success) {
                // Handle image uploads
                $this->handleImageUploads($id);
                
                // Handle image deletions
                if (!empty($_POST['delete_images'])) {
                    foreach ($_POST['delete_images'] as $imageId) {
                        $this->productModel->deleteProductImage($imageId);
                    }
                }
                
                // Log activity
                logActivity('product_update', "Updated product: {$data['name']}", $_SESSION['admin_id']);
                
                redirectWithMessage('products.php', 'Produk berhasil diupdate', 'success');
            } else {
                throw new Exception('Failed to update product');
            }
        } catch (Exception $e) {
            error_log("Error updating product: " . $e->getMessage());
            $_SESSION['form_data'] = $data;
            redirectWithMessage("product_edit.php?id=$id", 'Gagal mengupdate produk: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Delete product
     */
    public function delete($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirectWithMessage('products.php', 'Method not allowed', 'error');
        }
        
        // Validate CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            redirectWithMessage('products.php', 'Invalid CSRF token', 'error');
        }
        
        $product = $this->productModel->getProductById($id);
        if (!$product) {
            redirectWithMessage('products.php', 'Produk tidak ditemukan', 'error');
        }
        
        try {
            $success = $this->productModel->deleteProduct($id);
            
            if ($success) {
                // Log activity
                logActivity('product_delete', "Deleted product: {$product['name']}", $_SESSION['admin_id']);
                
                redirectWithMessage('products.php', 'Produk berhasil dihapus', 'success');
            } else {
                throw new Exception('Failed to delete product');
            }
        } catch (Exception $e) {
            error_log("Error deleting product: " . $e->getMessage());
            redirectWithMessage('products.php', 'Gagal menghapus produk: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Update stock
     */
    public function updateStock() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        if (!$productId || $quantity == 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        
        try {
            $success = $this->productModel->updateStock($productId, $quantity, 'adjustment', null, $notes);
            
            if ($success) {
                $product = $this->productModel->getProductById($productId);
                logActivity('stock_adjustment', "Stock adjustment for {$product['name']}: $quantity", $_SESSION['admin_id']);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true, 
                    'message' => 'Stok berhasil diupdate',
                    'new_stock' => $product['stock_quantity']
                ]);
            } else {
                throw new Exception('Failed to update stock');
            }
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Handle image uploads
     */
    private function handleImageUploads($productId) {
        if (!empty($_FILES['images']['name'][0])) {
            $totalImages = count($_FILES['images']['name']);
            
            for ($i = 0; $i < $totalImages; $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['images']['name'][$i],
                        'type' => $_FILES['images']['type'][$i],
                        'tmp_name' => $_FILES['images']['tmp_name'][$i],
                        'error' => $_FILES['images']['error'][$i],
                        'size' => $_FILES['images']['size'][$i]
                    ];
                    
                    try {
                        $filename = uploadFile($file, PRODUCT_IMAGE_PATH, ALLOWED_IMAGE_TYPES);
                        $isPrimary = ($i == 0 && empty($this->productModel->getProductImages($productId)));
                        $this->productModel->addProductImage($productId, $filename, '', $isPrimary, $i);
                    } catch (Exception $e) {
                        error_log("Error uploading image: " . $e->getMessage());
                    }
                }
            }
        }
    }
    
    /**
     * Render view
     */
    private function renderView($view, $data = []) {
        extract($data);
        
        // Set page title
        $page_title = 'Kelola Produk';
        
        // Include header
        include '../includes/header.php';
        
        // Include sidebar
        include '../includes/sidebar.php';
        
        // Include main content
        include "../views/$view.php";
        
        // Include footer if needed
        // include '../includes/footer.php';
    }
}

// Route handling
$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

$controller = new ProductController();

switch ($action) {
    case 'create':
        hasAccess();
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        $controller->create();
        break;
        
    case 'store':
        hasAccess();
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        $controller->store();
        break;
        
    case 'edit':
        hasAccess();
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        if (!$id) {
            redirectWithMessage('products.php', 'ID produk tidak valid', 'error');
        }
        $controller->edit($id);
        break;
        
    case 'update':
        hasAccess();
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        if (!$id) {
            redirectWithMessage('products.php', 'ID produk tidak valid', 'error');
        }
        $controller->update($id);
        break;
        
    case 'delete':
        hasAccess();
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        if (!$id) {
            redirectWithMessage('products.php', 'ID produk tidak valid', 'error');
        }
        $controller->delete($id);
        break;
        
    case 'update_stock':
        hasAccess();
        if (!hasPermission('manage_products')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
        $controller->updateStock();
        break;
        
    default:
        hasAccess();
        if (!hasPermission('view_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        $controller->index();
        break;
}