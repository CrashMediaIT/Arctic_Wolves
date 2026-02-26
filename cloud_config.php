<?php
/**
 * Cloud Storage Configuration
 * Provides functions for file storage using RustFS S3.
 * All uploads are stored in RustFS S3 — zero local file storage.
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/lib/rustfs_storage.php';

/**
 * Upload termination documents to RustFS S3 storage
 * Creates Year/Month/StaffName folder structure
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Deprecated/ignored — RustFS settings are loaded internally
 * @param string $staff_name Full name of the staff member
 * @param string $termination_date Date in Y-m-d format
 * @param array $files Array of uploaded files ($_FILES format)
 * @return array Array of uploaded file paths
 */
function uploadTerminationDocuments($pdo, $settings, $staff_name, $termination_date, $files) {
    $uploaded_paths = [];
    
    try {
        $rustfs = getRustFSSettings($pdo);
        
        // Parse date for Year/Month folders
        $date = new DateTime($termination_date);
        $year = $date->format('Y');
        $month = $date->format('m');
        
        // Sanitize staff name for folder name
        $safe_staff_name = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $staff_name);
        $safe_staff_name = str_replace(' ', '_', trim($safe_staff_name));
        
        // Handle file uploads
        if (!empty($files['name'][0])) {
            $file_count = count($files['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $original_name = basename($files['name'][$i]);
                    $tmp_path = $files['tmp_name'][$i];
                    $content_type = $files['type'][$i] ?? 'application/octet-stream';
                    
                    // Sanitize filename
                    $safe_filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $original_name);

                    // Upload to RustFS
                    $object_key = 'HR/Terminations/' . $year . '/' . $month . '/' . $safe_staff_name . '/' . $safe_filename;
                    $rustfs_url = null;

                    if (isRustFSConfigured($rustfs)) {
                        $result = uploadToRustFS($rustfs, $tmp_path, $object_key, $content_type);
                        if ($result['success']) {
                            $rustfs_url = $result['url'];
                        }
                    }

                    $uploaded_paths[] = [
                        'original_name' => $original_name,
                        'remote_path' => $rustfs_url ?? $object_key,
                        'file_size' => filesize($tmp_path),
                        'content_type' => $content_type
                    ];
                    
                    // Also upload to Paperless-NGX with Termination tag
                    $title = 'Termination_' . $safe_staff_name . '_' . $date->format('Y-m-d') . '_' . $safe_filename;
                    uploadToPaperless($pdo, $tmp_path, 'Termination', $title);
                }
            }
        }
        
        return [
            'success' => true,
            'folder_path' => 'HR/Terminations/' . $year . '/' . $month . '/' . $safe_staff_name,
            'uploaded_files' => $uploaded_paths
        ];
        
    } catch (Exception $e) {
        error_log("Error uploading termination documents: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'uploaded_files' => $uploaded_paths
        ];
    }
}

/**
 * Export termination data to RustFS S3 as a text/JSON file
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Deprecated/ignored — RustFS settings are loaded internally
 * @param array $termination_data Termination form data
 * @param string $staff_name Full name of the staff member
 * @param string $termination_date Date in Y-m-d format
 * @return array Result with success status and file path
 */
