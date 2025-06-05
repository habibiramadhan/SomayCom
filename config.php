<!-- config.php -->
<?php
/**
 * Configuration File for Somay POS Ecommerce
 * Database connection and application settings
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (development mode)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pos_somay_ecommerce');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('SITE_NAME', 'Somay Ecommerce');
define('SITE_URL', 'http://localhost/somaycom');
define('ADMIN_URL', SITE_URL . '/admin');

// Path Configuration
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/somaycom/uploads/');
define('PRODUCT_IMAGE_PATH', UPLOAD_PATH . 'products/');
define('PAYMENT_PROOF_PATH', UPLOAD_PATH . 'payments/');

// File Upload Settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// App Settings
define('ITEMS_PER_PAGE', 12);
define('ADMIN_ITEMS_PER_PAGE', 20);
define('MIN_ORDER_AMOUNT', 25000);
define('FREE_SHIPPING_MIN', 100000);

// Contact Information
define('SITE_PHONE', '081234567890');
define('SITE_EMAIL', 'info@somay.com');
define('WHATSAPP_NUMBER', '6281234567890');
define('SITE_ADDRESS', 'Tangerang Selatan, Banten');

// Order Settings
define('ORDER_PREFIX', 'ORD');
define('ORDER_EXPIRY_HOURS', 24);

// Security Settings
define('ADMIN_SESSION_TIMEOUT', 3600);
define('PASSWORD_MIN_LENGTH', 6);

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');

/**
 * Database Connection Function
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * Test Database Connection
 */
function testDBConnection() {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get App Setting from Database with caching
 */
function getAppSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        $settings = [];
        try {
            $pdo = getDB();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("Settings table not found: " . $e->getMessage());
        }
    }
    
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Update App Setting in Database
 */
function updateAppSetting($key, $value, $type = 'string') {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO app_settings (setting_key, setting_value, setting_type, updated_at) 
            VALUES (?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value), 
            setting_type = VALUES(setting_type),
            updated_at = VALUES(updated_at)
        ");
        return $stmt->execute([$key, $value, $type]);
    } catch (Exception $e) {
        error_log("Error updating setting: " . $e->getMessage());
        return false;
    }
}

/**
 * Get multiple app settings at once
 */
function getAppSettings($keys = []) {
    try {
        $pdo = getDB();
        
        if (empty($keys)) {
            // Get all settings
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings");
        } else {
            // Get specific settings
            $placeholders = str_repeat('?,', count($keys) - 1) . '?';
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ($placeholders)");
            $stmt->execute($keys);
        }
        
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        error_log("Error getting settings: " . $e->getMessage());
        return [];
    }
}

/**
 * Get dynamic site configuration from database
 */
function getSiteConfig() {
    static $config = null;
    
    if ($config === null) {
        $config = getAppSettings([
            'site_name',
            'site_phone', 
            'site_email',
            'site_address',
            'whatsapp_number',
            'min_order_amount',
            'free_shipping_min',
            'order_prefix',
            'meta_title',
            'meta_description',
            'meta_keywords'
        ]);
        
        // Set defaults if not found
        $defaults = [
            'site_name' => SITE_NAME,
            'site_phone' => SITE_PHONE,
            'site_email' => SITE_EMAIL,
            'site_address' => SITE_ADDRESS,
            'whatsapp_number' => WHATSAPP_NUMBER,
            'min_order_amount' => MIN_ORDER_AMOUNT,
            'free_shipping_min' => FREE_SHIPPING_MIN,
            'order_prefix' => ORDER_PREFIX,
            'meta_title' => SITE_NAME . ' - Distributor Somay Terlengkap',
            'meta_description' => 'Distributor somay dan siomay terpercaya dengan pengiriman cepat se-Tangerang Selatan',
            'meta_keywords' => 'somay, siomay, distributor, tangerang selatan'
        ];
        
        foreach ($defaults as $key => $value) {
            if (!isset($config[$key])) {
                $config[$key] = $value;
            }
        }
    }
    
    return $config;
}

/**
 * Initialize default app settings
 */
