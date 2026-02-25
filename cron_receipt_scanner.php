<?php
/**
 * Nextcloud Receipt Scanner - Background Job
 * Run this script every 5 minutes via cron
 * Example: *//* 5 * * * * /usr/bin/php /path/to/cron_receipt_scanner.php
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/cloud_config.php';
require_once __DIR__ . '/notifications.php';

$log_message = "[" . date('Y-m-d H:i:s') . "] Receipt Scanner: ";

try {
    // Get Nextcloud settings
    $settings = getNextcloudSettings($pdo);
    
    // Check if scanning is enabled
    $enabled_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'receipt_scan_enabled'");
    $enabled = $enabled_stmt->fetchColumn();
    
    if ($enabled !== '1') {
        echo $log_message . "Scanning disabled\n";
        exit(0);
    }
    
    // Check if subfolder scanning is enabled
    $subfolder_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'nextcloud_scan_subfolders'");
    $scan_subfolders = $subfolder_stmt->fetchColumn() === '1';
    
    // Connect to Nextcloud
    $connection = connectNextcloud($settings);
    $folder = $settings['nextcloud_receipt_folder'] ?? '/receipts';
    
    // List files in receipt folder (recursive if enabled)
    if ($scan_subfolders) {
        $files = [];
        $files = listNextcloudFilesRecursive($connection, $folder, $files);
        echo $log_message . "Scanning recursively in $folder\n";
    } else {
        $files = listNextcloudFiles($connection, $folder);
        echo $log_message . "Scanning $folder (non-recursive)\n";
    }
    
    $new_count = 0;
    $processed_count = 0;
    
    foreach ($files as $file) {
        // Skip non-image files
        if (!in_array(strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'pdf'])) {
            continue;
        }
        
        // Download file and get hash
        $content = downloadNextcloudFile($connection, $file['path']);
        $file_hash = getFileHash($content);
        
        // Check if already processed
        $check_stmt = $pdo->prepare("SELECT id FROM cloud_receipts WHERE file_hash = ?");
        $check_stmt->execute([$file_hash]);
        
        if ($check_stmt->fetch()) {
            continue; // Already processed
        }
        
        $new_count++;
        
        // Save file to temp location for OCR processing, then upload to RustFS
        $local_filename = 'cloud_' . uniqid() . '_' . basename($file['filename']);
        $tmp_file = sys_get_temp_dir() . '/' . $local_filename;
        file_put_contents($tmp_file, $content);
        
        // Upload to RustFS
        $receipt_local = 'uploads/receipts/' . $local_filename;
        $persist = persistUploadedFile($pdo, $tmp_file, 'receipts', $local_filename, $receipt_local);
        $receipt_url = (!empty($persist['rustfs_url'])) ? $persist['rustfs_url'] : $receipt_local;
        
        // Run OCR via Paperless-NGX (needs the temp file)
        $ocr_text = performOCR($tmp_file);
        
        // Clean up temp file
        @unlink($tmp_file);
        
        // Parse receipt data from OCR
        $parsed_data = parseReceiptOCR($ocr_text);
        
        // Create expense record
        $expense_id = createExpenseFromReceipt($pdo, $parsed_data, $receipt_url);
        
        // Record in cloud_receipts table
        $stmt = $pdo->prepare("
            INSERT INTO cloud_receipts (file_path, file_name, file_hash, expense_id, processed, ocr_attempted, ocr_data, detected_date, processed_date)
            VALUES (?, ?, ?, ?, 1, 1, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $file['path'],
            $file['filename'],
            $file_hash,
            $expense_id,
            $ocr_text
        ]);
        
        // Create notification for all admins
        notifyAdminsNewReceipt($pdo, $file['filename'], $expense_id);
        
        $processed_count++;
    }
    
    // Log results
    $log_stmt = $pdo->prepare("
        INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, description)
        VALUES (0, 'receipt_scan', '127.0.0.1', 'Cron Job', ?)
    ");
    $log_stmt->execute(["Receipt scan: $processed_count new, " . count($files) . " total"]);
    
    echo $log_message . "Processed $processed_count new receipts out of " . count($files) . " files\n";
    
} catch (Exception $e) {
    $error_msg = "Error: " . $e->getMessage();
    echo $log_message . $error_msg . "\n";
    
    // Log error
    try {
        $log_stmt = $pdo->prepare("
            INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, description)
            VALUES (0, 'receipt_scan_error', '127.0.0.1', 'Cron Job', ?)
        ");
        $log_stmt->execute(["Receipt scan error: " . $e->getMessage()]);
    } catch (Exception $log_e) {
        // Ignore logging errors
    }
    
    exit(1);
}

/**
 * Perform OCR on image file
 * Uses Paperless-NGX API for all OCR processing
 */