function exportTerminationData($pdo, $settings, $termination_data, $staff_name, $termination_date) {
    try {
        $rustfs = getRustFSSettings($pdo);
        
        // Parse date for Year/Month folders
        $date = new DateTime($termination_date);
        $year = $date->format('Y');
        $month = $date->format('m');
        
        // Sanitize staff name for folder name
        $safe_staff_name = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $staff_name);
        $safe_staff_name = str_replace(' ', '_', trim($safe_staff_name));
        
        $folder_key = 'HR/Terminations/' . $year . '/' . $month . '/' . $safe_staff_name;
        
        // Create termination summary document
        $summary_content = "EMPLOYEE TERMINATION RECORD\n";
        $summary_content .= "===========================\n\n";
        $summary_content .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $summary_content .= "EMPLOYEE INFORMATION\n";
        $summary_content .= "--------------------\n";
        $summary_content .= "Name: " . ($termination_data['employee_name'] ?? $staff_name) . "\n";
        $summary_content .= "Role: " . ($termination_data['role'] ?? 'N/A') . "\n";
        $summary_content .= "Email: " . ($termination_data['email'] ?? 'N/A') . "\n\n";
        $summary_content .= "TERMINATION DETAILS\n";
        $summary_content .= "-------------------\n";
        $summary_content .= "Termination Date: " . $termination_date . "\n";
        $summary_content .= "Termination Type: " . ($termination_data['termination_type'] ?? 'N/A') . "\n";
        $summary_content .= "Reason Category: " . ($termination_data['reason_category'] ?? 'N/A') . "\n";
        $summary_content .= "Notice Period (days): " . ($termination_data['notice_period'] ?? 'N/A') . "\n\n";
        $summary_content .= "DETAILED REASON/NOTES\n";
        $summary_content .= "---------------------\n";
        $summary_content .= ($termination_data['notes'] ?? 'No notes provided') . "\n\n";
        $summary_content .= "OFFBOARDING CHECKLIST\n";
        $summary_content .= "---------------------\n";
        if (!empty($termination_data['checklist']) && is_array($termination_data['checklist'])) {
            foreach ($termination_data['checklist'] as $item) {
                $summary_content .= "- [x] " . ucfirst(str_replace('_', ' ', $item)) . "\n";
            }
        } else {
            $summary_content .= "No checklist items selected\n";
        }
        $summary_content .= "\nFINAL COMMENTS\n";
        $summary_content .= "--------------\n";
        $summary_content .= ($termination_data['final_comments'] ?? 'No additional comments') . "\n\n";
        $summary_content .= "PROCESSED BY\n";
        $summary_content .= "------------\n";
        $summary_content .= "Admin ID: " . ($termination_data['processed_by'] ?? 'N/A') . "\n";
        $summary_content .= "Processed At: " . date('Y-m-d H:i:s') . "\n";
        
        $summary_url = null;
        $json_url = null;

        // Upload summary file to RustFS
        $filename = 'Termination_Summary_' . $safe_staff_name . '_' . $date->format('Y-m-d') . '.txt';
        $json_filename = 'Termination_Data_' . $safe_staff_name . '_' . $date->format('Y-m-d') . '.json';
        $json_content = json_encode($termination_data, JSON_PRETTY_PRINT);

        if (isRustFSConfigured($rustfs)) {
            $r1 = uploadContentToRustFS($rustfs, $summary_content, $folder_key . '/' . $filename, 'text/plain');
            if ($r1['success']) $summary_url = $r1['url'];

            $r2 = uploadContentToRustFS($rustfs, $json_content, $folder_key . '/' . $json_filename, 'application/json');
            if ($r2['success']) $json_url = $r2['url'];
        }
        
        return [
            'success' => true,
            'folder_path' => $folder_key,
            'summary_file' => $summary_url ?? ($folder_key . '/' . $filename),
            'json_file' => $json_url ?? ($folder_key . '/' . $json_filename)
        ];
        
    } catch (Exception $e) {
        error_log("Error exporting termination data: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Persist an uploaded file: uploads directly to RustFS S3 storage.
 * This is the single entry-point every upload handler should call.
 * Zero local storage — the file goes to RustFS only.
 *
 * Flow:
 *  1. Upload to RustFS S3
 *  2. Return the RustFS public URL as both the storage path and serving URL
 *
 * @param PDO    $pdo              Database connection
 * @param string $source_path      Absolute path to the source file (e.g., PHP tmp_name or downloaded file)
 * @param string $subfolder        Logical subfolder  (e.g., 'theme', 'drills/diagrams', 'videos/coach')
 * @param string $filename         Target filename
 * @param string $local_cache_rel  Relative path for backward compatibility (e.g., 'uploads/theme/logo.png')
 * @param bool   $use_large_upload Use streaming upload for large files (videos)
 * @return array ['success'=>bool, 'rustfs_url'=>string|null, 'nextcloud_path'=>string|null, 'persistent_path'=>string|null, 'local_cache'=>string]
 */
function persistUploadedFile($pdo, $source_path, $subfolder, $filename, $local_cache_rel, $use_large_upload = false) {
    $result = [
        'success' => false,
        'rustfs_url' => null,
        'nextcloud_path' => null,
        'persistent_path' => null,
        'local_cache' => $local_cache_rel,
    ];

    try {
        $rustfs = getRustFSSettings($pdo);
        if (!isRustFSConfigured($rustfs)) {
            error_log("persistUploadedFile: RustFS not configured — cannot persist $subfolder/$filename");
            $result['success'] = true; // Don't fail the caller if storage isn't configured yet
            return $result;
        }

        $sub_parts = array_filter(explode('/', $subfolder), function($p) { return $p !== ''; });
        $object_key = 'Images/' . implode('/', $sub_parts) . '/' . $filename;

        if ($use_large_upload) {
            $upload = uploadLargeFileToRustFS($rustfs, $source_path, $object_key);
        } else {
            $upload = uploadToRustFS($rustfs, $source_path, $object_key);
        }

        if ($upload['success']) {
            $result['success'] = true;
            $result['rustfs_url'] = $upload['url'];
            $result['persistent_path'] = $upload['url'];
            // Legacy key name, actually contains RustFS URL
            $result['nextcloud_path'] = $upload['url'];
            // Update local_cache to the RustFS URL so DB stores the S3 location
            $result['local_cache'] = $upload['url'];
        } else {
            error_log("persistUploadedFile: RustFS upload failed for $subfolder/$filename: " . ($upload['message'] ?? ''));
            $result['success'] = false;
        }
    } catch (\Throwable $e) {
        error_log("persistUploadedFile: RustFS upload error for $subfolder/$filename: " . $e->getMessage());
        $result['success'] = false;
    }

    return $result;
}

/**
 * Get Paperless-NGX connection settings from database
 * 
 * @param PDO $pdo Database connection
 * @return array|null Settings array with url and api_token, or null if not configured
 */
function getPaperlessSettings($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('paperless_url', 'paperless_api_token', 'paperless_store_documents', 'paperless_correspondent', 'paperless_document_type')");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        return null;
    }
    
    $url = $settings['paperless_url'] ?? '';
    $encrypted_token = $settings['paperless_api_token'] ?? '';
    $store = $settings['paperless_store_documents'] ?? '0';
    
    if (empty($url) || empty($encrypted_token) || $store !== '1') {
        return null;
    }
    
    if (function_exists('decryptPassword')) {
        $api_token = decryptPassword($encrypted_token);
    } else {
        return null;
    }
    
    if (empty($api_token)) {
        return null;
    }
    
    return [
        'url' => rtrim($url, '/'),
        'api_token' => $api_token,
        'correspondent' => $settings['paperless_correspondent'] ?? '',
        'document_type' => $settings['paperless_document_type'] ?? ''
    ];
}

/**
 * Get or create a tag in Paperless-NGX by name
 * 
 * @param string $base_url Paperless-NGX base URL
 * @param string $api_token API token
 * @param string $tag_name Tag name to find or create
 * @return int|null Tag ID, or null on failure
 */
function getPaperlessTagId($base_url, $api_token, $tag_name) {
    // Search for existing tag
    $search_url = $base_url . '/api/tags/?name__iexact=' . urlencode($tag_name);
    $ch = curl_init($search_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $api_token,
            'Accept: application/json; version=5'
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (!empty($data['results'][0]['id'])) {
            return intval($data['results'][0]['id']);
        }
    }
    
    // Tag not found — create it
    $create_url = $base_url . '/api/tags/';
    $ch = curl_init($create_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['name' => $tag_name]),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $api_token,
            'Content-Type: application/json',
            'Accept: application/json; version=5'
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 201 || $http_code === 200) {
        $data = json_decode($response, true);
        if (!empty($data['id'])) {
            return intval($data['id']);
        }
    }
    
    return null;
}

