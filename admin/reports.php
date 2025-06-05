<!-- admin/reports.php -->
<?php
require_once 'auth_check.php';
hasAccess();

if (!hasPermission('view_reports')) {
    redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
}

// Get action from URL
$action = $_GET['action'] ?? 'index';

// Route actions
switch ($action) {
    case 'sales':
        include 'reports/sales.php';
        break;
        
    case 'products':
        include 'reports/products.php';
        break;
        
    case 'customers':
        include 'reports/customers.php';
        break;
        
    case 'export':
        include 'reports/export.php';
        break;
        
    default:
        include 'reports/index.php';
        break;
}