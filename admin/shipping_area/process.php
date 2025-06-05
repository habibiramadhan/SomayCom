<?php
// admin/shipping_area/process.php
// Area process handler
$processAction = $_POST['process_action'] ?? '';

switch ($processAction) {
    case 'create':
        createArea();
        break;
    case 'update':
        updateArea();
        break;
    case 'delete':
        deleteArea();
        break;
    case 'toggle_status':
        toggleAreaStatus();
        break;
    default:
        redirectWithMessage('shipping_area.php', 'Aksi tidak valid', 'error');
}

/**
 * Create new area
 */
function createArea() {
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('shipping_area.php', 'Invalid CSRF token', 'error');
    }
    
    try {
        $pdo = getDB();
        
        // Prepare data
        $data = [
            'area_name' => trim($_POST['area_name'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'shipping_cost' => (float)($_POST['shipping_cost'] ?? 0),
            'estimated_delivery' => trim($_POST['estimated_delivery'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Validate data
        $errors = validateAreaData($data);
        if (!empty($errors)) {
            $_SESSION['form_data'] = $data;
            $_SESSION['form_errors'] = $errors;
            redirectWithMessage('shipping_area.php?action=create', 'Data tidak valid', 'error');
        }
        
        // Create area
        $sql = "
            INSERT INTO shipping_areas (
                area_name, postal_code, shipping_cost, estimated_delivery, is_active
            ) VALUES (?, ?, ?, ?, ?)
        ";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $data['area_name'],
            $data['postal_code'],
            $data['shipping_cost'],
            $data['estimated_delivery'],
            $data['is_active']
        ]);
        
        if ($success) {
            // Log activity
            logActivity('area_create', "Created shipping area: {$data['area_name']}", $_SESSION['admin_id']);
            
            redirectWithMessage('shipping_area.php', 'Area pengiriman berhasil ditambahkan', 'success');
        } else {
            throw new Exception('Failed to create area');
        }
        
    } catch (Exception $e) {
        error_log("Error creating area: " . $e->getMessage());
        $_SESSION['form_data'] = $data ?? [];
        redirectWithMessage('shipping_area.php?action=create', 'Gagal menambahkan area: ' . $e->getMessage(), 'error');
    }
}

/**
 * Update area
 */
function updateArea() {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        redirectWithMessage('shipping_area.php', 'ID area tidak valid', 'error');
    }
    
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('shipping_area.php', 'Invalid CSRF token', 'error');
    }
    
    try {
        $pdo = getDB();
        
        // Get current area
        $stmt = $pdo->prepare("SELECT * FROM shipping_areas WHERE id = ?");
        $stmt->execute([$id]);
        $currentArea = $stmt->fetch();
        
        if (!$currentArea) {
            redirectWithMessage('shipping_area.php', 'Area tidak ditemukan', 'error');
        }
        
        // Prepare data
        $data = [
            'area_name' => trim($_POST['area_name'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'shipping_cost' => (float)($_POST['shipping_cost'] ?? 0),
            'estimated_delivery' => trim($_POST['estimated_delivery'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Validate data
        $errors = validateAreaData($data, $id);
        if (!empty($errors)) {
            $_SESSION['form_data'] = $data;
            $_SESSION['form_errors'] = $errors;
            redirectWithMessage("shipping_area.php?action=edit&id=$id", 'Data tidak valid', 'error');
        }
        
        // Update area
        $sql = "
            UPDATE shipping_areas SET
                area_name = ?, postal_code = ?, shipping_cost = ?, 
                estimated_delivery = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $data['area_name'],
            $data['postal_code'],
            $data['shipping_cost'],
            $data['estimated_delivery'],
            $data['is_active'],
            $id
        ]);
        
        if ($success) {
            // Log activity
            logActivity('area_update', "Updated shipping area: {$data['area_name']}", $_SESSION['admin_id']);
            
            redirectWithMessage('shipping_area.php', 'Area pengiriman berhasil diupdate', 'success');
        } else {
            throw new Exception('Failed to update area');
        }
        
    } catch (Exception $e) {
        error_log("Error updating area: " . $e->getMessage());
        $_SESSION['form_data'] = $data ?? [];
        redirectWithMessage("shipping_area.php?action=edit&id=$id", 'Gagal mengupdate area: ' . $e->getMessage(), 'error');
    }
}

/**
 * Delete area
 */
function deleteArea() {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        redirectWithMessage('shipping_area.php', 'ID area tidak valid', 'error');
    }
    
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('shipping_area.php', 'Invalid CSRF token', 'error');
    }
    
    try {
        $pdo = getDB();
        
        // Get area info
        $stmt = $pdo->prepare("SELECT * FROM shipping_areas WHERE id = ?");
        $stmt->execute([$id]);
        $area = $stmt->fetch();
        
        if (!$area) {
            redirectWithMessage('shipping_area.php', 'Area tidak ditemukan', 'error');
        }
        
        // Check if area is used in orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shipping_area_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            redirectWithMessage('shipping_area.php', 'Area tidak dapat dihapus karena sudah digunakan dalam pesanan', 'error');
        }
        
        // Delete area
        $stmt = $pdo->prepare("DELETE FROM shipping_areas WHERE id = ?");
        $success = $stmt->execute([$id]);
        
        if ($success) {
            // Log activity
            logActivity('area_delete', "Deleted shipping area: {$area['area_name']}", $_SESSION['admin_id']);
            
            redirectWithMessage('shipping_area.php', 'Area pengiriman berhasil dihapus', 'success');
        } else {
            throw new Exception('Failed to delete area');
        }
        
    } catch (Exception $e) {
        error_log("Error deleting area: " . $e->getMessage());
        redirectWithMessage('shipping_area.php', 'Gagal menghapus area: ' . $e->getMessage(), 'error');
    }
}

