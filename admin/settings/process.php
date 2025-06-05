<!-- admin/settings/process.php -->
<?php
// Settings process handler

// Validate CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    redirectWithMessage('settings.php', 'Invalid CSRF token', 'error');
}

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('settings.php', 'Method not allowed', 'error');
}

try {
    $pdo = getDB();
    
    // Define settings to update
    $settingsToUpdate = [
        'site_name' => 'string',
        'site_phone' => 'string',
        'site_email' => 'string',
        'site_address' => 'string',
        'min_order_amount' => 'number',
        'free_shipping_min' => 'number',
        'order_prefix' => 'string',
        'order_expiry_hours' => 'number',
        'whatsapp_number' => 'string',
        'whatsapp_order_message' => 'string',
        'meta_title' => 'string',
        'meta_description' => 'string',
        'meta_keywords' => 'string',
        'smtp_host' => 'string',
        'smtp_port' => 'number',
        'smtp_username' => 'string',
        'smtp_password' => 'string',
        'facebook_url' => 'string',
        'instagram_url' => 'string',
        'twitter_url' => 'string'
    ];
    
    // Validate data
    $errors = [];
    $validatedData = [];
    
    foreach ($settingsToUpdate as $key => $type) {
        $value = $_POST[$key] ?? '';
        
        // Skip empty password fields
        if ($key === 'smtp_password' && empty($value)) {
            continue;
        }
        
        // Required fields validation
        $requiredFields = ['site_name', 'site_phone', 'site_email', 'min_order_amount', 'free_shipping_min', 'order_prefix', 'order_expiry_hours', 'whatsapp_number'];
        if (in_array($key, $requiredFields) && empty($value)) {
            $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' harus diisi';
            continue;
        }
        
        // Type-specific validation
        switch ($type) {
            case 'number':
                if (!empty($value) && !is_numeric($value)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' harus berupa angka';
                    continue 2;
                }
                $value = (float)$value;
                break;
                
            case 'string':
                $value = trim($value);
                break;
        }
        
        // Field-specific validation
        switch ($key) {
            case 'site_email':
            case 'smtp_username':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' harus berupa email valid';
                    continue 2;
                }
                break;
                
            case 'whatsapp_number':
                if (!empty($value) && !preg_match('/^62[0-9]{8,13}$/', $value)) {
                    $errors[] = 'Nomor WhatsApp harus format internasional (62xxxxxxxxx)';
                    continue 2;
                }
                break;
                
            case 'facebook_url':
            case 'instagram_url':
            case 'twitter_url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' harus berupa URL valid';
                    continue 2;
                }
                break;
                
            case 'min_order_amount':
                if ($value <= 0) {
                    $errors[] = 'Minimal pembelian harus lebih dari 0';
                    continue 2;
                }
                break;
                
            case 'free_shipping_min':
                if ($value <= 0) {
                    $errors[] = 'Minimal gratis ongkir harus lebih dari 0';
                    continue 2;
                }
                break;
                
            case 'order_expiry_hours':
                if ($value < 1 || $value > 168) {
                    $errors[] = 'Kadaluarsa order harus antara 1-168 jam';
                    continue 2;
                }
                break;
                
            case 'smtp_port':
                if (!empty($value) && ($value < 1 || $value > 65535)) {
                    $errors[] = 'SMTP Port harus antara 1-65535';
                    continue 2;
                }
                break;
        }
        
        $validatedData[$key] = $value;
    }
    
    // Business logic validation
    if (isset($validatedData['free_shipping_min']) && isset($validatedData['min_order_amount'])) {
        if ($validatedData['free_shipping_min'] <= $validatedData['min_order_amount']) {
            $errors[] = 'Minimal gratis ongkir harus lebih besar dari minimal pembelian';
        }
    }
    
    // If there are errors, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_errors'] = $errors;
        redirectWithMessage('settings.php', 'Data tidak valid', 'error');
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Update settings one by one
        $updateStmt = $pdo->prepare("
            INSERT INTO app_settings (setting_key, setting_value, setting_type, updated_at) 
            VALUES (?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value), 
            updated_at = VALUES(updated_at)
        ");
        
        foreach ($validatedData as $key => $value) {
            $type = $settingsToUpdate[$key];
            $updateStmt->execute([$key, $value, $type]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Log activity
        logActivity('settings_update', 'Updated application settings', $_SESSION['admin_id']);
        
        // Clear any cached settings
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        redirectWithMessage('settings.php', 'Pengaturan berhasil disimpan', 'success');
        
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error updating settings: " . $e->getMessage());
    $_SESSION['form_data'] = $_POST;
    redirectWithMessage('settings.php', 'Gagal menyimpan pengaturan: ' . $e->getMessage(), 'error');
}