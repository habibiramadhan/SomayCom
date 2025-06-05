<?php
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
    case 'create':
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        include 'product/create.php';
        break;
        
    case 'edit':
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        if (!$id) {
            redirectWithMessage('product.php', 'ID produk tidak valid', 'error');
        }
        include 'product/edit.php';
        break;
        
    case 'view':
        if (!$id) {
            redirectWithMessage('product.php', 'ID produk tidak valid', 'error');
        }
        include 'product/view.php';
        break;
        
    case 'process':
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        include 'product/process.php';
        break;
        
    default:
        include 'product/index.php';
        break;
}