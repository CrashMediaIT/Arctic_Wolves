<?php
/**
 * Cloud Storage Configuration
 * Provides functions for file storage using RustFS S3 and legacy Nextcloud WebDAV.
 * All uploads are stored in RustFS S3 — zero local file storage.
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/lib/rustfs_storage.php';

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
        $rustfs = getRustFSSettings($pdo);
        
        // Parse date for Year/Month/Day folders
        $date_obj = new DateTime($date ?? date('Y-m-d'));
        $year = $date_obj->format('Y');
        $month = $date_obj->format('m');
        $day = $date_obj->format('d');
        
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

        // Upload to RustFS
        $object_key = 'DrillVideos/' . $year . '/' . $month . '/' . $day . '/' . $filename;
        $rustfs_url = null;
        $file_size = filesize($file['tmp_name']);

        if (isRustFSConfigured($rustfs)) {
            $result = uploadLargeFileToRustFS($rustfs, $file['tmp_name'], $object_key);
            if ($result['success']) {
                $rustfs_url = $result['url'];
            }
        }
        
        return [
            'success' => true,
            'folder_path' => 'DrillVideos/' . $year . '/' . $month . '/' . $day,
            'filename' => $filename,
            'remote_path' => $rustfs_url ?? $object_key,
            'file_size' => $file_size
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
 * Save a file to RustFS S3 persistent storage.
 * Replaces the old local persistent storage — files go directly to RustFS.
 * 
 * @param string $local_file_path Path to the source file
 * @param string $subfolder Subfolder (e.g., 'profiles', 'evaluations/123', 'team_logos')
 * @param string $filename Target filename
 * @param PDO|null $pdo Database connection
 * @return array Result with success status and persistent_path (RustFS URL)
 */
