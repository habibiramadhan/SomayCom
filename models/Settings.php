<!-- models/Settings.php -->
<?php
class Settings {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDB();
    }
    
    /**
     * Get all settings
     */
    public function getAllSettings() {
        $stmt = $this->pdo->prepare("SELECT * FROM app_settings ORDER BY setting_key");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get settings as key-value pairs
     */
    public function getSettingsArray() {
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM app_settings");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    /**
     * Get editable settings only
     */
    public function getEditableSettings() {
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM app_settings WHERE is_editable = 1");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    /**
     * Get single setting value
     */
    public function getSetting($key, $default = null) {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    }
    
    /**
     * Update single setting
     */
    public function updateSetting($key, $value, $type = 'string') {
        $stmt = $this->pdo->prepare("
            INSERT INTO app_settings (setting_key, setting_value, setting_type, updated_at) 
            VALUES (?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value), 
            updated_at = VALUES(updated_at)
        ");
        
        return $stmt->execute([$key, $value, $type]);
    }
    
    /**
     * Update multiple settings
     */
    public function updateMultipleSettings($settings) {
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO app_settings (setting_key, setting_value, setting_type, updated_at) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value), 
                updated_at = VALUES(updated_at)
            ");
            
            foreach ($settings as $key => $data) {
                $value = $data['value'];
                $type = $data['type'] ?? 'string';
                $stmt->execute([$key, $value, $type]);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * Delete setting
     */
    public function deleteSetting($key) {
        $stmt = $this->pdo->prepare("DELETE FROM app_settings WHERE setting_key = ?");
        return $stmt->execute([$key]);
    }
    
    /**
     * Create setting if not exists
     */
    public function createSetting($key, $value, $type = 'string', $description = '', $isEditable = true) {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO app_settings (setting_key, setting_value, setting_type, description, is_editable, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        return $stmt->execute([$key, $value, $type, $description, $isEditable ? 1 : 0]);
    }
    
    /**
     * Get settings by type
     */
    public function getSettingsByType($type) {
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_type = ?");
        $stmt->execute([$type]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    /**
     * Validate setting value based on type
     */
    public function validateSetting($key, $value, $type) {
        switch ($type) {
            case 'number':
                return is_numeric($value);
                
            case 'boolean':
                return in_array(strtolower($value), ['0', '1', 'true', 'false', 'yes', 'no']);
                
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
                
            case 'json':
                json_decode($value);
                return json_last_error() === JSON_ERROR_NONE;
                
            case 'string':
            default:
                return is_string($value);
        }
    }
    
    /**
     * Get default settings structure
     */
    public function getDefaultSettings() {
        return [
            'site_name' => [
                'value' => 'Somay Ecommerce',
                'type' => 'string',
                'description' => 'Nama website',
                'is_editable' => true
            ],
            'site_phone' => [
                'value' => '081234567890',
                'type' => 'string',
                'description' => 'Nomor telepon toko',
                'is_editable' => true
            ],
            'site_email' => [
                'value' => 'info@somay.com',
                'type' => 'string',
                'description' => 'Email toko',
                'is_editable' => true
            ],
            'site_address' => [
                'value' => 'Tangerang Selatan, Banten',
                'type' => 'string',
                'description' => 'Alamat toko',
                'is_editable' => true
            ],
            'min_order_amount' => [
                'value' => '25000',
                'type' => 'number',
                'description' => 'Minimal pembelian',
                'is_editable' => true
            ],
            'free_shipping_min' => [
                'value' => '100000',
                'type' => 'number',
                'description' => 'Minimal gratis ongkir',
                'is_editable' => true
            ],
            'order_prefix' => [
                'value' => 'ORD',
                'type' => 'string',
                'description' => 'Prefix nomor order',
                'is_editable' => true
            ],
            'order_expiry_hours' => [
                'value' => '24',
                'type' => 'number',
                'description' => 'Jam kadaluarsa order',
                'is_editable' => true
            ],
            'whatsapp_number' => [
                'value' => '6281234567890',
                'type' => 'string',
                'description' => 'Nomor WhatsApp customer service',
                'is_editable' => true
            ],
            'whatsapp_order_message' => [
                'value' => 'Halo, saya ingin konfirmasi pesanan dengan nomor: {order_number}',
                'type' => 'string',
                'description' => 'Template pesan WhatsApp order',
                'is_editable' => true
            ],
            'meta_title' => [
                'value' => 'Somay Ecommerce - Distributor Somay Terlengkap',
                'type' => 'string',
                'description' => 'Meta title untuk SEO',
                'is_editable' => true
            ],
            'meta_description' => [
                'value' => 'Distributor somay dan siomay terpercaya dengan pengiriman cepat se-Tangerang Selatan',
                'type' => 'string',
                'description' => 'Meta description untuk SEO',
                'is_editable' => true
            ],
            'meta_keywords' => [
                'value' => 'somay, siomay, distributor, tangerang selatan',
                'type' => 'string',
                'description' => 'Meta keywords untuk SEO',
                'is_editable' => true
            ],
            'smtp_host' => [
                'value' => 'smtp.gmail.com',
                'type' => 'string',
                'description' => 'SMTP Host untuk email',
                'is_editable' => true
            ],
            'smtp_port' => [
                'value' => '587',
                'type' => 'number',
                'description' => 'SMTP Port',
                'is_editable' => true
            ],
            'smtp_username' => [
                'value' => '',
                'type' => 'string',
                'description' => 'SMTP Username',
                'is_editable' => true
            ],
            'smtp_password' => [
                'value' => '',
                'type' => 'string',
                'description' => 'SMTP Password',
                'is_editable' => true
            ],
            'facebook_url' => [
                'value' => '',
                'type' => 'string',
                'description' => 'URL Facebook',
                'is_editable' => true
            ],
            'instagram_url' => [
                'value' => '',
                'type' => 'string',
                'description' => 'URL Instagram',
                'is_editable' => true
            ],
            'twitter_url' => [
                'value' => '',
                'type' => 'string',
                'description' => 'URL Twitter',
                'is_editable' => true
            ]
        ];
    }
    
    /**
     * Initialize default settings
     */
    public function initializeDefaultSettings() {
        $defaults = $this->getDefaultSettings();
        
        foreach ($defaults as $key => $setting) {
            $this->createSetting(
                $key,
                $setting['value'],
                $setting['type'],
                $setting['description'],
                $setting['is_editable']
            );
        }
    }
    
    /**
     * Export settings to array
     */
    public function exportSettings() {
        $settings = $this->getAllSettings();
        $export = [];
        
        foreach ($settings as $setting) {
            $export[$setting['setting_key']] = [
                'value' => $setting['setting_value'],
                'type' => $setting['setting_type'],
                'description' => $setting['description'],
                'is_editable' => $setting['is_editable']
            ];
        }
        
        return $export;
    }
    
    /**
     * Import settings from array
     */
    public function importSettings($settingsArray) {
        $this->pdo->beginTransaction();
        
        try {
            foreach ($settingsArray as $key => $setting) {
                $this->updateSetting($key, $setting['value'], $setting['type']);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * Reset settings to default
     */
    public function resetToDefaults() {
        $this->pdo->beginTransaction();
        
        try {
            // Delete all existing settings
            $this->pdo->exec("DELETE FROM app_settings WHERE is_editable = 1");
            
            // Reinitialize with defaults
            $this->initializeDefaultSettings();
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
}