function initializeAppSettings() {
    $defaultSettings = [
        'site_name' => [SITE_NAME, 'string', 'Nama website'],
        'site_phone' => [SITE_PHONE, 'string', 'Nomor telepon toko'],
        'site_email' => [SITE_EMAIL, 'string', 'Email toko'],
        'site_address' => [SITE_ADDRESS, 'string', 'Alamat toko'],
        'min_order_amount' => [MIN_ORDER_AMOUNT, 'number', 'Minimal pembelian'],
        'free_shipping_min' => [FREE_SHIPPING_MIN, 'number', 'Minimal gratis ongkir'],
        'order_prefix' => [ORDER_PREFIX, 'string', 'Prefix nomor order'],
        'order_expiry_hours' => [ORDER_EXPIRY_HOURS, 'number', 'Jam kadaluarsa order'],
        'whatsapp_number' => [WHATSAPP_NUMBER, 'string', 'Nomor WhatsApp customer service'],
        'whatsapp_order_message' => ['Halo, saya ingin konfirmasi pesanan dengan nomor: {order_number}', 'string', 'Template pesan WhatsApp order'],
        'meta_title' => [SITE_NAME . ' - Distributor Somay Terlengkap', 'string', 'Meta title untuk SEO'],
        'meta_description' => ['Distributor somay dan siomay terpercaya dengan pengiriman cepat se-Tangerang Selatan', 'string', 'Meta description untuk SEO'],
        'meta_keywords' => ['somay, siomay, distributor, tangerang selatan', 'string', 'Meta keywords untuk SEO'],
        'smtp_host' => [SMTP_HOST, 'string', 'SMTP Host untuk email'],
        'smtp_port' => [SMTP_PORT, 'number', 'SMTP Port'],
        'smtp_username' => [SMTP_USERNAME, 'string', 'SMTP Username'],
        'smtp_password' => [SMTP_PASSWORD, 'string', 'SMTP Password'],
        'facebook_url' => ['', 'string', 'URL Facebook'],
        'instagram_url' => ['', 'string', 'URL Instagram'],
        'twitter_url' => ['', 'string', 'URL Twitter']
    ];
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO app_settings (setting_key, setting_value, setting_type, description, is_editable, created_at, updated_at) 
            VALUES (?, ?, ?, ?, 1, NOW(), NOW())
        ");
        
        foreach ($defaultSettings as $key => $data) {
            $stmt->execute([$key, $data[0], $data[1], $data[2]]);
        }
    } catch (Exception $e) {
        error_log("Error initializing settings: " . $e->getMessage());
    }
}

/**
 * Create necessary directories if they don't exist
 */
function createDirectories() {
    $directories = [
        UPLOAD_PATH,
        PRODUCT_IMAGE_PATH,
        PAYMENT_PROOF_PATH
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            try {
                $parentDir = dirname($dir);
                if (!is_writable($parentDir)) {
                    error_log("Parent directory is not writable: " . $parentDir);
                    continue;
                }
                
                if (!@mkdir($dir, 0755, true)) {
                    $error = error_get_last();
                    error_log("Failed to create directory: " . $dir . " - Error: " . $error['message']);
                }
            } catch (Exception $e) {
                error_log("Error creating directory: " . $dir . " - " . $e->getMessage());
            }
        }
    }
}

/**
 * Initialize Application
 */
function initializeApp() {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    date_default_timezone_set('Asia/Jakarta');
    
    if (!testDBConnection()) {
        die("Cannot connect to database. Please check your configuration.");
    }
    
    createDirectories();
    initializeAppSettings();
}

// Auto-initialize when config is loaded
initializeApp();

/**
 * Debug function (remove in production)
 */
function debug($data, $die = false) {
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    if ($die) die();
}

/**
 * Environment check
 */
function isProduction() {
    return isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost';
}

// Disable error display in production
if (isProduction()) {
    error_reporting(0);
    ini_set('display_errors', 0);
}

/**
 * Currency formatting
 */
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Date formatting
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Get current timestamp
 */
function getCurrentTimestamp() {
    return date('Y-m-d H:i:s');
}

/**
 * Generate secure random string
 */
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length));
}

/**
 * Generate unique order number
 */
function generateOrderNumber() {
    $prefix = getAppSetting('order_prefix', ORDER_PREFIX);
    $date = date('ymd');
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $count = $stmt->fetchColumn() + 1;
        
        return $prefix . $date . str_pad($count, 3, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        return $prefix . $date . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    }
}

/**
 * Safe redirect function
 */
function safeRedirect($url) {
    if (strpos($url, 'http') === 0) {
        $parsed = parse_url($url);
        $current_host = $_SERVER['HTTP_HOST'];
        if ($parsed['host'] !== $current_host) {
            $url = SITE_URL;
        }
    }
    
    header("Location: " . $url);
    exit();
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Indonesian format)
 */
function isValidPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^(08|628|\+628)[0-9]{8,12}$/', $phone);
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Generate SKU
 */
