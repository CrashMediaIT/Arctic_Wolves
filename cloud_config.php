<?php
/**
 * Nextcloud WebDAV Connection Helper
 * Provides functions for connecting to Nextcloud and managing files
 */

require_once __DIR__ . '/db_config.php';

/**
 * Get Nextcloud settings from database
 */
function getNextcloudSettings($pdo) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('nextcloud_url', 'nextcloud_username', 'nextcloud_password', 'nextcloud_receipt_folder', 'nextcloud_hr_dir', 'nextcloud_terminations_dir')");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

/**
 * Connect to Nextcloud via WebDAV
 */
function connectNextcloud($settings) {
    if (empty($settings['nextcloud_url']) || empty($settings['nextcloud_username']) || empty($settings['nextcloud_password'])) {
        throw new Exception("Nextcloud settings are incomplete");
    }
    
    $url = rtrim($settings['nextcloud_url'], '/');
    $username = $settings['nextcloud_username'];
    $password = $settings['nextcloud_password'];
    
    return [
        'url' => $url,
        'username' => $username,
        'password' => $password
    ];
}

/**
 * List files in Nextcloud folder via WebDAV PROPFIND
 */
function listNextcloudFiles($connection, $folder) {
    $folder = '/' . trim($folder, '/');
    $webdav_url = $connection['url'] . '/remote.php/dav/files/' . $connection['username'] . $folder;
    
    $ch = curl_init($webdav_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $connection['username'] . ':' . $connection['password']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Depth: 1',
        'Content-Type: application/xml'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 207) {
        throw new Exception("Failed to list files. HTTP Code: $http_code");
    }
    
    return parseWebDAVResponse($response, $folder);
}

/**
 * Parse WebDAV XML response
 */
