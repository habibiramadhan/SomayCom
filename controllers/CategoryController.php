<?php
// controllers/CategoryController.php
require_once '../models/Category.php';

class CategoryController {
    private $categoryModel;
    
    public function __construct() {
        $this->categoryModel = new Category();
    }
    
    /**
     * Display categories list
     */
    public function index() {
        // Get filters from GET parameters
        $filters = [
            'search' => $_GET['search'] ?? '',
            'is_active' => $_GET['is_active'] ?? ''
        ];
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['per_page'] ?? 20);
        
        // Get categories
        $categories = $this->categoryModel->getAllCategories($page, $limit, $filters);
        $totalCategories = $this->categoryModel->getTotalCategories($filters);
        $totalPages = ceil($totalCategories / $limit);
        
        // Pass data to view
        $data = [
            'categories' => $categories,
            'filters' => $filters,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalCategories,
                'per_page' => $limit
            ]
        ];
        
        $this->renderView('categories/index', $data);
    }
    
    /**
     * Show create category form
     */
    public function create() {
        $data = [
            'next_sort_order' => $this->categoryModel->getNextSortOrder()
        ];
        
        $this->renderView('categories/create', $data);
    }
    
    /**
     * Store new category
     */
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirectWithMessage('category.php', 'Method not allowed', 'error');
        }
        
        // Validate CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            redirectWithMessage('category.php', 'Invalid CSRF token', 'error');
        }
        
        // Prepare data
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => $this->categoryModel->generateSlug($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Validate data
        $errors = $this->categoryModel->validateCategory($data);
        if (!empty($errors)) {
            $_SESSION['form_data'] = $data;
            $_SESSION['form_errors'] = $errors;
            header('Location: category.php?action=create');
            exit;
        }
        
        try {
            // Handle image upload
            if (!empty($_FILES['image']['name'])) {
                $data['image'] = $this->handleImageUpload($_FILES['image']);
            }
            
            $categoryId = $this->categoryModel->createCategory($data);
            
            if ($categoryId) {
                // Log activity
                logActivity('category_create', "Created category: {$data['name']}", $_SESSION['admin_id']);
                
                redirectWithMessage('category.php', 'Kategori berhasil ditambahkan', 'success');
            } else {
                throw new Exception('Failed to create category');
            }
        } catch (Exception $e) {
            error_log("Error creating category: " . $e->getMessage());
            $_SESSION['form_data'] = $data;
            redirectWithMessage('category.php?action=create', 'Gagal menambahkan kategori: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Show category details
     */
    public function view($id) {
        $category = $this->categoryModel->getCategoryById($id);
        if (!$category) {
            redirectWithMessage('category.php', 'Kategori tidak ditemukan', 'error');
        }
        
        $products = $this->categoryModel->getCategoryProducts($id);
        $stats = $this->categoryModel->getCategoryStats($id);
        
        $data = [
            'category' => $category,
            'products' => $products,
            'stats' => $stats
        ];
        
        $this->renderView('categories/view', $data);
    }
    
    /**
     * Show edit category form
     */
    public function edit($id) {
        $category = $this->categoryModel->getCategoryById($id);
        if (!$category) {
            redirectWithMessage('category.php', 'Kategori tidak ditemukan', 'error');
        }
        
        $data = [
            'category' => $category
        ];
        
        $this->renderView('categories/edit', $data);
    }
    
    /**
     * Update category
     */
    public function update($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirectWithMessage('category.php', 'Method not allowed', 'error');
        }
        
        // Validate CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            redirectWithMessage('category.php', 'Invalid CSRF token', 'error');
        }
        
        $category = $this->categoryModel->getCategoryById($id);
        if (!$category) {
            redirectWithMessage('category.php', 'Kategori tidak ditemukan', 'error');
        }
        
        // Prepare data
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => $this->categoryModel->generateSlug($_POST['name'] ?? '', $id),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'image' => $category['image'] // Keep existing image by default
        ];
        
        // Validate data
        $errors = $this->categoryModel->validateCategory($data, $id);
        if (!empty($errors)) {
            $_SESSION['form_data'] = $data;
            $_SESSION['form_errors'] = $errors;
            header("Location: category.php?action=edit&id=$id");
            exit;
        }
        
        try {
            // Handle image deletion
            if (isset($_POST['delete_image']) && !empty($category['image'])) {
                $this->deleteImage($category['image']);
                $data['image'] = null;
            }
            
            // Handle new image upload
            if (!empty($_FILES['image']['name'])) {
                // Delete old image if exists
                if (!empty($category['image'])) {
                    $this->deleteImage($category['image']);
                }
                $data['image'] = $this->handleImageUpload($_FILES['image']);
            }
            
            $success = $this->categoryModel->updateCategory($id, $data);
            
            if ($success) {
                // Log activity
                logActivity('category_update', "Updated category: {$data['name']}", $_SESSION['admin_id']);
                
                redirectWithMessage('category.php', 'Kategori berhasil diupdate', 'success');
            } else {
                throw new Exception('Failed to update category');
            }
        } catch (Exception $e) {
            error_log("Error updating category: " . $e->getMessage());
            $_SESSION['form_data'] = $data;
            redirectWithMessage("category.php?action=edit&id=$id", 'Gagal mengupdate kategori: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Delete category
     */
    public function delete($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirectWithMessage('category.php', 'Method not allowed', 'error');
        }
        
        // Validate CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            redirectWithMessage('category.php', 'Invalid CSRF token', 'error');
        }
        
        $category = $this->categoryModel->getCategoryById($id);
        if (!$category) {
            redirectWithMessage('category.php', 'Kategori tidak ditemukan', 'error');
        }
        
        try {
            $success = $this->categoryModel->deleteCategory($id);
            
            if ($success) {
                // Delete image if exists
                if (!empty($category['image'])) {
                    $this->deleteImage($category['image']);
                }
                
                // Log activity
                logActivity('category_delete', "Deleted category: {$category['name']}", $_SESSION['admin_id']);
                
                redirectWithMessage('category.php', 'Kategori berhasil dihapus', 'success');
            } else {
                throw new Exception('Failed to delete category');
            }
        } catch (Exception $e) {
            error_log("Error deleting category: " . $e->getMessage());
            redirectWithMessage('category.php', 'Gagal menghapus kategori: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Toggle category status
     */
    public function toggleStatus() {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        $id = (int)($_POST['id'] ?? 0);
        $isActive = $_POST['is_active'] === 'true' ? 1 : 0;
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
            exit;
        }
        
        try {
            $category = $this->categoryModel->getCategoryById($id);
            if (!$category) {
                echo json_encode(['success' => false, 'message' => 'Kategori tidak ditemukan']);
                exit;
            }
            
            $success = $this->categoryModel->updateStatus($id, $isActive);
            
            if ($success) {
                // Log activity
                $status = $isActive ? 'activated' : 'deactivated';
                logActivity('category_toggle', "Category {$status}: {$category['name']}", $_SESSION['admin_id']);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Status kategori berhasil diubah'
                ]);
            } else {
                throw new Exception('Failed to update status');
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Update sort order
     */
    public function updateSortOrder() {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        $orders = $_POST['orders'] ?? [];
        
        if (empty($orders)) {
            echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
            exit;
        }
        
        try {
            $success = $this->categoryModel->updateSortOrder($orders);
            
            if ($success) {
                logActivity('category_reorder', 'Updated category sort order', $_SESSION['admin_id']);
                echo json_encode(['success' => true, 'message' => 'Urutan berhasil diubah']);
            } else {
                throw new Exception('Failed to update sort order');
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Handle image upload
     */
    private function handleImageUpload($file) {
        // Create categories upload directory if it doesn't exist
        $uploadDir = UPLOAD_PATH . 'categories/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Gagal membuat directory upload');
            }
        }
        
        return uploadFile($file, $uploadDir, ALLOWED_IMAGE_TYPES);
    }
    
    /**
     * Delete category image
     */
    private function deleteImage($imagePath) {
        if (!empty($imagePath)) {
            $fullPath = UPLOAD_PATH . 'categories/' . $imagePath;
            return deleteFile($fullPath);
        }
        return false;
    }
    
    /**
     * Render view
     */
    private function renderView($view, $data = []) {
        extract($data);
        
        // Set page title
        $page_title = 'Kelola Kategori';
        
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

$controller = new CategoryController();

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
        
    case 'view':
        hasAccess();
        if (!hasPermission('view_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        if (!$id) {
            redirectWithMessage('category.php', 'ID kategori tidak valid', 'error');
        }
        $controller->view($id);
        break;
        
    case 'edit':
        hasAccess();
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        if (!$id) {
            redirectWithMessage('category.php', 'ID kategori tidak valid', 'error');
        }
        $controller->edit($id);
        break;
        
    case 'update':
        hasAccess();
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        if (!$id) {
            redirectWithMessage('category.php', 'ID kategori tidak valid', 'error');
        }
        $controller->update($id);
        break;
        
    case 'delete':
        hasAccess();
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        if (!$id) {
            redirectWithMessage('category.php', 'ID kategori tidak valid', 'error');
        }
        $controller->delete($id);
        break;
        
    case 'toggle_status':
        hasAccess();
        if (!hasPermission('manage_products')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
        $controller->toggleStatus();
        break;
        
    case 'update_sort_order':
        hasAccess();
        if (!hasPermission('manage_products')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
        $controller->updateSortOrder();
        break;
        
    default:
        hasAccess();
        if (!hasPermission('view_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        $controller->index();
        break;
}