function generateSKU($prefix = 'PRD') {
    return $prefix . date('y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * File upload handler with improved error handling
 */
function uploadFile($file, $directory, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp']) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (php.ini limit)',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (form limit)', 
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ada',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh extension'
        ];
        
        $error_msg = $upload_errors[$file['error']] ?? 'Upload error: ' . $file['error'];
        throw new Exception($error_msg);
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        $max_size_mb = MAX_FILE_SIZE / 1024 / 1024;
        throw new Exception("File terlalu besar. Maksimal {$max_size_mb}MB");
    }
    
    if ($file['size'] == 0) {
        throw new Exception('File kosong atau tidak valid');
    }
    
    $original_name = $file['name'];
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    
    if (empty($extension)) {
        throw new Exception('File harus memiliki extension');
    }
    
    if (!in_array($extension, $allowed_types)) {
        throw new Exception('Tipe file tidak diizinkan. Hanya: ' . implode(', ', $allowed_types));
    }
    
    $allowed_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];
    
    if (isset($allowed_mimes[$extension])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if ($mime_type !== $allowed_mimes[$extension]) {
            throw new Exception('Tipe file tidak sesuai dengan extension');
        }
    }
    
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
            throw new Exception('Gagal membuat directory: ' . $directory);
        }
    }
    
    if (!is_writable($directory)) {
        throw new Exception('Directory tidak writable: ' . $directory);
    }
    
    $filename = generateUniqueFilename($original_name, $directory);
    $filepath = $directory . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Gagal memindahkan file ke: ' . $filepath);
    }
    
    if (!file_exists($filepath) || !is_readable($filepath)) {
        throw new Exception('File upload gagal atau tidak bisa dibaca');
    }
    
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $image_info = getimagesize($filepath);
        if ($image_info === false) {
            unlink($filepath);
            throw new Exception('File bukan gambar yang valid');
        }
        
        $min_width = 50;
        $min_height = 50;
        if ($image_info[0] < $min_width || $image_info[1] < $min_height) {
            unlink($filepath);
            throw new Exception("Gambar terlalu kecil. Minimal {$min_width}x{$min_height}px");
        }
    }
    
    return $filename;
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($original_name, $directory) {
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $base_name = pathinfo($original_name, PATHINFO_FILENAME);
    
    $base_name = preg_replace('/[^a-zA-Z0-9-_]/', '', $base_name);
    $base_name = substr($base_name, 0, 50);
    
    $timestamp = time();
    $random = substr(md5(uniqid(rand(), true)), 0, 8);
    $filename = $base_name . '_' . $timestamp . '_' . $random . '.' . $extension;
    
    $counter = 1;
    $original_filename = $filename;
    while (file_exists($directory . $filename)) {
        $filename = pathinfo($original_filename, PATHINFO_FILENAME) . '_' . $counter . '.' . $extension;
        $counter++;
    }
    
    return $filename;
}

/**
 * Delete file safely with validation
 */
function deleteFile($filepath) {
    if (empty($filepath)) {
        return false;
    }
    
    if (!file_exists($filepath) || !is_file($filepath)) {
        return false;
    }
    
    $real_path = realpath($filepath);
    $upload_real_path = realpath(UPLOAD_PATH);
    
    if ($real_path === false || $upload_real_path === false) {
        return false;
    }
    
    if (strpos($real_path, $upload_real_path) !== 0) {
        error_log("Attempted to delete file outside upload directory: " . $filepath);
        return false;
    }
    
    return unlink($filepath);
}

/**
 * Log activity
 */
function logActivity($action, $description, $admin_id = null) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (admin_id, action, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $admin_id ?? ($_SESSION['admin_id'] ?? null),
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

/**
 * Send email using dynamic SMTP settings
 */
function sendEmail($to, $subject, $message, $from = null) {
    $settings = getAppSettings(['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'site_email']);
    
    if (empty($settings['smtp_host']) || empty($settings['smtp_username'])) {
        error_log("SMTP settings not configured");
        return false;
    }
    
    // TODO: Implement email sending using PHPMailer with dynamic settings
    error_log("Email would be sent to: $to, Subject: $subject");
    return true;
}

/**
 * Send WhatsApp message using configured number
 */
function sendWhatsApp($phone, $message) {
    $whatsapp_number = getAppSetting('whatsapp_number', WHATSAPP_NUMBER);
    
    // TODO: Implement WhatsApp API integration
    error_log("WhatsApp would be sent to: $phone, Message: $message");
    return true;
}

/**
 * Format WhatsApp order message with variables
 */
function formatWhatsAppMessage($template, $variables = []) {
    $defaultTemplate = "Halo, saya ingin konfirmasi pesanan dengan nomor: {order_number}";
    $message = $template ?: $defaultTemplate;
    
    foreach ($variables as $key => $value) {
        $message = str_replace('{' . $key . '}', $value, $message);
    }
    
    return $message;
}

// Auto-load additional config files if they exist
$additional_configs = [
    'config-local.php',
    'config-custom.php'
];

foreach ($additional_configs as $config_file) {
    if (file_exists($config_file)) {
        require_once $config_file;
    }
}
?>