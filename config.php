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
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // Ubah jadi 10MB
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
define('ORDER_EXPIRY_HOURS', 24); // Order expires after 24 hours if not paid

// Security Settings
define('ADMIN_SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 6);

// Email Configuration (if needed later)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');

/**
 * Database Connection Function
 * Returns PDO connection object with error handling
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
            // Log error (in production, don't show actual error)
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * Test Database Connection
 * Returns true if connection successful
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
 * Get App Setting from Database
 * Fallback to default if not found
 */
function getAppSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        $settings = [];
        try {
            $pdo = getDB();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE is_editable = 1");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // If table doesn't exist yet, return empty array
            error_log("Settings table not found: " . $e->getMessage());
        }
    }
    
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Update App Setting in Database
 */
function updateAppSetting($key, $value) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO app_settings (setting_key, setting_value, updated_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value), 
            updated_at = VALUES(updated_at)
        ");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        error_log("Error updating setting: " . $e->getMessage());
        return false;
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
                // Cek apakah parent directory writable
                $parentDir = dirname($dir);
                if (!is_writable($parentDir)) {
                    error_log("Parent directory is not writable: " . $parentDir);
                    continue;
                }
                
                // Coba buat directory dengan permission yang benar
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
 * Create directories and check database connection
 */
function initializeApp() {
    // Set error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Set timezone
    date_default_timezone_set('Asia/Jakarta');
    
    // Test database connection
    if (!testDBConnection()) {
        die("Cannot connect to database. Please check your configuration.");
    }
    
    // Create upload directories
    createDirectories();
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
    $prefix = ORDER_PREFIX;
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
    // Prevent open redirect attacks
    if (strpos($url, 'http') === 0) {
        // Only allow redirects to same domain
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
    // Remove non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a valid Indonesian phone number
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
 * File upload handler
 */
function uploadFile($file, $directory, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp']) {
    // Validasi error upload
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
    
    // Validasi ukuran file
    if ($file['size'] > MAX_FILE_SIZE) {
        $max_size_mb = MAX_FILE_SIZE / 1024 / 1024;
        throw new Exception("File terlalu besar. Maksimal {$max_size_mb}MB");
    }
    
    // Validasi file kosong
    if ($file['size'] == 0) {
        throw new Exception('File kosong atau tidak valid');
    }
    
    // Validasi dan dapatkan extension
    $original_name = $file['name'];
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    
    if (empty($extension)) {
        throw new Exception('File harus memiliki extension');
    }
    
    if (!in_array($extension, $allowed_types)) {
        throw new Exception('Tipe file tidak diizinkan. Hanya: ' . implode(', ', $allowed_types));
    }
    
    // Validasi tipe MIME untuk keamanan
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
    
    // Pastikan directory ada
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
            throw new Exception('Gagal membuat directory: ' . $directory);
        }
    }
    
    // Cek permission directory
    if (!is_writable($directory)) {
        throw new Exception('Directory tidak writable: ' . $directory);
    }
    
    // Generate nama file unik
    $filename = generateUniqueFilename($original_name, $directory);
    $filepath = $directory . $filename;
    
    // Pindahkan file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Gagal memindahkan file ke: ' . $filepath);
    }
    
    // Validasi file berhasil dipindahkan dan bisa dibaca
    if (!file_exists($filepath) || !is_readable($filepath)) {
        throw new Exception('File upload gagal atau tidak bisa dibaca');
    }
    
    // Untuk gambar, validasi tambahan
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $image_info = getimagesize($filepath);
        if ($image_info === false) {
            // Hapus file yang tidak valid
            unlink($filepath);
            throw new Exception('File bukan gambar yang valid');
        }
        
        // Cek dimensi minimum (opsional)
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
    
    // Bersihkan nama file dari karakter tidak aman
    $base_name = preg_replace('/[^a-zA-Z0-9-_]/', '', $base_name);
    $base_name = substr($base_name, 0, 50); // Batasi panjang
    
    // Generate nama dengan timestamp dan random
    $timestamp = time();
    $random = substr(md5(uniqid(rand(), true)), 0, 8);
    $filename = $base_name . '_' . $timestamp . '_' . $random . '.' . $extension;
    
    // Pastikan file tidak ada (double check)
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
    
    // Pastikan file ada dan di dalam directory yang diizinkan
    if (!file_exists($filepath) || !is_file($filepath)) {
        return false;
    }
    
    // Validasi path untuk keamanan (tidak boleh keluar dari upload directory)
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
 * Get file size in human readable format
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Validate image and get info
 */
function validateImage($filepath) {
    if (!file_exists($filepath)) {
        return false;
    }
    
    $image_info = getimagesize($filepath);
    if ($image_info === false) {
        return false;
    }
    
    return [
        'width' => $image_info[0],
        'height' => $image_info[1],
        'type' => $image_info[2],
        'mime' => $image_info['mime'],
        'size' => filesize($filepath),
        'size_formatted' => formatFileSize(filesize($filepath))
    ];
}

/**
 * Resize image (opsional untuk optimasi)
 */
function resizeImage($source_path, $destination_path, $max_width = 800, $max_height = 600, $quality = 85) {
    $image_info = getimagesize($source_path);
    if ($image_info === false) {
        return false;
    }
    
    $original_width = $image_info[0];
    $original_height = $image_info[1];
    $image_type = $image_info[2];
    
    // Hitung dimensi baru dengan mempertahankan aspect ratio
    $ratio = min($max_width / $original_width, $max_height / $original_height);
    $new_width = round($original_width * $ratio);
    $new_height = round($original_height * $ratio);
    
    // Jika sudah kecil, tidak perlu resize
    if ($ratio >= 1) {
        return copy($source_path, $destination_path);
    }
    
    // Buat resource gambar sesuai tipe
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($source_path);
            break;
        case IMAGETYPE_WEBP:
            $source_image = imagecreatefromwebp($source_path);
            break;
        default:
            return false;
    }
    
    if (!$source_image) {
        return false;
    }
    
    // Buat canvas baru
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // Untuk PNG dan GIF, pertahankan transparency
    if ($image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_GIF) {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // Resize
    imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
    
    // Simpan hasil resize
    $result = false;
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($new_image, $destination_path, $quality);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($new_image, $destination_path, 9);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($new_image, $destination_path);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($new_image, $destination_path, $quality);
            break;
    }
    
    // Cleanup
    imagedestroy($source_image);
    imagedestroy($new_image);
    
    return $result;
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
 * Send email (placeholder for future implementation)
 */
function sendEmail($to, $subject, $message, $from = null) {
    // TODO: Implement email sending using PHPMailer or similar
    error_log("Email would be sent to: $to, Subject: $subject");
    return true;
}

/**
 * Send WhatsApp message (placeholder for future implementation)
 */
function sendWhatsApp($phone, $message) {
    // TODO: Implement WhatsApp API integration
    error_log("WhatsApp would be sent to: $phone, Message: $message");
    return true;
}

// Auto-load additional config files if they exist
$additional_configs = [
    'config-local.php',  // For local overrides
    'config-custom.php'  // For custom settings
];

foreach ($additional_configs as $config_file) {
    if (file_exists($config_file)) {
        require_once $config_file;
    }
}
?>