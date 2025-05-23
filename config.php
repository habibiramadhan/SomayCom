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
define('SITE_URL', 'http://localhost/somay-pos');
define('ADMIN_URL', SITE_URL . '/admin');

// Path Configuration
define('UPLOAD_PATH', 'uploads/');
define('PRODUCT_IMAGE_PATH', UPLOAD_PATH . 'products/');
define('PAYMENT_PROOF_PATH', UPLOAD_PATH . 'payments/');

// File Upload Settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
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