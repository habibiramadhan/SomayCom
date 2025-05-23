<?php
session_start();
require_once '../config.php';

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
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Hapus semua session
session_unset();
session_destroy();

// Redirect ke halaman login
header('Location: login.php');
exit(); 