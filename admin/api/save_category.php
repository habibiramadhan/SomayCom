<?php
require_once '../auth_check.php';
hasAccess();

header('Content-Type: application/json');

try {
    // Get JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['name']) || !isset($data['slug'])) {
        throw new Exception('Data tidak lengkap');
    }

    $pdo = getDB();
    
    // Check if slug is unique
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
    $stmt->execute([$data['slug'], $data['id'] ?? 0]);
    if ($stmt->fetch()) {
        throw new Exception('Slug sudah digunakan');
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        if (isset($data['id'])) {
            // Update existing category
            $stmt = $pdo->prepare("
                UPDATE categories 
                SET name = ?,
                    slug = ?,
                    description = ?,
                    is_active = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['slug'],
                $data['description'] ?? null,
                $data['is_active'],
                $data['id']
            ]);
            $message = 'Kategori berhasil diperbarui';
        } else {
            // Insert new category
            $stmt = $pdo->prepare("
                INSERT INTO categories (name, slug, description, is_active)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['slug'],
                $data['description'] ?? null,
                $data['is_active']
            ]);
            $message = 'Kategori berhasil ditambahkan';
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => $message]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 