/**
 * Toggle area status via AJAX
 */
function toggleAreaStatus() {
    header('Content-Type: application/json');
    
    $id = (int)($_POST['id'] ?? 0);
    $isActive = $_POST['is_active'] === 'true' ? 1 : 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID area tidak valid']);
        exit;
    }
    
    try {
        $pdo = getDB();
        
        // Get current area
        $stmt = $pdo->prepare("SELECT * FROM shipping_areas WHERE id = ?");
        $stmt->execute([$id]);
        $area = $stmt->fetch();
        
        if (!$area) {
            echo json_encode(['success' => false, 'message' => 'Area tidak ditemukan']);
            exit;
        }
        
        // Update status
        $stmt = $pdo->prepare("UPDATE shipping_areas SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $success = $stmt->execute([$isActive, $id]);
        
        if ($success) {
            $action = $isActive ? 'activated' : 'deactivated';
            logActivity('area_toggle', "Area {$area['area_name']} $action", $_SESSION['admin_id']);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Status area berhasil diubah'
            ]);
        } else {
            throw new Exception('Failed to update area status');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * Validate area data
 */
function validateAreaData($data, $excludeId = null) {
    $errors = [];
    $pdo = getDB();
    
    // Required fields
    if (empty($data['area_name'])) {
        $errors[] = 'Nama area harus diisi';
    }
    
    if (empty($data['shipping_cost']) || $data['shipping_cost'] < 0) {
        $errors[] = 'Ongkos kirim harus diisi dan tidak boleh negatif';
    }
    
    // Check unique area name
    if (!empty($data['area_name'])) {
        $where = "area_name = ?";
        $params = [$data['area_name']];
        
        if ($excludeId) {
            $where .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM shipping_areas WHERE $where");
        $stmt->execute($params);
        
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Nama area sudah digunakan';
        }
    }
    
    // Validate postal code format (optional)
    if (!empty($data['postal_code']) && !preg_match('/^\d{5}$/', $data['postal_code'])) {
        $errors[] = 'Kode pos harus 5 digit angka';
    }
    
    return $errors;
}
?>