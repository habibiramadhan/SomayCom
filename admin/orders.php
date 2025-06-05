<!-- admin/orders.php -->
<?php
require_once 'auth_check.php';
hasAccess();

if (!hasPermission('view_orders')) {
    redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
}

// Get action from URL
$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

// Route actions
switch ($action) {
    case 'view':
        if (!$id) {
            redirectWithMessage('orders.php', 'ID pesanan tidak valid', 'error');
        }
        include 'orders/view.php';
        break;
        
    case 'update_status':
        if (!hasPermission('manage_orders')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        include 'orders/process.php';
        break;
        
    case 'process':
        if (!hasPermission('manage_orders')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        include 'orders/process.php';
        break;
        
    default:
        include 'orders/index.php';
        break;
}