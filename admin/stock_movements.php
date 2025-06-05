<?php
// admin/stock_movements.php
require_once 'auth_check.php';
hasAccess();

if (!hasPermission('view_products')) {
    redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
}

// Get action from URL
$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

// Route actions
switch ($action) {
    case 'export':
        include 'stock_movements/export.php';
        break;
        
    case 'view':
        if (!$id) {
            redirectWithMessage('stock_movements.php', 'ID movement tidak valid', 'error');
        }
        include 'stock_movements/view.php';
        break;
        
    default:
        include 'stock_movements/index.php';
        break;
}