function parseWebDAVResponse($xml, $folder) {
    $files = [];
    
    try {
        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('d', 'DAV:');
        $xpath->registerNamespace('oc', 'http://owncloud.org/ns');
        
        $responses = $xpath->query('//d:response');
        
        foreach ($responses as $response) {
            $href = $xpath->query('.//d:href', $response)->item(0);
            $getlastmodified = $xpath->query('.//d:getlastmodified', $response)->item(0);
            $getcontentlength = $xpath->query('.//d:getcontentlength', $response)->item(0);
            $getcontenttype = $xpath->query('.//d:getcontenttype', $response)->item(0);
            
            if ($href) {
                $path = urldecode($href->textContent);
                $filename = basename($path);
                
                if ($filename && $filename !== '' && strpos($path, $folder) !== false && $path !== $folder && $path !== $folder . '/') {
                    $files[] = [
                        'path' => $path,
                        'filename' => $filename,
                        'modified' => $getlastmodified ? $getlastmodified->textContent : null,
                        'size' => $getcontentlength ? $getcontentlength->textContent : 0,
                        'type' => $getcontenttype ? $getcontenttype->textContent : 'application/octet-stream'
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("WebDAV parse error: " . $e->getMessage());
    }
    
    return $files;
}

/**
 * Download file content from Nextcloud
 */
function downloadNextcloudFile($connection, $file_path) {
    $webdav_url = $connection['url'] . '/remote.php/dav/files/' . $connection['username'] . $file_path;
    
    $ch = curl_init($webdav_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $connection['username'] . ':' . $connection['password']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("Failed to download file. HTTP Code: $http_code");
    }
    
    return $content;
}

/**
 * Get SHA256 hash of file
 */
function getFileHash($content) {
    return hash('sha256', $content);
}

/**
 * List files recursively in Nextcloud folder and subfolders
 * Supports year/month organization like /receipts/2026/01/
 */
function listNextcloudFilesRecursive($connection, $folder, &$allFiles = []) {
    $folder = '/' . trim($folder, '/');
    
    try {
        $items = listNextcloudFiles($connection, $folder);
        
        foreach ($items as $item) {
            // Check if it's a directory by checking if path ends with / or has no content type
            $isDirectory = (substr($item['path'], -1) === '/' || 
                           empty($item['type']) || 
                           $item['type'] === 'httpd/unix-directory');
            
            if ($isDirectory) {
                // Recursively scan subdirectory
                listNextcloudFilesRecursive($connection, $item['path'], $allFiles);
            } else {
                // It's a file, add to results
                // Only include image and PDF files
                $ext = strtolower(pathinfo($item['filename'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
                    $allFiles[] = $item;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error scanning folder $folder: " . $e->getMessage());
    }
    
    return $allFiles;
}

/**
 * Test Nextcloud connection
 */
function testNextcloudConnection($settings) {
    try {
        $connection = connectNextcloud($settings);
        $folder = $settings['nextcloud_receipt_folder'] ?? '/receipts';
        $files = listNextcloudFiles($connection, $folder);
        return ['success' => true, 'message' => 'Connection successful', 'file_count' => count($files)];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Create a folder in Nextcloud via WebDAV MKCOL
 */
function createNextcloudFolder($connection, $folder_path) {
    $folder_path = '/' . trim($folder_path, '/');
    $webdav_url = $connection['url'] . '/remote.php/dav/files/' . $connection['username'] . $folder_path;
    
    $ch = curl_init($webdav_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MKCOL');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $connection['username'] . ':' . $connection['password']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // 201 = Created, 405 = Already exists
    if ($http_code !== 201 && $http_code !== 405) {
        throw new Exception("Failed to create folder. HTTP Code: $http_code");
    }
    
    return true;
}

/**
 * Check if a folder exists in Nextcloud
 */
function nextcloudFolderExists($connection, $folder_path) {
    $folder_path = '/' . trim($folder_path, '/');
    $webdav_url = $connection['url'] . '/remote.php/dav/files/' . $connection['username'] . $folder_path;
    
    $ch = curl_init($webdav_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $connection['username'] . ':' . $connection['password']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Depth: 0',
        'Content-Type: application/xml'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 207;
}

/**
 * Upload a file to Nextcloud via WebDAV PUT
 */
function uploadToNextcloud($connection, $remote_path, $file_content, $content_type = 'application/octet-stream') {
    $remote_path = '/' . trim($remote_path, '/');
    $webdav_url = $connection['url'] . '/remote.php/dav/files/' . $connection['username'] . $remote_path;
    
    $ch = curl_init($webdav_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $file_content);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $connection['username'] . ':' . $connection['password']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: ' . $content_type,
        'Content-Length: ' . strlen($file_content)
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // 201 = Created, 204 = No Content (file overwritten)
    if ($http_code !== 201 && $http_code !== 204) {
        throw new Exception("Failed to upload file. HTTP Code: $http_code");
    }
    
    return $remote_path;
}

/**
 * Ensure directory structure exists in Nextcloud (creates if missing)
 * Creates all folders in the path hierarchy
 */
function ensureNextcloudPath($connection, $base_path, $sub_paths = []) {
    $current_path = '/' . trim($base_path, '/');
    
    // Create base path if it doesn't exist
    if (!nextcloudFolderExists($connection, $current_path)) {
        createNextcloudFolder($connection, $current_path);
    }
    
    // Create each sub path
    foreach ($sub_paths as $sub_path) {
        $current_path .= '/' . trim($sub_path, '/');
        if (!nextcloudFolderExists($connection, $current_path)) {
            createNextcloudFolder($connection, $current_path);
        }
    }
    
    return $current_path;
}

/**
 * Upload termination documents to Nextcloud
 * Creates Year/Month/StaffName folder structure
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Nextcloud settings
 * @param string $staff_name Full name of the staff member
 * @param string $termination_date Date in Y-m-d format
 * @param array $files Array of uploaded files ($_FILES format)
 * @return array Array of uploaded file paths
 */
function uploadTerminationDocuments($pdo, $settings, $staff_name, $termination_date, $files) {
    $uploaded_paths = [];
    
    try {
        $connection = connectNextcloud($settings);
        
        // Get base terminations directory
        $terminations_dir = $settings['nextcloud_terminations_dir'] ?? '/Arctic_Wolves/HR/Terminations';
        
        // Parse date for Year/Month folders
        $date = new DateTime($termination_date);
        $year = $date->format('Y');
        $month = $date->format('m');
        
        // Sanitize staff name for folder name
        $safe_staff_name = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $staff_name);
        $safe_staff_name = str_replace(' ', '_', trim($safe_staff_name));
        
        // Create folder structure: /HR/Terminations/YYYY/MM/StaffName
        $folder_path = ensureNextcloudPath($connection, $terminations_dir, [$year, $month, $safe_staff_name]);
        
        // Handle file uploads
        if (!empty($files['name'][0])) {
            $file_count = count($files['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $original_name = basename($files['name'][$i]);
                    $tmp_path = $files['tmp_name'][$i];
                    $file_content = file_get_contents($tmp_path);
                    $content_type = $files['type'][$i] ?? 'application/octet-stream';
                    
                    // Sanitize filename
                    $safe_filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $original_name);
                    $remote_path = $folder_path . '/' . $safe_filename;
                    
                    // Upload file
                    $uploaded_path = uploadToNextcloud($connection, $remote_path, $file_content, $content_type);
                    $uploaded_paths[] = [
                        'original_name' => $original_name,
                        'remote_path' => $uploaded_path,
                        'file_size' => strlen($file_content),
                        'content_type' => $content_type
                    ];
                }
            }
        }
        
        return [
            'success' => true,
            'folder_path' => $folder_path,
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
 * Export termination data to Nextcloud as a text/JSON file
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Nextcloud settings
 * @param array $termination_data Termination form data
 * @param string $staff_name Full name of the staff member
 * @param string $termination_date Date in Y-m-d format
 * @return array Result with success status and file path
 */
function exportTerminationData($pdo, $settings, $termination_data, $staff_name, $termination_date) {
    try {
        $connection = connectNextcloud($settings);
        
        // Get base terminations directory
        $terminations_dir = $settings['nextcloud_terminations_dir'] ?? '/Arctic_Wolves/HR/Terminations';
        
        // Parse date for Year/Month folders
        $date = new DateTime($termination_date);
        $year = $date->format('Y');
        $month = $date->format('m');
        
        // Sanitize staff name for folder name
        $safe_staff_name = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $staff_name);
        $safe_staff_name = str_replace(' ', '_', trim($safe_staff_name));
        
        // Create folder structure: /HR/Terminations/YYYY/MM/StaffName
        $folder_path = ensureNextcloudPath($connection, $terminations_dir, [$year, $month, $safe_staff_name]);
        
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
        
        // Upload summary file
        $filename = 'Termination_Summary_' . $safe_staff_name . '_' . $date->format('Y-m-d') . '.txt';
        $remote_path = $folder_path . '/' . $filename;
        uploadToNextcloud($connection, $remote_path, $summary_content, 'text/plain');
        
        // Also create a JSON version for machine readability
        $json_filename = 'Termination_Data_' . $safe_staff_name . '_' . $date->format('Y-m-d') . '.json';
        $json_path = $folder_path . '/' . $json_filename;
        $json_content = json_encode($termination_data, JSON_PRETTY_PRINT);
        uploadToNextcloud($connection, $json_path, $json_content, 'application/json');
        
        return [
            'success' => true,
            'folder_path' => $folder_path,
            'summary_file' => $remote_path,
            'json_file' => $json_path
        ];
        
    } catch (Exception $e) {
        error_log("Error exporting termination data: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