function performOCR($file_path) {
    global $pdo;
    
    // Use Paperless-NGX for OCR
    $paperless_text = performPaperlessOCRCron($file_path, $pdo);
    if ($paperless_text !== null) {
        return $paperless_text;
    }
    
    return "OCR_NOT_AVAILABLE: Paperless-NGX not configured - configure in Settings > System Tools";
}

/**
 * Perform OCR via Paperless-NGX API (cron version)
 * Returns OCR text string, or null if Paperless-NGX is not configured/available
 */
function performPaperlessOCRCron($file_path, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('paperless_url', 'paperless_api_token', 'paperless_ocr_enabled', 'paperless_correspondent', 'paperless_document_type')");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        return null;
    }
    
    $paperless_url = $settings['paperless_url'] ?? '';
    $encrypted_token = $settings['paperless_api_token'] ?? '';
    $ocr_enabled = $settings['paperless_ocr_enabled'] ?? '0';
    
    if (empty($paperless_url) || empty($encrypted_token) || $ocr_enabled !== '1') {
        return null;
    }
    
    // Decrypt the API token using the canonical helper from security.php
    $api_token = decryptPassword($encrypted_token);
    
    if (empty($api_token)) {
        return null;
    }
    
    // Upload document to Paperless-NGX
    $api_url = rtrim($paperless_url, '/') . '/api/documents/post_document/';
    $file_mime = mime_content_type($file_path) ?: 'application/octet-stream';
    $file_name = basename($file_path);
    
    $post_fields = ['document' => new CURLFile($file_path, $file_mime, $file_name)];
    
    // Add correspondent and document type if configured
    $base_url = rtrim($paperless_url, '/');
    $correspondent_name = $settings['paperless_correspondent'] ?? '';
    if (!empty($correspondent_name)) {
        $correspondent_id = getPaperlessCorrespondentId($base_url, $api_token, $correspondent_name);
        if ($correspondent_id) {
            $post_fields['correspondent'] = strval($correspondent_id);
        }
    }
    $document_type_name = $settings['paperless_document_type'] ?? '';
    if (!empty($document_type_name)) {
        $document_type_id = getPaperlessDocumentTypeId($base_url, $api_token, $document_type_name);
        if ($document_type_id) {
            $post_fields['document_type'] = strval($document_type_id);
        }
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
    curl_close($ch);
    
    if ($http_code < 200 || $http_code >= 300) {
        error_log('Paperless-NGX upload failed: HTTP ' . $http_code);
        return "OCR_ERROR: Paperless-NGX upload failed (HTTP " . $http_code . "). Check your Paperless-NGX server.";
    }
    
    $task_id = trim($response, '"');
    // Validate task ID is a UUID or alphanumeric string
    if (empty($task_id) || !preg_match('/^[a-zA-Z0-9\-]+$/', $task_id)) {
        error_log('Paperless-NGX: Invalid or empty task ID returned');
        return "OCR_ERROR: Paperless-NGX returned an invalid task ID. Check your Paperless-NGX server.";
    }
    
    // Poll for completion
    $task_url = rtrim($paperless_url, '/') . '/api/tasks/?task_id=' . urlencode($task_id);
    $document_id = null;
    
    for ($i = 0; $i < 15; $i++) {
        sleep(2);
        $ch = curl_init($task_url);
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
        $task_response = curl_exec($ch);
        curl_close($ch);
        
        $task_data = json_decode($task_response, true);
        if (!is_array($task_data) || empty($task_data)) continue;
        
        $task = isset($task_data[0]) ? $task_data[0] : $task_data;
        if (isset($task['status']) && $task['status'] === 'SUCCESS') {
            $document_id = $task['related_document'] ?? ($task['result'] ?? null);
            break;
        } elseif (isset($task['status']) && $task['status'] === 'FAILURE') {
            error_log('Paperless-NGX OCR task failed: ' . ($task['result'] ?? 'unknown error'));
            return "OCR_ERROR: Paperless-NGX OCR processing failed: " . ($task['result'] ?? 'unknown error');
        }
    }
    
    if (empty($document_id)) {
        error_log('Paperless-NGX: Document processing timed out or failed');
        return "OCR_ERROR: Paperless-NGX document processing timed out. Try again or check your server.";
    }
    
    // Fetch OCR text
    $doc_url = rtrim($paperless_url, '/') . '/api/documents/' . intval($document_id) . '/';
    $ch = curl_init($doc_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $api_token,
            'Accept: application/json; version=5'
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $doc_response = curl_exec($ch);
    curl_close($ch);
    
    $doc_data = json_decode($doc_response, true);
    $ocr_text = $doc_data['content'] ?? '';
    
    // Clean up - delete from Paperless if not storing
    try {
        $store_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'paperless_store_documents'");
        $store_stmt->execute();
        $store_docs = $store_stmt->fetchColumn();
    } catch (Exception $e) {
        $store_docs = '0';
    }
    
    if ($store_docs !== '1' && !empty($document_id)) {
        $del_url = rtrim($paperless_url, '/') . '/api/documents/' . intval($document_id) . '/';
        $ch = curl_init($del_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Token ' . $api_token],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
    
    return !empty($ocr_text) ? $ocr_text : "OCR_FAILED";
}

/**
 * Parse receipt data from OCR text
 */
function parseReceiptOCR($ocr_text) {
    $vendor = 'Unknown Vendor';
    $amount = 0.00;
    $date = date('Y-m-d');
    
    if (strpos($ocr_text, 'OCR_') === 0) {
        // OCR not available or failed
        return ['vendor' => $vendor, 'amount' => $amount, 'date' => $date];
    }
    
    // Parse vendor (first non-empty line)
    $lines = array_filter(array_map('trim', explode("\n", $ocr_text)));
    if (!empty($lines)) {
        $vendor = reset($lines);
        $vendor = substr($vendor, 0, 100); // Limit length
    }
    
    // Parse amount (look for currency patterns)
    if (preg_match('/\$?\s*(\d+[\.,]\d{2})/', $ocr_text, $matches)) {
        $amount = floatval(str_replace(',', '.', $matches[1]));
    }
    
    // Parse date (look for date patterns)
    if (preg_match('/(\d{4}[-\/]\d{1,2}[-\/]\d{1,2})|(\d{1,2}[-\/]\d{1,2}[-\/]\d{4})/', $ocr_text, $matches)) {
        $date_str = $matches[0];
        $date_str = str_replace('/', '-', $date_str);
        
        try {
            $parsed_date = new DateTime($date_str);
            $date = $parsed_date->format('Y-m-d');
        } catch (Exception $e) {
            // Keep default date
        }
    }
    
    return [
        'vendor' => $vendor,
        'amount' => $amount,
        'date' => $date
    ];
}

/**
 * Create expense from receipt data
 */
function createExpenseFromReceipt($pdo, $data, $receipt_file) {
    // expenses table uses VARCHAR 'category' field, not category_id FK
    $category_name = 'Cloud Receipts';
    
    // Build description including vendor info
    $description = 'Auto-imported from Nextcloud';
    if (!empty($data['vendor'])) {
        $description = $data['vendor'] . ' - ' . $description;
    }
    
    // Get the first admin user ID dynamically
    $admin_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    $admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);
    $user_id = $admin ? $admin['id'] : 1; // Fallback to ID 1 if no admin found
    
    $stmt = $pdo->prepare("
        INSERT INTO expenses (user_id, category, description, amount, expense_date, receipt_url, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $user_id,
        $category_name,
        $description,
        $data['amount'],
        $data['date'],
        $receipt_file
    ]);
    
    return $pdo->lastInsertId();
}

/**
 * Notify all admins about new receipt
 */
function notifyAdminsNewReceipt($pdo, $filename, $expense_id) {
    // Get all admin users (verified admins only)
    $admin_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_verified = 1");
    
    while ($admin = $admin_stmt->fetch()) {
        createNotification(
            $pdo,
            $admin['id'],
            'expense',
            'New Cloud Receipt Imported',
            "A new receipt ($filename) has been automatically imported from Nextcloud and processed.",
            "dashboard.php?page=accounts_payable&expense_id=$expense_id",
            true
        );
    }
}
?>