function saveToPersistentStorage($local_file_path, $subfolder, $filename, $pdo = null) {
    try {
        if ($pdo === null) {
            global $pdo;
        }
        $rustfs = getRustFSSettings($pdo);
        if (!isRustFSConfigured($rustfs)) {
            error_log("saveToPersistentStorage: RustFS not configured, skipping upload");
            return ['success' => false, 'message' => 'RustFS not configured'];
        }

        $sub_parts = array_filter(explode('/', $subfolder), function($p) { return $p !== ''; });
        $object_key = 'Images/' . implode('/', $sub_parts) . '/' . $filename;

        $result = uploadToRustFS($rustfs, $local_file_path, $object_key);

        if ($result['success']) {
            return [
                'success' => true,
                'persistent_path' => $result['url'],
                'object_key' => $object_key,
            ];
        }

        throw new Exception($result['message'] ?? 'RustFS upload failed');
    } catch (Exception $e) {
        error_log("Error saving to RustFS persistent storage: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Check if a file exists in RustFS S3 persistent storage.
 * Since all files are served from RustFS URLs, there is no local restore needed.
 * This function is kept for backward compatibility — callers that expect
 * a boolean "was it restored?" will still work.
 * 
 * @param string $subfolder Subfolder (e.g., 'profiles', 'evaluations/123')
 * @param string $filename The filename to look for
 * @param string $local_path Unused — kept for API compatibility
 * @param PDO|null $pdo Database connection
 * @return bool True if the file exists in RustFS
 */
function restoreFromPersistentStorage($subfolder, $filename, $local_path, $pdo = null) {
    try {
        if ($pdo === null) {
            global $pdo;
        }
        $rustfs = getRustFSSettings($pdo);
        if (!isRustFSConfigured($rustfs)) {
            return false;
        }

        $sub_parts = array_filter(explode('/', $subfolder), function($p) { return $p !== ''; });
        $object_key = 'Images/' . implode('/', $sub_parts) . '/' . $filename;

        return rustfsObjectExists($rustfs, $object_key);
    } catch (Exception $e) {
        error_log("Error checking RustFS persistent storage: " . $e->getMessage());
        return false;
    }
}

/**
 * Upload an image file to RustFS S3 storage.
 * Replaces the old Nextcloud WebDAV upload.
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Nextcloud settings (ignored — uses RustFS settings from DB)
 * @param string $local_file_path Path to the local file to upload
 * @param string $subfolder Subfolder within images dir (e.g., 'profiles', 'evaluations/123')
 * @param string $filename Target filename for the upload
 * @return array Result with success status and remote_path (RustFS URL)
 */
function uploadImageToNextcloud($pdo, $settings, $local_file_path, $subfolder, $filename) {
    try {
        $rustfs = getRustFSSettings($pdo);
        if (!isRustFSConfigured($rustfs)) {
            error_log("uploadImageToNextcloud: RustFS not configured");
            return ['success' => false, 'message' => 'RustFS not configured'];
        }

        $sub_parts = array_filter(explode('/', $subfolder), function($p) { return $p !== ''; });
        $object_key = 'Images/' . implode('/', $sub_parts) . '/' . $filename;

        $result = uploadToRustFS($rustfs, $local_file_path, $object_key);

        if ($result['success']) {
            return [
                'success' => true,
                'remote_path' => $result['url'],
                'file_size' => filesize($local_file_path),
            ];
        }

        throw new Exception($result['message'] ?? 'RustFS upload failed');
    } catch (Exception $e) {
        error_log("Error uploading image to RustFS: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Upload a large file (e.g. video) to RustFS S3 using streaming to avoid memory exhaustion.
 * Replaces the old Nextcloud streaming upload.
 *
 * @param PDO $pdo Database connection
 * @param array $settings Nextcloud settings (ignored — uses RustFS settings from DB)
 * @param string $local_file_path Absolute path to the local file
 * @param string $subfolder Subfolder within images dir (e.g., 'videos/coach')
 * @param string $filename Target filename
 * @return array Result with success status and remote_path (RustFS URL)
 */
function uploadLargeFileToNextcloud($pdo, $settings, $local_file_path, $subfolder, $filename) {
    try {
        $rustfs = getRustFSSettings($pdo);
        if (!isRustFSConfigured($rustfs)) {
            error_log("uploadLargeFileToNextcloud: RustFS not configured");
            return ['success' => false, 'message' => 'RustFS not configured'];
        }

        $sub_parts = array_filter(explode('/', $subfolder), function($p) { return $p !== ''; });
        $object_key = 'Images/' . implode('/', $sub_parts) . '/' . $filename;

        $result = uploadLargeFileToRustFS($rustfs, $local_file_path, $object_key);

        if ($result['success']) {
            return [
                'success' => true,
                'remote_path' => $result['url'],
                'file_size' => $result['file_size'] ?? filesize($local_file_path),
            ];
        }

        throw new Exception($result['message'] ?? 'RustFS streaming upload failed');
    } catch (Exception $e) {
        error_log("Error uploading large file to RustFS: " . $e->getMessage());
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
            // Set nextcloud_path to the RustFS URL for backward compatibility
            $result['nextcloud_path'] = $upload['url'];
            // Update local_cache to the RustFS URL so DB stores the S3 location
            $result['local_cache'] = $upload['url'];
        } else {
            error_log("persistUploadedFile: RustFS upload failed for $subfolder/$filename: " . ($upload['message'] ?? ''));
            $result['success'] = true; // Don't fail completely if RustFS has a transient error
        }
    } catch (\Throwable $e) {
        error_log("persistUploadedFile: RustFS upload error for $subfolder/$filename: " . $e->getMessage());
        $result['success'] = true;
    }

    return $result;
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
/**
 * Get the RustFS URL for an image. No local restoration is needed.
 * Since all files are served from RustFS URLs, this function is kept
 * for backward compatibility but simply checks if the file exists in RustFS.
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Settings (ignored — uses RustFS)
 * @param string $nextcloud_path Remote path (may be old Nextcloud path or RustFS URL)
 * @param string $local_path Local path (unused — no local storage)
 * @return bool True if file is accessible
 */
function restoreImageFromNextcloud($pdo, $settings, $nextcloud_path, $local_path) {
    // If the path is already a RustFS URL, it's already accessible
    if (strpos($nextcloud_path, 'http://') === 0 || strpos($nextcloud_path, 'https://') === 0) {
        return true;
    }

    // Try to find in RustFS by constructing the object key
    try {
        $rustfs = getRustFSSettings($pdo);
        if (!isRustFSConfigured($rustfs)) {
            return false;
        }

        $images_dir = $settings['nextcloud_images_dir'] ?? '/Images';
        $relative_path = $nextcloud_path;
        if (strpos($relative_path, $images_dir . '/') === 0) {
            $relative_path = substr($relative_path, strlen($images_dir . '/'));
        }

        $object_key = 'Images/' . ltrim($relative_path, '/');
        return rustfsObjectExists($rustfs, $object_key);
    } catch (Exception $e) {
        error_log("Error checking RustFS for image: " . $e->getMessage());
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
 * Theme images are stored in RustFS and served via S3 URLs.
 * No local restoration is needed — this function is a no-op for backward compatibility.
 *
 * @param PDO $pdo Database connection
 */
function restoreThemeImagesFromPersistentStorage($pdo) {
    // No-op: theme images are now stored in RustFS S3 and served via URLs.
    // The database stores the RustFS URL directly, so no local restoration is needed.
    return;
}

/**
 * All files are stored in RustFS S3 and served via URLs.
 * No local restoration is needed — this function is a no-op for backward compatibility.
 * 
 * Previously, this function scanned the database for local file paths and restored
 * them from persistent storage after a re-deploy. With RustFS, the database stores
 * S3 URLs directly, so files are always accessible without local copies.
 *
 * @param PDO $pdo Database connection
 */
function restoreAllFilesFromPersistentStorage($pdo) {
    // No-op: all files are now stored in RustFS S3 and served via URLs.
    // The database stores the RustFS URL directly, so no local restoration is needed.
    return;
}
?>
