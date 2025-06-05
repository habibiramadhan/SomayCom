<!-- admin/settings/backup_restore.php -->
<?php
require_once '../auth_check.php';
require_once '../models/Settings.php';

hasAccess();

if (!hasPermission('manage_settings')) {
    redirectWithMessage('dashboard.php', 'Akses ditolak', 'error');
}

$action = $_GET['action'] ?? '';
$settingsModel = new Settings();

switch ($action) {
    case 'backup':
        backupSettings();
        break;
        
    case 'restore':
        restoreSettings();
        break;
        
    case 'reset':
        resetSettings();
        break;
        
    default:
        redirectWithMessage('settings.php', 'Aksi tidak valid', 'error');
}

/**
 * Backup settings to JSON file
 */
function backupSettings() {
    global $settingsModel;
    
    try {
        $settings = $settingsModel->exportSettings();
        $backup_data = [
            'version' => '1.0',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['admin_name'],
            'settings' => $settings
        ];
        
        $filename = 'somay_settings_backup_' . date('Y-m-d_H-i-s') . '.json';
        $json_data = json_encode($backup_data, JSON_PRETTY_PRINT);
        
        // Send file as download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json_data));
        
        echo $json_data;
        exit;
        
    } catch (Exception $e) {
        error_log("Error backing up settings: " . $e->getMessage());
        redirectWithMessage('settings.php', 'Gagal membuat backup: ' . $e->getMessage(), 'error');
    }
}

/**
 * Restore settings from uploaded JSON file
 */
function restoreSettings() {
    global $settingsModel;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirectWithMessage('settings.php', 'Method not allowed', 'error');
    }
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('settings.php', 'Invalid CSRF token', 'error');
    }
    
    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        redirectWithMessage('settings.php', 'File backup tidak valid', 'error');
    }
    
    try {
        $file_content = file_get_contents($_FILES['backup_file']['tmp_name']);
        $backup_data = json_decode($file_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('File backup tidak valid (JSON error)');
        }
        
        if (!isset($backup_data['settings']) || !is_array($backup_data['settings'])) {
            throw new Exception('Format file backup tidak valid');
        }
        
        // Validate settings before importing
        $valid_settings = [];
        foreach ($backup_data['settings'] as $key => $setting) {
            if (is_array($setting) && isset($setting['value'], $setting['type'])) {
                $valid_settings[$key] = $setting;
            }
        }
        
        if (empty($valid_settings)) {
            throw new Exception('Tidak ada pengaturan valid dalam file backup');
        }
        
        // Import settings
        $settingsModel->importSettings($valid_settings);
        
        // Log activity
        logActivity('settings_restore', 'Restored settings from backup file', $_SESSION['admin_id']);
        
        redirectWithMessage('settings.php', 'Pengaturan berhasil dipulihkan dari backup', 'success');
        
    } catch (Exception $e) {
        error_log("Error restoring settings: " . $e->getMessage());
        redirectWithMessage('settings.php', 'Gagal memulihkan pengaturan: ' . $e->getMessage(), 'error');
    }
}

/**
 * Reset settings to default values
 */
function resetSettings() {
    global $settingsModel;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirectWithMessage('settings.php', 'Method not allowed', 'error');
    }
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('settings.php', 'Invalid CSRF token', 'error');
    }
    
    try {
        $settingsModel->resetToDefaults();
        
        // Log activity
        logActivity('settings_reset', 'Reset all settings to default values', $_SESSION['admin_id']);
        
        redirectWithMessage('settings.php', 'Pengaturan berhasil direset ke nilai default', 'success');
        
    } catch (Exception $e) {
        error_log("Error resetting settings: " . $e->getMessage());
        redirectWithMessage('settings.php', 'Gagal mereset pengaturan: ' . $e->getMessage(), 'error');
    }
}