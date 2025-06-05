<!-- admin/settings.php -->
<?php
require_once 'auth_check.php';
hasAccess();

if (!hasPermission('manage_settings')) {
    redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
}

// Get action from URL
$action = $_GET['action'] ?? 'index';

// Route actions
switch ($action) {
    case 'update':
        if (!hasPermission('manage_settings')) {
            redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
        }
        include 'settings/process.php';
        break;
        
    default:
        include 'settings/index.php';
        break;
}