/**
 * Get or create a correspondent in Paperless-NGX by name
 * 
 * @param string $base_url Paperless-NGX base URL
 * @param string $api_token API token
 * @param string $name Correspondent name to find or create
 * @return int|null Correspondent ID, or null on failure
 */
function getPaperlessCorrespondentId($base_url, $api_token, $name) {
    // Search for existing correspondent
    $search_url = $base_url . '/api/correspondents/?name__iexact=' . urlencode($name);
    $ch = curl_init($search_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $api_token,
            'Accept: application/json; version=5'
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (!empty($data['results'][0]['id'])) {
            return intval($data['results'][0]['id']);
        }
    }
    
    // Correspondent not found — create it
    $create_url = $base_url . '/api/correspondents/';
    $ch = curl_init($create_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['name' => $name]),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $api_token,
            'Content-Type: application/json',
            'Accept: application/json; version=5'
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 201 || $http_code === 200) {
        $data = json_decode($response, true);
        if (!empty($data['id'])) {
            return intval($data['id']);
        }
    }
    
    return null;
}

/**
 * Get or create a document type in Paperless-NGX by name
 * 
 * @param string $base_url Paperless-NGX base URL
 * @param string $api_token API token
 * @param string $name Document type name to find or create
 * @return int|null Document type ID, or null on failure
 */
