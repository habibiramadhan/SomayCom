<?php
// admin/shipping_area.php
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
        include 'shipping_area/create.php';
        break;
        
    case 'edit':
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        if (!$id) {
            redirectWithMessage('shipping_area.php', 'ID area tidak valid', 'error');
        }
        include 'shipping_area/edit.php';
        break;
        
    case 'view':
        if (!$id) {
            redirectWithMessage('shipping_area.php', 'ID area tidak valid', 'error');
        }
        include 'shipping_area/view.php';
        break;
        
    case 'process':
        if (!hasPermission('manage_products')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        include 'shipping_area/process.php';
        break;
        
    default:
        include 'shipping_area/index.php';
        break;
}