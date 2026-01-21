<?php
/**
 * Nextcloud Receipt Scanner - Background Job
 * Run this script every 5 minutes via cron
 * Example: *//* 5 * * * * /usr/bin/php /path/to/cron_receipt_scanner.php
 */

require_once __DIR__ . '/db_config.php';
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
        
        // Save file locally
        $upload_dir = __DIR__ . '/uploads/receipts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $local_filename = 'cloud_' . uniqid() . '_' . basename($file['filename']);
        file_put_contents($upload_dir . $local_filename, $content);
        
        // Run OCR (Tesseract placeholder)
        $ocr_text = performOCR($upload_dir . $local_filename);
        
        // Parse receipt data from OCR
        $parsed_data = parseReceiptOCR($ocr_text);
        
        // Create expense record
        $expense_id = createExpenseFromReceipt($pdo, $parsed_data, $local_filename);
        
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
        INSERT INTO security_logs (user_id, action, ip_address, user_agent, status)
        VALUES (0, ?, '127.0.0.1', 'Cron Job', 'success')
    ");
    $log_stmt->execute(["Receipt scan: $processed_count new, " . count($files) . " total"]);
    
    echo $log_message . "Processed $processed_count new receipts out of " . count($files) . " files\n";
    
} catch (Exception $e) {
    $error_msg = "Error: " . $e->getMessage();
    echo $log_message . $error_msg . "\n";
    
    // Log error
    try {
        $log_stmt = $pdo->prepare("
            INSERT INTO security_logs (user_id, action, ip_address, user_agent, status)
            VALUES (0, ?, '127.0.0.1', 'Cron Job', 'failure')
        ");
        $log_stmt->execute(["Receipt scan error: " . $e->getMessage()]);
    } catch (Exception $log_e) {
        // Ignore logging errors
    }
    
    exit(1);
}

/**
 * Perform OCR on image file using Tesseract
 */
function performOCR($file_path) {
    // Check if Tesseract is installed
    $tesseract_check = shell_exec('which tesseract 2>/dev/null');
    
    if (empty($tesseract_check)) {
        return "OCR_NOT_AVAILABLE: Tesseract not installed";
    }
    
    $output_file = sys_get_temp_dir() . '/' . uniqid('ocr_');
    $command = sprintf('tesseract %s %s 2>&1', escapeshellarg($file_path), escapeshellarg($output_file));
    shell_exec($command);
    
    $ocr_text = '';
    if (file_exists($output_file . '.txt')) {
        $ocr_text = file_get_contents($output_file . '.txt');
        unlink($output_file . '.txt');
    }
    
    return $ocr_text ?: "OCR_FAILED";
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
    // Get default category for cloud receipts
    $category_stmt = $pdo->query("SELECT id FROM expense_categories WHERE name = 'Cloud Receipts' LIMIT 1");
    $category = $category_stmt->fetch();
    
    if (!$category) {
        // Create category if doesn't exist
        $pdo->exec("INSERT INTO expense_categories (name, description) VALUES ('Cloud Receipts', 'Auto-imported from Nextcloud')");
        $category_id = $pdo->lastInsertId();
    } else {
        $category_id = $category['id'];
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO expenses (category_id, vendor_name, description, amount, tax_amount, total_amount, expense_date, receipt_file, payment_method, created_by)
        VALUES (?, ?, ?, ?, 0, ?, ?, ?, 'Unknown', 0)
    ");
    $stmt->execute([
        $category_id,
        $data['vendor'],
        'Auto-imported from Nextcloud',
        $data['amount'],
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
    // Get all admin users
    $admin_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
    
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
