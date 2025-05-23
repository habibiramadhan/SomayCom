<?php
session_start();
require_once '../config.php';

// Fungsi untuk mengecek apakah user sudah login
function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

// Fungsi untuk mengecek role admin
function hasRole($role) {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === $role;
}

// Fungsi untuk mengecek apakah user memiliki akses ke halaman tertentu
function hasAccess($requiredRole = null) {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }

    if ($requiredRole && !hasRole($requiredRole)) {
        header('Location: dashboard.php');
        exit();
    }
}

// Cek remember me token
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

            // Update last login
            $stmt = $pdo->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$admin['id']]);
        } else {
            // Hapus cookie jika token tidak valid
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    } catch (Exception $e) {
        error_log("Remember me error: " . $e->getMessage());
    }
} 