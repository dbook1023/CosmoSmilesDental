<?php
// src/Controllers/SiteContentController.php

require_once __DIR__ . '/../../config/database.php';

class SiteContentController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Get all content for a specific page
     */
    public function getContentByPage($page) {
        $query = "SELECT section_key, content_type, content_value FROM site_content WHERE page = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$page]);
        
        $content = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $content[$row['section_key']] = [
                'type' => $row['content_type'],
                'value' => $row['content_value']
            ];
        }
        return $content;
    }

    /**
     * Helper to get a single content value quickly in templates
     */
    public function getValue($page, $sectionKey, $default = '') {
        $query = "SELECT content_value FROM site_content WHERE page = ? AND section_key = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$page, $sectionKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['content_value'] : $default;
    }
    
    /**
     * Helper to get all content map for a page as flat section_key => content_value
     */
    public function getFlatContent($page) {
        $query = "SELECT section_key, content_value FROM site_content WHERE page = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$page]);
        $flat = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $flat[$row['section_key']] = $row['content_value'];
        }
        return $flat;
    }

    /**
     * Update or Insert content
     */
    public function updateContent($page, $sectionKey, $contentType, $contentValue) {
        $query = "INSERT INTO site_content (page, section_key, content_type, content_value) 
                  VALUES (?, ?, ?, ?) 
                  ON DUPLICATE KEY UPDATE content_type = ?, content_value = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            $page, 
            $sectionKey, 
            $contentType, 
            $contentValue,
            $contentType,
            $contentValue
        ]);
    }

    /**
     * Handle Image Uploads
     * Returns the relative path to be saved in DB
     */
    public function uploadImage($fileField, $uploadDir = '/uploads/content/') {
        $fullUploadDir = __DIR__ . '/../../public' . $uploadDir;
        
        // Create directory if it doesn't exist
        if (!file_exists($fullUploadDir)) {
            mkdir($fullUploadDir, 0777, true);
        }

        if (isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES[$fileField]['tmp_name'];
            $name = basename($_FILES[$fileField]['name']);
            // Generate unique filename
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            
            if (in_array($extension, $allowedExtensions)) {
                $newFilename = uniqid('img_') . '.' . $extension;
                $destination = $fullUploadDir . $newFilename;
                
                if (move_uploaded_file($tmpName, $destination)) {
                    return 'public' . $uploadDir . $newFilename;
                }
            }
        }
        return false;
    }

    /**
     * Process form submission for settings
     * Expected $_POST data: ['page' => 'home', 'content' => ['hero_title' => '...', ...]]
     */
    public function processAdminContentUpdate() {
        if (!isset($_SESSION['admin_id'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        // DEBUGGING: Log incoming POST request to help identify dropping fields
        file_put_contents(__DIR__ . '/../../tmp/debug_post.log', "[" . date('Y-m-d H:i:s') . "] POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);

        $page = $_POST['page'] ?? '';
        
        if (empty($page)) {
            return ['success' => false, 'message' => 'No page specified'];
        }

        $successCount = 0;
        $currentContent = $this->getFlatContent($page);
        
        // 1. Process Text/Icon fields
        if (isset($_POST['content']) && is_array($_POST['content'])) {
            error_log("SiteContentController update - received content array: " . print_r($_POST['content'], true));
            foreach ($_POST['content'] as $sectionKey => $value) {
                // Determine type based on key suffix
                $type = 'text';
                if (strpos($sectionKey, 'icon') !== false) {
                    $type = 'icon';
                }

                // ONLY UPDATE if the value has actually changed
                $newValue = (string)$value;
                $oldValue = isset($currentContent[$sectionKey]) ? (string)$currentContent[$sectionKey] : '';

                if ($newValue !== $oldValue) {
                    error_log("Updating {$sectionKey}: '{$oldValue}' -> '{$newValue}'");
                    if ($this->updateContent($page, $sectionKey, $type, $newValue)) {
                        $successCount++;
                    }
                }
            }
        }

        // 2. Process Image uploads
        if (isset($_FILES['images'])) {
            foreach ($_FILES['images']['name'] as $sectionKey => $name) {
                if ($_FILES['images']['error'][$sectionKey] === UPLOAD_ERR_OK) {
                    // Manually reconstruct single file array for uploadImage logic
                    $simulatedFile = [
                        'name' => $_FILES['images']['name'][$sectionKey],
                        'type' => $_FILES['images']['type'][$sectionKey],
                        'tmp_name' => $_FILES['images']['tmp_name'][$sectionKey],
                        'error' => $_FILES['images']['error'][$sectionKey],
                        'size' => $_FILES['images']['size'][$sectionKey]
                    ];
                    $_FILES['temp_upload'] = $simulatedFile;
                    
                    $imagePath = $this->uploadImage('temp_upload');
                    if ($imagePath) {
                        if ($this->updateContent($page, $sectionKey, 'image', $imagePath)) {
                            $successCount++;
                        }
                    }
                }
            }
        }

        return [
            'success' => true, 
            'message' => 'Content updated successfully. Updated ' . $successCount . ' fields.'
        ];
    }
}
