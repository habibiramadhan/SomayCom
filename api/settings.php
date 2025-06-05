<!-- api/settings.php -->
<?php
/**
 * Settings API for frontend use
 * Provides public settings that can be used by the frontend
 */

require_once '../config.php';

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get public settings only (non-sensitive)
    $publicSettings = [
        'site_name',
        'site_phone',
        'site_email', 
        'site_address',
        'whatsapp_number',
        'min_order_amount',
        'free_shipping_min',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'facebook_url',
        'instagram_url',
        'twitter_url'
    ];
    
    $settings = getAppSettings($publicSettings);
    
    // Add some computed values
    $settings['formatted_min_order'] = formatRupiah($settings['min_order_amount'] ?? 0);
    $settings['formatted_free_shipping'] = formatRupiah($settings['free_shipping_min'] ?? 0);
    $settings['whatsapp_url'] = 'https://wa.me/' . ($settings['whatsapp_number'] ?? '');
    
    // Add current timestamp
    $settings['timestamp'] = date('c');
    $settings['timezone'] = date_default_timezone_get();
    
    echo json_encode([
        'success' => true,
        'data' => $settings
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Settings API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
?>