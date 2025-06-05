<?php
// admin/category.php
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
        include 'category/create.php';
        break;
        
    case 'edit':
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        if (!$id) {
            redirectWithMessage('category.php', 'ID kategori tidak valid', 'error');
        }
        include 'category/edit.php';
        break;
        
    case 'view':
        if (!$id) {
            redirectWithMessage('category.php', 'ID kategori tidak valid', 'error');
        }
        include 'category/view.php';
        break;
        
    case 'process':
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        include 'category/process.php';
        break;
        
    default:
        include 'category/index.php';
        break;
}