function getPaperlessDocumentTypeId($base_url, $api_token, $name) {
    // Search for existing document type
    $search_url = $base_url . '/api/document_types/?name__iexact=' . urlencode($name);
    $ch = curl_init($search_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $api_token,
            'Accept: application/json; version=5'
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (!empty($data['results'][0]['id'])) {
            return intval($data['results'][0]['id']);
        }
    }
    
    // Document type not found — create it
    $create_url = $base_url . '/api/document_types/';
    $ch = curl_init($create_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['name' => $name]),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $api_token,
            'Content-Type: application/json',
            'Accept: application/json; version=5'
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 201 || $http_code === 200) {
        $data = json_decode($response, true);
        if (!empty($data['id'])) {
            return intval($data['id']);
        }
    }
    
    return null;
}

/**
 * Upload a document to Paperless-NGX with a tag for the file type
 * 
 * @param PDO $pdo Database connection
 * @param string $file_path Local file path to upload
 * @param string $tag_name Tag to apply (e.g. "Receipt", "Contract", "HR", "Termination", "Document")
 * @param string $title Optional document title
 * @return array Result with success status
 */
function uploadToPaperless($pdo, $file_path, $tag_name, $title = '') {
    $paperless = getPaperlessSettings($pdo);
    if (!$paperless) {
        return ['success' => false, 'message' => 'Paperless-NGX not configured or storage not enabled'];
    }
    
    $base_url = $paperless['url'];
    $api_token = $paperless['api_token'];
    
    // Get or create the tag
    $tag_id = getPaperlessTagId($base_url, $api_token, $tag_name);
    
    // Resolve correspondent and document type names to IDs
    $correspondent_id = null;
    if (!empty($paperless['correspondent'])) {
        $correspondent_id = getPaperlessCorrespondentId($base_url, $api_token, $paperless['correspondent']);
    }
    $document_type_id = null;
    if (!empty($paperless['document_type'])) {
        $document_type_id = getPaperlessDocumentTypeId($base_url, $api_token, $paperless['document_type']);
    }
    
    // Verify file exists before uploading
    if (!file_exists($file_path)) {
        return ['success' => false, 'message' => 'File not found: ' . basename($file_path)];
    }
    
    // Build the upload request
    $api_url = $base_url . '/api/documents/post_document/';
    $file_mime = @mime_content_type($file_path) ?: 'application/octet-stream';
    $file_name = !empty($title) ? $title : basename($file_path);
    
    $post_fields = [
        'document' => new CURLFile($file_path, $file_mime, $file_name)
    ];
    
    if (!empty($title)) {
        $post_fields['title'] = $title;
    }
    
    if ($tag_id) {
        $post_fields['tags'] = strval($tag_id);
    }
    
    if ($correspondent_id) {
        $post_fields['correspondent'] = strval($correspondent_id);
    }
    
    if ($document_type_id) {
        $post_fields['document_type'] = strval($document_type_id);
    }
    
    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post_fields,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $api_token,
            'Accept: application/json; version=5'
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if (!empty($curl_error)) {
        error_log('Paperless-NGX upload failed: ' . $curl_error);
        return ['success' => false, 'message' => 'Connection error: ' . $curl_error];
    }
    
    if ($http_code >= 200 && $http_code < 300) {
        return ['success' => true, 'task_id' => trim($response, '"')];
    }
    
    error_log('Paperless-NGX upload failed: HTTP ' . $http_code . ' - ' . $response);
    return ['success' => false, 'message' => 'Upload failed (HTTP ' . $http_code . ')'];
}
?>
