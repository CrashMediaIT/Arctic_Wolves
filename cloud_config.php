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
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('nextcloud_url', 'nextcloud_username', 'nextcloud_password', 'nextcloud_receipt_folder', 'nextcloud_hr_dir', 'nextcloud_terminations_dir', 'nextcloud_payroll_dir', 'nextcloud_onboarding_dir', 'nextcloud_drill_videos_dir', 'nextcloud_contracts_dir', 'nextcloud_images_dir', 'nextcloud_persistent_path')");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

/**
 * Get secondary (backup) Nextcloud settings from database.
 * Used for redundant backups to a second Nextcloud instance.
 */
function getSecondaryNextcloudSettings($pdo) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('nextcloud_backup_url', 'nextcloud_backup_username', 'nextcloud_backup_password', 'nextcloud_backup_folder')");
    $raw = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $raw[$row['setting_key']] = $row['setting_value'];
    }
    # Normalize keys to match the primary Nextcloud settings structure
    return [
        'nextcloud_url'            => $raw['nextcloud_backup_url']      ?? null,
        'nextcloud_username'       => $raw['nextcloud_backup_username'] ?? null,
        'nextcloud_password'       => $raw['nextcloud_backup_password'] ?? null,
        'nextcloud_backup_folder'  => $raw['nextcloud_backup_folder']   ?? '/ArcticWolves/Backups/',
    ];
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
    
    // Decrypt password if it was stored encrypted
    if (function_exists('decryptPassword')) {
        $decrypted = decryptPassword($password);
        if (!empty($decrypted)) {
            $password = $decrypted;
        }
    }
    
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
function testNextcloudConnection($settings, $server_type = 'primary') {
    try {
        $connection = connectNextcloud($settings);
        $server_name = parse_url($connection['url'], PHP_URL_HOST) ?: $connection['url'];
        $server_label = ($server_type === 'backup') ? 'Backup Server' : 'Primary Server';

        // First, verify basic WebDAV connectivity by checking the user's root folder
        $root_url = $connection['url'] . '/remote.php/dav/files/' . $connection['username'] . '/';
        $ch = curl_init($root_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $connection['username'] . ':' . $connection['password']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Depth: 0',
            'Content-Type: application/xml'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if (!empty($curl_error)) {
            return [
                'success' => false,
                'message' => "Connection error for $server_label ($server_name): $curl_error",
                'server_type' => $server_type
            ];
        }

        if ($http_code === 401 || $http_code === 403) {
            return [
                'success' => false,
                'message' => "Authentication failed for $server_label ($server_name). Check username and password.",
                'server_type' => $server_type
            ];
        }

        if ($http_code !== 207) {
            return [
                'success' => false,
                'message' => "Failed to connect to $server_label ($server_name). HTTP Code: $http_code. Verify the Nextcloud URL is correct (e.g. https://cloud.example.com).",
                'server_type' => $server_type
            ];
        }

        // Connection works — now try the specific folder
        $folder = $settings['nextcloud_receipt_folder'] ?? '/receipts';
        $file_count = 0;
        $folder_note = '';
        if (!empty($folder)) {
            try {
                $files = listNextcloudFiles($connection, $folder);
                $file_count = count($files);
            } catch (Exception $e) {
                // Folder may not exist yet — try to create it
                try {
                    createNextcloudFolder($connection, $folder);
                    $folder_note = " Folder '$folder' was created automatically.";
                } catch (Exception $e2) {
                    $folder_note = " Note: folder '$folder' does not exist and could not be created.";
                }
            }
        }

        return [
            'success' => true,
            'message' => "Connection successful to $server_label: $server_name" . $folder_note,
            'file_count' => $file_count,
            'server_name' => $server_name,
            'server_type' => $server_type
        ];
    } catch (Exception $e) {
        $server_label = ($server_type === 'backup') ? 'Backup Server' : 'Primary Server';
        return [
            'success' => false,
            'message' => "Failed to connect to $server_label: " . $e->getMessage(),
            'server_type' => $server_type
        ];
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
 * Automatically creates parent directories if they don't exist
 */
function uploadToNextcloud($connection, $remote_path, $file_content, $content_type = 'application/octet-stream') {
    $remote_path = '/' . trim($remote_path, '/');
    
    // Ensure parent directory exists before uploading
    $parent_dir = dirname($remote_path);
    if ($parent_dir !== '/' && $parent_dir !== '.') {
        if (!nextcloudFolderExists($connection, $parent_dir)) {
            // Create parent directories recursively
            $parts = array_filter(explode('/', $parent_dir), function($p) { return $p !== ''; });
            $current = '';
            foreach ($parts as $part) {
                $current .= '/' . $part;
                if (!nextcloudFolderExists($connection, $current)) {
                    createNextcloudFolder($connection, $current);
                }
            }
        }
    }
    
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
        $terminations_dir = $settings['nextcloud_terminations_dir'] ?? '/HR/Terminations';
        
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
                    
                    // Upload file to Nextcloud
                    $uploaded_path = uploadToNextcloud($connection, $remote_path, $file_content, $content_type);
                    $uploaded_paths[] = [
                        'original_name' => $original_name,
                        'remote_path' => $uploaded_path,
                        'file_size' => strlen($file_content),
                        'content_type' => $content_type
                    ];
                    
                    // Save to persistent local storage
                    saveToPersistentStorage($tmp_path, 'Terminations/' . $year . '/' . $month . '/' . $safe_staff_name, $safe_filename, $pdo);
                    
                    // Also upload to Paperless-NGX with Termination tag
                    $title = 'Termination_' . $safe_staff_name . '_' . $date->format('Y-m-d') . '_' . $safe_filename;
                    uploadToPaperless($pdo, $tmp_path, 'Termination', $title);
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
        $terminations_dir = $settings['nextcloud_terminations_dir'] ?? '/HR/Terminations';
        
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

/**
 * Upload drill video to Nextcloud
 * Creates folder structure: /DrillVideos/YYYY/MM/DD/
 * Naming convention: SessionName-DrillName-AthleteName-Rep#.ext
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Nextcloud settings
 * @param string $session_name Session title
 * @param string $drill_name Drill title
 * @param string $athlete_name Full name of the athlete
 * @param int $rep_number Rep number
 * @param array $file Uploaded file ($_FILES format)
 * @param string $date Date in Y-m-d format (defaults to today)
 * @return array Result with success status and file path
 */
function uploadDrillVideo($pdo, $settings, $session_name, $drill_name, $athlete_name, $rep_number, $file, $date = null) {
    try {
        $connection = connectNextcloud($settings);
        
        // Get base drill videos directory
        $drill_videos_dir = $settings['nextcloud_drill_videos_dir'] ?? '/DrillVideos';
        
        // Parse date for Year/Month/Day folders
        $date_obj = new DateTime($date ?? date('Y-m-d'));
        $year = $date_obj->format('Y');
        $month = $date_obj->format('m');
        $day = $date_obj->format('d');
        
        // Create folder structure: /DrillVideos/YYYY/MM/DD
        $folder_path = ensureNextcloudPath($connection, $drill_videos_dir, [$year, $month, $day]);
        
        // Sanitize names for filename
        $safe_session = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $session_name);
        $safe_session = str_replace(' ', '_', trim($safe_session));
        
        $safe_drill = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $drill_name);
        $safe_drill = str_replace(' ', '_', trim($safe_drill));
        
        $safe_athlete = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $athlete_name);
        $safe_athlete = str_replace(' ', '_', trim($safe_athlete));
        
        // Get file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp4', 'mov', 'avi', 'webm', 'mkv'])) {
            throw new Exception("Invalid video file format: $ext");
        }
        
        // Build filename: SessionName-DrillName-AthleteName-Rep#.ext
        $filename = sprintf('%s-%s-%s-Rep%d.%s', 
            $safe_session, 
            $safe_drill, 
            $safe_athlete, 
            $rep_number,
            $ext
        );
        
        // Read file content
        $file_content = file_get_contents($file['tmp_name']);
        if ($file_content === false) {
            throw new Exception("Failed to read uploaded file");
        }
        
        // Determine content type
        $content_types = [
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska'
        ];
        $content_type = $content_types[$ext] ?? 'video/mp4';
        
        // Save to persistent local storage first (faster restores)
        saveToPersistentStorage($file['tmp_name'], 'DrillVideos/' . $year . '/' . $month . '/' . $day, $filename, $pdo);
        
        // Upload file to Nextcloud as backup
        $remote_path = $folder_path . '/' . $filename;
        uploadToNextcloud($connection, $remote_path, $file_content, $content_type);
        
        return [
            'success' => true,
            'folder_path' => $folder_path,
            'filename' => $filename,
            'remote_path' => $remote_path,
            'file_size' => strlen($file_content)
        ];
        
    } catch (Exception $e) {
        error_log("Error uploading drill video: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get the persistent storage base directory (outside the web root).
 * This directory survives application updates because it lives outside the project folder.
 * Structure mirrors Nextcloud: persistent_uploads/Images/{subfolder}/{filename}
 * 
 * If a PDO connection is provided, checks the database for a custom path
 * configured via the 'nextcloud_persistent_path' setting.
 * 
 * @param PDO|null $pdo Optional database connection to read custom path from settings
 * @return string Absolute path to the persistent storage directory
 */
function getPersistentStoragePath($pdo = null) {
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute(['nextcloud_persistent_path']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty(trim($row['setting_value']))) {
                return rtrim(trim($row['setting_value']), '/');
            }
        } catch (Exception $e) {
            error_log("Error reading persistent path setting: " . $e->getMessage());
        }
    }
    // Default: /config/persistent_uploads (outside web root, survives app re-deploys)
    // Fall back to sibling directory of project root if /config doesn't exist
    if (is_dir('/config')) {
        return '/config/persistent_uploads';
    }
    return realpath(__DIR__ . '/..') . '/persistent_uploads';
}

/**
 * Save a file to persistent local storage outside the web root.
 * Uses the same subfolder structure as Nextcloud so files can be cross-restored.
 * 
 * @param string $local_file_path Path to the source file
 * @param string $subfolder Subfolder (e.g., 'profiles', 'evaluations/123', 'team_logos')
 * @param string $filename Target filename
 * @return array Result with success status and persistent_path
 */
function saveToPersistentStorage($local_file_path, $subfolder, $filename, $pdo = null) {
    try {
        $base_dir = getPersistentStoragePath($pdo);
        
        // Build path: persistent_uploads/Images/{subfolder}/{filename}
        $sub_parts = array_filter(explode('/', $subfolder), function($p) { return $p !== ''; });
        $target_dir = $base_dir . '/Images/' . implode('/', $sub_parts);
        
        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0755, true)) {
                throw new Exception("Failed to create persistent storage directory: $target_dir");
            }
        }
        
        $target_path = $target_dir . '/' . $filename;
        
        if (!copy($local_file_path, $target_path)) {
            throw new Exception("Failed to copy file to persistent storage: $target_path");
        }
        
        return [
            'success' => true,
            'persistent_path' => $target_path
        ];
        
    } catch (Exception $e) {
        error_log("Error saving to persistent storage: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Restore a file from persistent local storage to the uploads directory.
 * Used when the local file in uploads/ is missing after an update.
 * 
 * @param string $subfolder Subfolder (e.g., 'profiles', 'evaluations/123')
 * @param string $filename The filename to look for
 * @param string $local_path The local path to restore the file to
 * @return bool True if file was restored successfully
 */
function restoreFromPersistentStorage($subfolder, $filename, $local_path, $pdo = null) {
    try {
        $base_dir = getPersistentStoragePath($pdo);
        
        $sub_parts = array_filter(explode('/', $subfolder), function($p) { return $p !== ''; });
        $persistent_path = $base_dir . '/Images/' . implode('/', $sub_parts) . '/' . $filename;
        
        if (!file_exists($persistent_path)) {
            return false;
        }
        
        // Ensure local directory exists
        $local_dir = dirname($local_path);
        if (!is_dir($local_dir)) {
            mkdir($local_dir, 0755, true);
        }
        
        if (!copy($persistent_path, $local_path)) {
            throw new Exception("Failed to restore file from persistent storage to: $local_path");
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error restoring from persistent storage: " . $e->getMessage());
        return false;
    }
}

/**
 * Upload an image file to Nextcloud for persistent storage
 * Also saves a copy to persistent local storage outside the web root.
 * Creates folder structure: /Images/profiles/ or /Images/evaluations/{eval_id}/
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Nextcloud settings (from getNextcloudSettings)
 * @param string $local_file_path Path to the local file to upload
 * @param string $subfolder Subfolder within images dir (e.g., 'profiles', 'evaluations/123')
 * @param string $filename Target filename for the upload
 * @return array Result with success status and remote_path
 */
function uploadImageToNextcloud($pdo, $settings, $local_file_path, $subfolder, $filename) {
    // Always save to persistent local storage (survives updates)
    saveToPersistentStorage($local_file_path, $subfolder, $filename, $pdo);
    
    try {
        $connection = connectNextcloud($settings);
        
        $images_dir = $settings['nextcloud_images_dir'] ?? '/Images';
        
        // Build folder path
        $sub_parts = array_filter(explode('/', $subfolder), function($p) { return $p !== ''; });
        $folder_path = ensureNextcloudPath($connection, $images_dir, $sub_parts);
        
        // Read file content
        $file_content = file_get_contents($local_file_path);
        if ($file_content === false) {
            throw new Exception("Failed to read local file: $local_file_path");
        }
        
        // Determine content type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $content_type = finfo_file($finfo, $local_file_path);
        finfo_close($finfo);
        
        // Upload file
        $remote_path = $folder_path . '/' . $filename;
        uploadToNextcloud($connection, $remote_path, $file_content, $content_type);
        
        return [
            'success' => true,
            'remote_path' => $remote_path,
            'file_size' => strlen($file_content)
        ];
        
    } catch (Exception $e) {
        error_log("Error uploading image to Nextcloud: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Upload a large file (e.g. video) to Nextcloud using streaming to avoid memory exhaustion.
 * Uses CURLOPT_INFILE to stream the file directly from disk without loading it into memory.
 * Also saves a copy to persistent local storage.
 *
 * @param PDO $pdo Database connection
 * @param array $settings Nextcloud settings (from getNextcloudSettings)
 * @param string $local_file_path Absolute path to the local file
 * @param string $subfolder Subfolder within Nextcloud images dir (e.g., 'videos/coach')
 * @param string $filename Target filename
 * @return array Result with success status and remote_path
 */
function uploadLargeFileToNextcloud($pdo, $settings, $local_file_path, $subfolder, $filename) {
    // Always save to persistent local storage first (uses copy(), memory-safe)
    saveToPersistentStorage($local_file_path, $subfolder, $filename, $pdo);

    try {
        $connection = connectNextcloud($settings);

        $images_dir = $settings['nextcloud_images_dir'] ?? '/Images';

        // Build folder path
        $sub_parts = array_filter(explode('/', $subfolder), function($p) { return $p !== ''; });
        $folder_path = ensureNextcloudPath($connection, $images_dir, $sub_parts);

        $remote_path = $folder_path . '/' . $filename;
        $remote_path_clean = '/' . trim($remote_path, '/');

        $webdav_url = $connection['url'] . '/remote.php/dav/files/' . $connection['username'] . $remote_path_clean;

        $file_size = filesize($local_file_path);
        $fh = fopen($local_file_path, 'rb');
        if ($fh === false) {
            throw new Exception("Failed to open file for streaming upload: $local_file_path");
        }

        // Determine content type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $content_type = finfo_file($finfo, $local_file_path);
        finfo_close($finfo);

        $ch = curl_init($webdav_url);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, $file_size);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $connection['username'] . ':' . $connection['password']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . $content_type,
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fh);

        if ($http_code !== 201 && $http_code !== 204) {
            throw new Exception("Failed to upload large file via streaming. HTTP Code: $http_code");
        }

        return [
            'success' => true,
            'remote_path' => $remote_path_clean,
            'file_size' => $file_size
        ];

    } catch (Exception $e) {
        error_log("Error uploading large file to Nextcloud: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Download an image from Nextcloud and restore it locally.
 * First tries to restore from persistent local storage (faster, no network needed).
 * Falls back to Nextcloud if persistent copy is not available.
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Nextcloud settings (from getNextcloudSettings)
 * @param string $nextcloud_path Remote path in Nextcloud
 * @param string $local_path Local path to save the file to
 * @return bool True if file was restored successfully
 */
function restoreImageFromNextcloud($pdo, $settings, $nextcloud_path, $local_path) {
    // Try persistent local storage first (faster, works offline)
    $images_dir = $settings['nextcloud_images_dir'] ?? '/Images';
    $relative_path = $nextcloud_path;
    if (strpos($relative_path, $images_dir . '/') === 0) {
        $relative_path = substr($relative_path, strlen($images_dir . '/'));
    }
    $subfolder = dirname($relative_path);
    $filename = basename($relative_path);
    
    if (!empty($subfolder) && !empty($filename) && $subfolder !== '.') {
        $restored = restoreFromPersistentStorage($subfolder, $filename, $local_path, $pdo);
        if ($restored) {
            return true;
        }
    }
    
    // Fall back to Nextcloud
    try {
        $connection = connectNextcloud($settings);
        
        $content = downloadNextcloudFile($connection, $nextcloud_path);
        
        // Ensure local directory exists
        $local_dir = dirname($local_path);
        if (!is_dir($local_dir)) {
            mkdir($local_dir, 0755, true);
        }
        
        // Write file
        if (file_put_contents($local_path, $content) === false) {
            throw new Exception("Failed to write file to: $local_path");
        }
        
        // Also save to persistent storage for future restores
        if (!empty($subfolder) && !empty($filename) && $subfolder !== '.') {
            saveToPersistentStorage($local_path, $subfolder, $filename, $pdo);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error restoring image from Nextcloud: " . $e->getMessage());
        return false;
    }
}

/**
 * 
 * @param array $settings Nextcloud settings
 * @param string $session_name Session title
 * @param string $drill_name Drill title
 * @param string $athlete_name Full name of the athlete
 * @param int $rep_number Rep number
 * @param string $date Date in Y-m-d format
 * @param string $ext File extension (default: mp4)
 * @return string Expected Nextcloud path
 */
function getDrillVideoPath($settings, $session_name, $drill_name, $athlete_name, $rep_number, $date, $ext = 'mp4') {
    $drill_videos_dir = $settings['nextcloud_drill_videos_dir'] ?? '/DrillVideos';
    
    $date_obj = new DateTime($date);
    $year = $date_obj->format('Y');
    $month = $date_obj->format('m');
    $day = $date_obj->format('d');
    
    $safe_session = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $session_name));
    $safe_drill = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $drill_name));
    $safe_athlete = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $athlete_name));
    
    $filename = sprintf('%s-%s-%s-Rep%d.%s', $safe_session, $safe_drill, $safe_athlete, $rep_number, $ext);
    
    return sprintf('%s/%s/%s/%s/%s', $drill_videos_dir, $year, $month, $day, $filename);
}

/**
 * List drill videos for a specific date
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Nextcloud settings
 * @param string $date Date in Y-m-d format
 * @return array List of video files
 */
function listDrillVideosForDate($pdo, $settings, $date) {
    try {
        $connection = connectNextcloud($settings);
        $drill_videos_dir = $settings['nextcloud_drill_videos_dir'] ?? '/DrillVideos';
        
        $date_obj = new DateTime($date);
        $folder_path = sprintf('%s/%s/%s/%s', 
            $drill_videos_dir, 
            $date_obj->format('Y'),
            $date_obj->format('m'),
            $date_obj->format('d')
        );
        
        // Check if folder exists
        if (!nextcloudFolderExists($connection, $folder_path)) {
            return ['success' => true, 'videos' => []];
        }
        
        $files = listNextcloudFiles($connection, $folder_path);
        
        // Filter for video files only
        $video_extensions = ['mp4', 'mov', 'avi', 'webm', 'mkv'];
        $videos = array_filter($files, function($file) use ($video_extensions) {
            $ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
            return in_array($ext, $video_extensions);
        });
        
        return [
            'success' => true,
            'videos' => array_values($videos),
            'folder_path' => $folder_path
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'videos' => []
        ];
    }
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

/**
 * Restore theme images from persistent storage when local files are missing.
 * Called during page load to ensure theme images survive re-deploys.
 * Checks logo, favicon, hero image, center ice logo, and business card backgrounds.
 *
 * @param PDO $pdo Database connection
 */
function restoreThemeImagesFromPersistentStorage($pdo) {
    try {
        $stmt = $pdo->query("SELECT setting_name, setting_value FROM theme_settings WHERE setting_name IN (
            'logo_url', 'favicon_url', 'hero_image_url', 'center_ice_logo_url',
            'business_card_front_bg_url', 'business_card_back_bg_url',
            'logo_url_nc_path', 'favicon_url_nc_path', 'hero_image_url_nc_path',
            'center_ice_logo_url_nc_path', 'business_card_front_bg_url_nc_path',
            'business_card_back_bg_url_nc_path'
        )");
        $all_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        return; // Table may not exist yet
    }

    // Separate URL settings from Nextcloud path settings
    $theme_images = [];
    $nc_paths = [];
    foreach ($all_settings as $key => $val) {
        if (str_ends_with($key, '_nc_path')) {
            $nc_paths[$key] = $val;
        } else {
            $theme_images[$key] = $val;
        }
    }

    $project_root = realpath(__DIR__);

    foreach ($theme_images as $setting_name => $url) {
        if (empty($url)) continue;

        // Only process local upload paths (not external URLs)
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) continue;
        if (strpos($url, 'uploads/') !== 0) continue;

        $local_path = $project_root . '/' . $url;

        // If the file already exists locally, nothing to do
        if (file_exists($local_path)) continue;

        // Extract filename and determine subfolder for persistent storage lookup
        $filename = basename($url);
        // Theme images are stored under 'theme' subfolder in persistent storage
        $subfolder = 'theme';

        // Try to restore from persistent storage
        $restored = restoreFromPersistentStorage($subfolder, $filename, $local_path, $pdo);
        if ($restored) {
            error_log("Restored theme image from persistent storage: $filename -> $local_path");
        } else {
            // Try Nextcloud using stored Nextcloud path first, then guess path as fallback
            try {
                $nc_settings = getNextcloudSettings($pdo);
                if (!empty($nc_settings['nextcloud_url'])) {
                    if (!empty($nc_settings['nextcloud_password'])) {
                        $decrypted = function_exists('decryptPassword') ? decryptPassword($nc_settings['nextcloud_password']) : '';
                        if (!empty($decrypted)) {
                            $nc_settings['nextcloud_password'] = $decrypted;
                        }
                    }
                    // Use stored Nextcloud path if available, otherwise guess
                    $nc_path_key = $setting_name . '_nc_path';
                    $remote_path = $nc_paths[$nc_path_key] ?? null;
                    if (empty($remote_path)) {
                        $images_dir = $nc_settings['nextcloud_images_dir'] ?? '/Images';
                        $remote_path = $images_dir . '/theme/' . $filename;
                    }
                    $nc_restored = restoreImageFromNextcloud($pdo, $nc_settings, $remote_path, $local_path);
                    if ($nc_restored) {
                        error_log("Restored theme image from Nextcloud: $filename -> $local_path");
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to restore theme image from Nextcloud: " . $e->getMessage());
            }
        }
    }
}

/**
 * Restore ALL files from persistent storage back to local paths after a re-deploy.
 *
 * When the Arctic_Wolves directory is deleted and re-created (e.g., during an update),
 * the database still contains paths to files that no longer exist locally. This function
 * queries every table that stores local file paths and restores any missing files from
 * persistent storage (which lives outside the web root and survives re-deploys).
 *
 * This is called once per session from dashboard.php and pwa.php to ensure all images,
 * videos, documents, and other files are available without waiting for the user to visit
 * every individual page.
 *
 * Persistent storage structure: {persistent_base}/Images/{subfolder}/{filename}
 * The subfolder matches the subfolder used in saveToPersistentStorage() and
 * uploadImageToNextcloud() calls throughout the application.
 *
 * @param PDO $pdo Database connection
 */
function restoreAllFilesFromPersistentStorage($pdo) {
    $project_root = realpath(__DIR__);
    $restored_count = 0;
    $base_dir = getPersistentStoragePath($pdo);

    // Helper: try to restore a single file from persistent storage
    // $relative_url: the relative URL stored in DB (e.g., 'uploads/theme/logo.png' or 'videos/drills/file.mp4')
    // $subfolder: the persistent storage subfolder (e.g., 'theme', 'profiles', 'videos/coach')
    $tryRestore = function($relative_url, $subfolder) use ($project_root, $pdo, $base_dir, &$restored_count) {
        if (empty($relative_url)) return;
        // Skip external URLs
        if (strpos($relative_url, 'http://') === 0 || strpos($relative_url, 'https://') === 0) return;
        
        $local_path = $project_root . '/' . $relative_url;
        // Already exists, nothing to do
        if (file_exists($local_path)) return;
        
        $filename = basename($relative_url);
        if (empty($filename)) return;
        
        // First try the exact subfolder
        $restored = restoreFromPersistentStorage($subfolder, $filename, $local_path, $pdo);
        if ($restored) {
            $restored_count++;
            error_log("Persistent restore: $subfolder/$filename -> $local_path");
            return;
        }
        
        // If exact subfolder didn't work, search recursively under the base subfolder.
        // This handles cases like DrillVideos/2024/03/15/file.mp4 where we don't know
        // the exact date path, or files that may have been saved under a different subfolder.
        $search_base = $base_dir . '/Images/' . trim($subfolder, '/');
        if (is_dir($search_base)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($search_base, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                if ($file->getFilename() === $filename) {
                    // Ensure local directory exists
                    $local_dir = dirname($local_path);
                    if (!is_dir($local_dir)) {
                        mkdir($local_dir, 0755, true);
                    }
                    if (copy($file->getPathname(), $local_path)) {
                        $restored_count++;
                        error_log("Persistent restore (recursive): " . $file->getPathname() . " -> $local_path");
                        return;
                    }
                }
            }
        }
    };

    // ── 1. Theme images (logo, favicon, hero, business card backgrounds, center ice) ──
    restoreThemeImagesFromPersistentStorage($pdo);

    // ── 2. User profile images ──
    try {
        $stmt = $pdo->query("SELECT id, profile_image FROM users WHERE profile_image IS NOT NULL AND profile_image != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tryRestore($row['profile_image'], 'profiles');
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 3. Videos (coach reviews, athlete uploads, game plan) ──
    try {
        $stmt = $pdo->query("SELECT id, video_url, local_path, video_type FROM videos WHERE (video_url IS NOT NULL AND video_url != '') OR (local_path IS NOT NULL AND local_path != '')");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Determine subfolder based on video_type
            $vtype = $row['video_type'] ?? '';
            if ($vtype === 'coach_review') {
                $subfolder = 'videos/coach';
            } elseif ($vtype === 'uploaded_by_athlete') {
                $subfolder = 'videos/athlete';
            } elseif ($vtype === 'drill_review') {
                // Drill videos use DrillVideos subfolder (uploaded via uploadDrillVideo)
                $subfolder = 'DrillVideos';
            } else {
                $subfolder = 'videos/gameplan';
            }
            $url = $row['video_url'] ?? $row['local_path'] ?? '';
            $tryRestore($url, $subfolder);
            // Also try local_path if different
            if (!empty($row['local_path']) && $row['local_path'] !== $url) {
                $tryRestore($row['local_path'], $subfolder);
            }
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 4. Drill images and custom diagrams ──
    try {
        $stmt = $pdo->query("SELECT id, custom_image FROM drills WHERE custom_image IS NOT NULL AND custom_image != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tryRestore($row['custom_image'], 'drills');
            // Also try drills/diagrams subfolder (used by practice plan diagram exports)
            $tryRestore($row['custom_image'], 'drills/diagrams');
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 5. Evaluation media (photos, videos taken during evaluations) ──
    try {
        $stmt = $pdo->query("SELECT id, file_path, evaluation_id FROM evaluation_media WHERE file_path IS NOT NULL AND file_path != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $eval_id = $row['evaluation_id'] ?? '';
            $subfolder = !empty($eval_id) ? 'evaluations/' . intval($eval_id) : 'evaluations';
            $tryRestore($row['file_path'], $subfolder);
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 6. Team logos ──
    try {
        $stmt = $pdo->query("SELECT id, logo_url FROM teams WHERE logo_url IS NOT NULL AND logo_url != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tryRestore($row['logo_url'], 'team_logos');
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 7. Exercise images (workout builder) ──
    try {
        $stmt = $pdo->query("SELECT id, image_url FROM exercises WHERE image_url IS NOT NULL AND image_url != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tryRestore($row['image_url'], 'exercises');
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 8. Merchandise category images ──
    try {
        $stmt = $pdo->query("SELECT id, image_url FROM merchandise_categories WHERE image_url IS NOT NULL AND image_url != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tryRestore($row['image_url'], 'merchandise/categories');
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 9. Merchandise product images ──
    try {
        $stmt = $pdo->query("SELECT id, image_url FROM merchandise_products WHERE image_url IS NOT NULL AND image_url != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tryRestore($row['image_url'], 'merchandise/products');
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 10. Additional product images ──
    try {
        $stmt = $pdo->query("SELECT id, image_url FROM merchandise_product_images WHERE image_url IS NOT NULL AND image_url != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tryRestore($row['image_url'], 'merchandise/products');
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 11. Evaluation goal step media ──
    try {
        $stmt = $pdo->query("SELECT id, media_url, step_id FROM eval_goal_step_media WHERE media_url IS NOT NULL AND media_url != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $step_id = $row['step_id'] ?? '';
            $subfolder = !empty($step_id) ? 'eval_goals/' . intval($step_id) : 'eval_goals';
            $tryRestore($row['media_url'], $subfolder);
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 12. Training program images ──
    try {
        $stmt = $pdo->query("SELECT id, image_url FROM training_programs WHERE image_url IS NOT NULL AND image_url != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tryRestore($row['image_url'], 'theme');
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 13. Expense receipts ──
    try {
        $stmt = $pdo->query("SELECT id, receipt_url, expense_date FROM expenses WHERE receipt_url IS NOT NULL AND receipt_url != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['expense_date'] ?? '';
            if (!empty($date)) {
                $year = date('Y', strtotime($date));
                $month = date('m', strtotime($date));
                $tryRestore($row['receipt_url'], 'Receipts/' . $year . '/' . $month);
            } else {
                $tryRestore($row['receipt_url'], 'Receipts');
            }
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 14. Recurring expense / contract files ──
    try {
        $stmt = $pdo->query("SELECT id, contract_file_url, vendor_name, purpose FROM recurring_expenses WHERE contract_file_url IS NOT NULL AND contract_file_url != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vendor = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $row['vendor_name'] ?? 'Unknown');
            $vendor = str_replace(' ', '_', trim($vendor));
            $purpose = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $row['purpose'] ?? 'General');
            $purpose = str_replace(' ', '_', trim($purpose));
            $tryRestore($row['contract_file_url'], 'Contracts/' . $vendor . '/' . $purpose);
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 15. Game plan video sources ──
    try {
        $stmt = $pdo->query("SELECT id, video_url FROM vr_video_sources WHERE video_url IS NOT NULL AND video_url != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tryRestore($row['video_url'], 'videos/gameplan');
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 16. Termination documents ──
    try {
        $stmt = $pdo->query("
            SELECT td.id, td.file_path, et.staff_name, et.termination_date 
            FROM termination_documents td 
            LEFT JOIN employee_terminations et ON td.termination_id = et.id 
            WHERE td.file_path IS NOT NULL AND td.file_path != ''
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['termination_date'] ?? '';
            $staff = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $row['staff_name'] ?? 'Unknown');
            $staff = str_replace(' ', '_', trim($staff));
            if (!empty($date)) {
                $year = date('Y', strtotime($date));
                $month = date('m', strtotime($date));
                $tryRestore($row['file_path'], 'Terminations/' . $year . '/' . $month . '/' . $staff);
            } else {
                $tryRestore($row['file_path'], 'Terminations');
            }
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 17. Onboarding documents ──
    try {
        $stmt = $pdo->query("
            SELECT od.id, od.file_path, eo.staff_name, eo.hire_date 
            FROM onboarding_documents od 
            LEFT JOIN employee_onboarding eo ON od.onboarding_id = eo.id 
            WHERE od.file_path IS NOT NULL AND od.file_path != ''
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['hire_date'] ?? '';
            $staff = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $row['staff_name'] ?? 'Unknown');
            $staff = str_replace(' ', '_', trim($staff));
            if (!empty($date)) {
                $year = date('Y', strtotime($date));
                $tryRestore($row['file_path'], 'Onboarding/' . $year . '/' . $staff);
            } else {
                $tryRestore($row['file_path'], 'Onboarding');
            }
        }
    } catch (Exception $e) { /* table may not exist */ }

    // ── 18. Drill video media (separate from practice plan drills) ──
    try {
        $stmt = $pdo->query("SELECT id, video_url FROM drills WHERE video_url IS NOT NULL AND video_url != ''");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tryRestore($row['video_url'], 'drills/videos');
        }
    } catch (Exception $e) { /* table may not exist */ }

    if ($restored_count > 0) {
        error_log("Persistent storage restoration complete: $restored_count files restored");
    }
}
?>
