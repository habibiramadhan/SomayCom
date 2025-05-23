<?php
// Pastikan session dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

/**
 * Fungsi untuk mengecek apakah user sudah login
 */
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Fungsi untuk mengecek role admin
 */
function hasRole($role) {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === $role;
}

/**
 * Fungsi untuk mengecek apakah user memiliki akses ke halaman tertentu
 */
function hasAccess($requiredRole = null) {
    // Cek apakah sudah login
    if (!isLoggedIn()) {
        // Redirect ke login jika belum login
        header('Location: login.php');
        exit();
    }

    // Cek session timeout (opsional)
    if (isset($_SESSION['admin_login_time'])) {
        $timeout = ADMIN_SESSION_TIMEOUT ?? 3600; // Default 1 jam
        if (time() - $_SESSION['admin_login_time'] > $timeout) {
            // Session expired
            session_unset();
            session_destroy();
            header('Location: login.php?expired=1');
            exit();
        }
    }

    // Cek role jika diperlukan
    if ($requiredRole && !hasRole($requiredRole)) {
        // Redirect ke dashboard jika role tidak sesuai
        header('Location: dashboard.php?access_denied=1');
        exit();
    }

    return true;
}

/**
 * Fungsi untuk mendapatkan info admin yang sedang login
 */
function getCurrentAdmin() {
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'],
        'name' => $_SESSION['admin_name'],
        'role' => $_SESSION['admin_role']
    ];
}

/**
 * Fungsi untuk logout
 */
function logout() {
    // Hapus remember token dari database jika ada
    if (isset($_SESSION['admin_id'])) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("UPDATE admins SET remember_token = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
        }
    }

    // Hapus cookie remember me
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }

    // Hapus semua session
    session_unset();
    session_destroy();
    
    // Redirect ke halaman login
    header('Location: login.php');
    exit();
}

// Cek remember me token jika belum login
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE remember_token = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$_COOKIE['remember_token']]);
        $admin = $stmt->fetch();

        if ($admin) {
            // Set session
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_login_time'] = time();

            // Update last login
            $stmt = $pdo->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$admin['id']]);
        } else {
            // Hapus cookie jika token tidak valid
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
    } catch (Exception $e) {
        error_log("Remember me error: " . $e->getMessage());
        // Hapus cookie jika terjadi error
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
}

/**
 * Fungsi untuk mengecek permission berdasarkan role
 */
function hasPermission($action) {
    if (!isLoggedIn()) {
        return false;
    }

    $role = $_SESSION['admin_role'];
    
    // Super admin bisa melakukan semua
    if ($role === 'super_admin') {
        return true;
    }

    // Definisi permission berdasarkan role
    $permissions = [
        'admin' => [
            'view_dashboard',
            'manage_products',
            'manage_orders',
            'manage_categories',
            'manage_customers',
            'view_reports'
        ],
        'operator' => [
            'view_dashboard',
            'view_products',
            'manage_orders',
            'view_customers'
        ]
    ];

    return in_array($action, $permissions[$role] ?? []);
}

/**
 * Fungsi untuk redirect dengan pesan
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit();
}

/**
 * Fungsi untuk menampilkan flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return ['message' => $message, 'type' => $type];
    }
    
    return null;
}

/**
 * Fungsi untuk generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Fungsi untuk verifikasi CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>