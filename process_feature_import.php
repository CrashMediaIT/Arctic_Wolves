<?php
/**
 * Process Feature Import
 * Handles feature package upload and import execution
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/admin/feature_importer.php';

setSecurityHeaders();

// Only admins can import features
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'error' => 'Access denied. Admin privileges required.'
    ]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'get_installed_versions':
                // Get list of installed feature versions
                $importer = new FeatureImporter($pdo, __DIR__);
                $versions = $importer->getInstalledVersions();
                
                echo json_encode([
                    'success' => true,
                    'versions' => $versions
                ]);
                break;
                
            case 'import_feature':
                // Check if file was uploaded
                if (!isset($_FILES['feature_package']) || $_FILES['feature_package']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('No file uploaded or upload error occurred');
                }
                
                $file = $_FILES['feature_package'];
                
                // Validate file type
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($file_ext !== 'zip') {
                    throw new Exception('Invalid file type. Only ZIP files are allowed');
                }
                
                // Validate file size (max 50MB)
                $max_size = 50 * 1024 * 1024; // 50MB
                if ($file['size'] > $max_size) {
                    throw new Exception('File too large. Maximum size is 50MB');
                }
                
                // Move uploaded file to temporary location
                $upload_dir = __DIR__ . '/tmp/feature_imports';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $temp_file = $upload_dir . '/' . uniqid('upload_') . '.zip';
                if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
                    throw new Exception('Failed to save uploaded file');
                }
                
                // Initialize importer and run import
                $importer = new FeatureImporter($pdo, __DIR__);
                $result = $importer->importFeature($temp_file);
                
                // Clean up uploaded file
                if (file_exists($temp_file)) {
                    unlink($temp_file);
                }
                
                // Return result
                echo json_encode($result);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'log' => [
                [
                    'message' => 'Error: ' . $e->getMessage(),
                    'type' => 'error',
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]
        ]);
    }
    
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}
