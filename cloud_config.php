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
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('nextcloud_url', 'nextcloud_username', 'nextcloud_password', 'nextcloud_receipt_folder')");
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
?>
