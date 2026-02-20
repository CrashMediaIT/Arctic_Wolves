<?php
// process_expenses.php - Handle expense operations with CRA best practices
session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Conditionally load cloud_config if available
if (file_exists(__DIR__ . '/cloud_config.php')) {
    require_once 'cloud_config.php';
}

setSecurityHeaders();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Access denied.']));
}

checkCsrfToken();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// JSON actions that return JSON response
$json_actions = ['ocr_scan', 'get_expense', 'export_expenses', 'create_virtual_card', 'list_virtual_cards', 
                 'process_batch_payment', 'create_payee', 'update_payee', 'delete_payee', 'get_payees'];

if (in_array($action, $json_actions)) {
    header('Content-Type: application/json');
}

/**
 * Upload receipt to Nextcloud with year/month directory structure
 */
function uploadReceiptToNextcloud($pdo, $local_file_path, $expense_date, $vendor_name, $expense_id) {
    try {
        $settings = getNextcloudSettings($pdo);
        
        if (empty($settings['nextcloud_url']) || empty($settings['nextcloud_username'])) {
            return ['success' => false, 'message' => 'Nextcloud not configured'];
        }
        
        $connection = connectNextcloud($settings);
        $receipts_dir = $settings['nextcloud_receipts_dir'] ?? '/Arctic_Wolves/Receipts';
        
        // Parse date for Year/Month folders
        $date = new DateTime($expense_date);
        $year = $date->format('Y');
        $month = $date->format('m');
        
        // Create folder structure: /Receipts/YYYY/MM
        $folder_path = ensureNextcloudPath($connection, $receipts_dir, [$year, $month]);
        
        // Sanitize vendor name for filename
        $safe_vendor = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $vendor_name);
        $safe_vendor = substr($safe_vendor, 0, 50);
        
        // Generate filename: Date_Vendor_ExpenseID.ext
        $file_ext = strtolower(pathinfo($local_file_path, PATHINFO_EXTENSION));
        $filename = $date->format('Y-m-d') . '_' . $safe_vendor . '_' . $expense_id . '.' . $file_ext;
        
        // Upload file
        $file_content = file_get_contents($local_file_path);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $content_type = finfo_file($finfo, $local_file_path);
        finfo_close($finfo);
        
        $remote_path = $folder_path . '/' . $filename;
        uploadToNextcloud($connection, $remote_path, $file_content, $content_type);
        
        return [
            'success' => true,
            'cloud_path' => $remote_path,
            'filename' => $filename
        ];
        
    } catch (Exception $e) {
        ErrorLogger::error("Nextcloud upload error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Perform OCR on receipt image or PDF
 * Uses Paperless-NGX API for all OCR processing
 */
function performReceiptOCR($file_path) {
    $paperless_result = performPaperlessOCR($file_path);
    if ($paperless_result !== null) {
        return $paperless_result;
    }
    
    return [
        'vendor' => '',
        'date' => date('Y-m-d'),
        'subtotal' => 0.00,
        'tax' => 0.00,
        'total' => 0.00,
        'items' => [],
        'raw_text' => '',
        'error' => 'OCR not available - configure Paperless-NGX in Settings > System Tools'
    ];
}

/**
 * Perform OCR via Paperless-NGX API
 * Returns parsed OCR data array, or null if Paperless-NGX is not configured/available
 */
function performPaperlessOCR($file_path) {
    global $pdo;
    
    // Check if Paperless-NGX is configured and enabled for OCR
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('paperless_url', 'paperless_api_token', 'paperless_ocr_enabled')");
        $stmt->execute();
        $paperless_settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $paperless_settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        return null;
    }
    
    $paperless_url = $paperless_settings['paperless_url'] ?? '';
    $encrypted_token = $paperless_settings['paperless_api_token'] ?? '';
    $ocr_enabled = $paperless_settings['paperless_ocr_enabled'] ?? '0';
    
    if (empty($paperless_url) || empty($encrypted_token) || $ocr_enabled !== '1') {
        return null;
    }
    
    // Decrypt the API token
    if (function_exists('decryptPassword')) {
        $api_token = decryptPassword($encrypted_token);
    } else {
        return null;
    }
    
    if (empty($api_token)) {
        return null;
    }
    
    $ocr_data = [
        'vendor' => '',
        'date' => date('Y-m-d'),
        'subtotal' => 0.00,
        'tax' => 0.00,
        'total' => 0.00,
        'items' => [],
        'raw_text' => ''
    ];
    
    // Upload document to Paperless-NGX for OCR processing
    $api_url = rtrim($paperless_url, '/') . '/api/documents/post_document/';
    
    $file_mime = mime_content_type($file_path) ?: 'application/octet-stream';
    $file_name = basename($file_path);
    
    $ch = curl_init($api_url);
    $post_fields = [
        'document' => new CURLFile($file_path, $file_mime, $file_name)
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post_fields,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $api_token,
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if (!empty($curl_error) || $http_code < 200 || $http_code >= 300) {
        error_log('Paperless-NGX upload failed: HTTP ' . $http_code . ' - ' . ($curl_error ?: $response));
        return null; // Paperless-NGX not available
    }
    
    // Paperless-NGX returns a task ID — we need to poll for the result
    $task_id = trim($response, '"');
    // Validate task ID is a UUID or alphanumeric string
    if (empty($task_id) || !preg_match('/^[a-zA-Z0-9\-]+$/', $task_id)) {
        error_log('Paperless-NGX: Invalid or empty task ID returned');
        return null;
    }
    
    // Poll for task completion (up to 30 seconds)
    $task_url = rtrim($paperless_url, '/') . '/api/tasks/?task_id=' . urlencode($task_id);
    $max_attempts = 15;
    $document_id = null;
    
    for ($i = 0; $i < $max_attempts; $i++) {
        sleep(2);
        
        $ch = curl_init($task_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . $api_token,
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $task_response = curl_exec($ch);
        curl_close($ch);
        
        $task_data = json_decode($task_response, true);
        if (!is_array($task_data) || empty($task_data)) {
            continue;
        }
        
        // Task data is returned as an array of tasks
        $task = is_array($task_data) && isset($task_data[0]) ? $task_data[0] : $task_data;
        
        if (isset($task['status']) && $task['status'] === 'SUCCESS') {
            $document_id = $task['related_document'] ?? ($task['result'] ?? null);
            break;
        } elseif (isset($task['status']) && $task['status'] === 'FAILURE') {
            error_log('Paperless-NGX OCR task failed: ' . ($task['result'] ?? 'unknown error'));
            return null;
        }
    }
    
    if (empty($document_id)) {
        error_log('Paperless-NGX: Document processing timed out or failed');
        return null;
    }
    
    // Fetch the document content (OCR text)
    $doc_url = rtrim($paperless_url, '/') . '/api/documents/' . intval($document_id) . '/';
    $ch = curl_init($doc_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $api_token,
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $doc_response = curl_exec($ch);
    curl_close($ch);
    
    $doc_data = json_decode($doc_response, true);
    $ocr_text = $doc_data['content'] ?? '';
    
    if (empty($ocr_text)) {
        $ocr_data['error'] = 'Paperless-NGX OCR returned no text';
        return $ocr_data;
    }
    
    $ocr_data = parseOCRText($ocr_text, $ocr_data);
    
    // Tag the OCR-processed document as a Receipt in Paperless-NGX
    $tag_id = getPaperlessTagId($paperless_url, $api_token, 'Receipt');
    if ($tag_id && !empty($document_id)) {
        $patch_url = rtrim($paperless_url, '/') . '/api/documents/' . intval($document_id) . '/';
        $ch = curl_init($patch_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode(['tags' => [$tag_id]]),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . $api_token,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
    
    return $ocr_data;
}

/**
 * Parse OCR text into structured receipt data
 */
function parseOCRText($ocr_text, $ocr_data) {
    $ocr_data['raw_text'] = $ocr_text;
    
    // Parse vendor (first non-empty line that looks like a business name)
    $lines = array_filter(array_map('trim', explode("\n", $ocr_text)));
    if (!empty($lines)) {
        $ocr_data['vendor'] = substr(reset($lines), 0, 100);
    }
    
    // Parse amounts - look for currency patterns
    $amounts = [];
    if (preg_match_all('/\$?\s*(\d+[\.,]\d{2})/', $ocr_text, $matches)) {
        foreach ($matches[1] as $match) {
            $amounts[] = floatval(str_replace(',', '.', $match));
        }
    }
    
    // Assume largest amount is total, find subtotal and tax
    if (!empty($amounts)) {
        rsort($amounts);
        $ocr_data['total'] = $amounts[0];
        
        // Try to find tax amount (common patterns: GST, HST, TAX followed by amount)
        if (preg_match('/(GST|HST|TAX|TVQ|TPS)[:\s]*\$?\s*(\d+[\.,]\d{2})/i', $ocr_text, $tax_match)) {
            $ocr_data['tax'] = floatval(str_replace(',', '.', $tax_match[2]));
            $ocr_data['subtotal'] = $ocr_data['total'] - $ocr_data['tax'];
        } else if (count($amounts) > 1) {
            // Assume second largest is subtotal
            $ocr_data['subtotal'] = $amounts[1];
            $ocr_data['tax'] = $ocr_data['total'] - $ocr_data['subtotal'];
        } else {
            $ocr_data['subtotal'] = $ocr_data['total'];
        }
    }
    
    // Parse date
    if (preg_match('/(\d{4}[-\/]\d{1,2}[-\/]\d{1,2})|(\d{1,2}[-\/]\d{1,2}[-\/]\d{4})|(\d{1,2}[-\/]\d{1,2}[-\/]\d{2})/', $ocr_text, $date_match)) {
        $date_str = str_replace('/', '-', $date_match[0]);
        try {
            $parsed_date = new DateTime($date_str);
            $ocr_data['date'] = $parsed_date->format('Y-m-d');
        } catch (Exception $e) {
            // Keep default date
        }
    }
    
    // Try to parse line items (look for quantity x price patterns)
    if (preg_match_all('/(.+?)\s+(\d+)\s*[xX@]\s*\$?(\d+[\.,]\d{2})/', $ocr_text, $item_matches, PREG_SET_ORDER)) {
        foreach ($item_matches as $item) {
            $ocr_data['items'][] = [
                'name' => trim($item[1]),
                'quantity' => intval($item[2]),
                'unit_price' => floatval(str_replace(',', '.', $item[3])),
                'total_price' => intval($item[2]) * floatval(str_replace(',', '.', $item[3]))
            ];
        }
    }
    
    return $ocr_data;
}

try {
    switch ($action) {
        case 'create':
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $expense_date = $_POST['expense_date'] ?? '';
            $vendor_name = trim($_POST['vendor_name'] ?? '');
            $subtotal = floatval($_POST['subtotal'] ?? $amount);
            $tax_amount = floatval($_POST['tax_amount'] ?? 0);
            $total_amount = floatval($_POST['total_amount'] ?? ($subtotal + $tax_amount));
            $payment_method = trim($_POST['payment_method'] ?? '');
            $reference_number = trim($_POST['reference_number'] ?? '');
            $currency = trim($_POST['currency'] ?? 'CAD');
            $payee_id = !empty($_POST['payee_id']) ? intval($_POST['payee_id']) : null;
            $line_items = isset($_POST['line_items']) ? json_decode($_POST['line_items'], true) : [];
            
            // Validate required fields
            if (empty($category)) {
                throw new Exception('Category is required');
            }
            if (empty($expense_date)) {
                throw new Exception('Expense date is required');
            }
            if ($total_amount <= 0 && $amount <= 0) {
                throw new Exception('Amount must be greater than zero');
            }
            
            // Use total_amount if provided, otherwise use amount
            if ($total_amount <= 0) {
                $total_amount = $amount;
                $subtotal = $amount;
            }
            
            // Handle file upload
            $receipt_url = null;
            $nextcloud_path = null;
            $ocr_data = null;
            
            if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/receipts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Validate file size (10MB max)
                if ($_FILES['receipt_file']['size'] > 10 * 1024 * 1024) {
                    throw new Exception('File size exceeds 10MB limit');
                }
                
                // Validate MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $_FILES['receipt_file']['tmp_name']);
                finfo_close($finfo);
                
                $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
                if (!in_array($mime_type, $allowed_mimes)) {
                    throw new Exception('Invalid file type. Only JPG, PNG, and PDF files are allowed.');
                }
                
                $file_ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
                
                if (in_array($file_ext, $allowed_exts)) {
                    $receipt_url = 'uploads/receipts/' . uniqid('receipt_') . '.' . $file_ext;
                    move_uploaded_file($_FILES['receipt_file']['tmp_name'], $receipt_url);
                    
                    // Perform OCR on images
                    if (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
                        $ocr_data = performReceiptOCR($receipt_url);
                    }
                }
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO expenses (user_id, expense_date, amount, category, vendor_name, payee_id,
                    subtotal, tax_amount, total_amount, payment_method, reference_number, 
                    description, receipt_url, nextcloud_path, ocr_data, ocr_processed, currency, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $user_id, $expense_date, $total_amount, $category, $vendor_name, $payee_id,
                $subtotal, $tax_amount, $total_amount, $payment_method, $reference_number,
                $description, $receipt_url, $nextcloud_path, 
                $ocr_data ? json_encode($ocr_data) : null, $ocr_data ? 1 : 0, $currency
            ]);
            
            $expense_id = $pdo->lastInsertId();
            
            Auditor::log($pdo, $user_id, 'create', 'expenses', $expense_id, ['action' => 'created_expense', 'category' => $category, 'amount' => $total_amount]);
            
            // Save line items if provided
            if (!empty($line_items)) {
                $item_stmt = $pdo->prepare("
                    INSERT INTO expense_line_items (expense_id, item_name, quantity, unit_price, total_price, category, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($line_items as $item) {
                    $item_total = floatval($item['quantity'] ?? 1) * floatval($item['unit_price'] ?? 0);
                    $item_stmt->execute([
                        $expense_id,
                        trim($item['item_name'] ?? 'Item'),
                        floatval($item['quantity'] ?? 1),
                        floatval($item['unit_price'] ?? 0),
                        $item_total,
                        trim($item['category'] ?? ''),
                        trim($item['notes'] ?? '')
                    ]);
                }
            }
            
            // Upload to Nextcloud if receipt exists
            if ($receipt_url && !empty($vendor_name)) {
                $nc_result = uploadReceiptToNextcloud($pdo, $receipt_url, $expense_date, $vendor_name, $expense_id);
                if ($nc_result['success']) {
                    $pdo->prepare("UPDATE expenses SET nextcloud_path = ? WHERE id = ?")->execute([
                        $nc_result['cloud_path'], $expense_id
                    ]);
                }
                
                // Also upload to Paperless-NGX with Receipt tag
                $receipt_title = $expense_date . '_' . $vendor_name . '_' . $expense_id;
                uploadToPaperless($pdo, $receipt_url, 'Receipt', $receipt_title);
            }
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true, 
                    'message' => 'Expense added successfully!',
                    'expense_id' => $expense_id,
                    'ocr_data' => $ocr_data
                ]);
                exit();
            }
            header("Location: dashboard.php?page=accounting_expenses&status=success");
            exit();
            
        case 'update':
            $expense_id = intval($_POST['expense_id']);
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $amount = floatval($_POST['amount']);
            $expense_date = $_POST['expense_date'];
            $vendor_name = trim($_POST['vendor_name'] ?? '');
            $subtotal = floatval($_POST['subtotal'] ?? $amount);
            $tax_amount = floatval($_POST['tax_amount'] ?? 0);
            $total_amount = floatval($_POST['total_amount'] ?? ($subtotal + $tax_amount));
            $payment_method = trim($_POST['payment_method'] ?? '');
            $reference_number = trim($_POST['reference_number'] ?? '');
            $currency = trim($_POST['currency'] ?? 'CAD');
            $payee_id = !empty($_POST['payee_id']) ? intval($_POST['payee_id']) : null;
            
            // Handle file upload for update
            $receipt_url = null;
            if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/receipts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Validate file size (10MB max)
                if ($_FILES['receipt_file']['size'] > 10 * 1024 * 1024) {
                    throw new Exception('File size exceeds 10MB limit');
                }
                
                // Validate MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $_FILES['receipt_file']['tmp_name']);
                finfo_close($finfo);
                
                $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
                if (!in_array($mime_type, $allowed_mimes)) {
                    throw new Exception('Invalid file type. Only JPG, PNG, and PDF files are allowed.');
                }
                
                $file_ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
                
                if (in_array($file_ext, $allowed_exts)) {
                    $receipt_url = 'uploads/receipts/' . uniqid('receipt_') . '.' . $file_ext;
                    move_uploaded_file($_FILES['receipt_file']['tmp_name'], $receipt_url);
                    
                    // Update with new file
                    $stmt = $pdo->prepare("
                        UPDATE expenses 
                        SET category = ?, description = ?, amount = ?, expense_date = ?, receipt_url = ?,
                            vendor_name = ?, payee_id = ?, subtotal = ?, tax_amount = ?, total_amount = ?,
                            payment_method = ?, reference_number = ?, currency = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $category, $description, $total_amount, $expense_date, $receipt_url,
                        $vendor_name, $payee_id, $subtotal, $tax_amount, $total_amount,
                        $payment_method, $reference_number, $currency, $expense_id
                    ]);
                    
                    // Upload to Nextcloud
                    if (!empty($vendor_name)) {
                        $nc_result = uploadReceiptToNextcloud($pdo, $receipt_url, $expense_date, $vendor_name, $expense_id);
                        if ($nc_result['success']) {
                            $pdo->prepare("UPDATE expenses SET nextcloud_path = ? WHERE id = ?")->execute([
                                $nc_result['cloud_path'], $expense_id
                            ]);
                        }
                        
                        // Also upload to Paperless-NGX with Receipt tag
                        $receipt_title = $expense_date . '_' . $vendor_name . '_' . $expense_id;
                        uploadToPaperless($pdo, $receipt_url, 'Receipt', $receipt_title);
                    }
                }
            } else {
                // Update without changing file
                $stmt = $pdo->prepare("
                    UPDATE expenses 
                    SET category = ?, description = ?, amount = ?, expense_date = ?,
                        vendor_name = ?, payee_id = ?, subtotal = ?, tax_amount = ?, total_amount = ?,
                        payment_method = ?, reference_number = ?, currency = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $category, $description, $total_amount, $expense_date,
                    $vendor_name, $payee_id, $subtotal, $tax_amount, $total_amount,
                    $payment_method, $reference_number, $currency, $expense_id
                ]);
            }
            
            Auditor::log($pdo, $user_id, 'update', 'expenses', $expense_id, ['action' => 'updated_expense', 'category' => $category, 'amount' => $total_amount]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Expense updated successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=accounting_expenses&status=success");
            exit();
            
        case 'delete':
            $expense_id = intval($_POST['expense_id']);
            
            // Delete receipt file if exists
            $file_stmt = $pdo->prepare("SELECT receipt_url FROM expenses WHERE id = ?");
            $file_stmt->execute([$expense_id]);
            $receipt = $file_stmt->fetchColumn();
            
            // Validate path is within uploads directory and delete
            if ($receipt && strpos($receipt, 'uploads/receipts/') === 0 && file_exists($receipt)) {
                // Additional check: ensure no path traversal
                $real_path = realpath($receipt);
                $uploads_path = realpath('uploads/receipts/');
                if ($real_path && $uploads_path && strpos($real_path, $uploads_path) === 0) {
                    unlink($receipt);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
            $stmt->execute([$expense_id]);
            
            Auditor::log($pdo, $user_id, 'delete', 'expenses', $expense_id, ['action' => 'deleted_expense']);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Expense deleted successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=expenses&status=success");
            exit();
            
        case 'mark_paid':
            $expense_id = intval($_POST['expense_id'] ?? 0);
            if ($expense_id <= 0) {
                throw new Exception('Invalid expense ID');
            }
            $stmt = $pdo->prepare("UPDATE expenses SET status = 'approved' WHERE id = ?");
            $stmt->execute([$expense_id]);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Expense marked as paid']);
                exit();
            }
            header("Location: dashboard.php?page=accounts_payable&status=success");
            exit();

        case 'create_category':
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("
                INSERT INTO expense_categories (name, description, is_active)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $description, $is_active]);
            
            header("Location: dashboard.php?page=expense_categories&status=success");
            exit();
            
        case 'update_category':
            $category_id = intval($_POST['category_id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("
                UPDATE expense_categories 
                SET name = ?, description = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $is_active, $category_id]);
            
            header("Location: dashboard.php?page=expense_categories&status=success");
            exit();
            
        case 'delete_category':
            $category_id = intval($_POST['category_id']);
            
            // Check if category is in use - Get category name first, then check expenses
            $cat_stmt = $pdo->prepare("SELECT name FROM expense_categories WHERE id = ?");
            $cat_stmt->execute([$category_id]);
            $category_name = $cat_stmt->fetchColumn();
            
            if ($category_name) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE category = ?");
                $check->execute([$category_name]);
                
                if ($check->fetchColumn() > 0) {
                    header("Location: dashboard.php?page=expense_categories&status=error&message=Category+is+in+use");
                    exit();
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE id = ?");
            $stmt->execute([$category_id]);
            
            header("Location: dashboard.php?page=expense_categories&status=success");
            exit();
        
        // =====================================================
        // OCR SCANNING
        // =====================================================
        case 'ocr_scan':
            if (!isset($_FILES['receipt_file']) || $_FILES['receipt_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'No file uploaded']);
                exit();
            }
            
            // Validate file
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['receipt_file']['tmp_name']);
            finfo_close($finfo);
            
            $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!in_array($mime_type, $allowed_mimes)) {
                echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and PDF files can be scanned']);
                exit();
            }
            
            // Save temporarily with correct extension based on MIME type
            $ext = ($mime_type === 'image/png') ? '.png' : (($mime_type === 'application/pdf') ? '.pdf' : '.jpg');
            $temp_file = sys_get_temp_dir() . '/' . uniqid('ocr_') . $ext;
            move_uploaded_file($_FILES['receipt_file']['tmp_name'], $temp_file);
            
            // Perform OCR via Paperless-NGX (handles all file types including PDFs natively)
            $ocr_data = performReceiptOCR($temp_file);
            
            // Clean up
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
            
            if (!empty($ocr_data['error'])) {
                echo json_encode([
                    'success' => false,
                    'message' => $ocr_data['error']
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'ocr_data' => $ocr_data
                ]);
            }
            exit();
        
        // =====================================================
        // PAYEE MANAGEMENT
        // =====================================================
        case 'create_payee':
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Payee name is required']);
                exit();
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO payees (name, company_name, email, phone, address_line1, address_line2, 
                    city, state_province, postal_code, country, default_payment_method, 
                    bank_name, etransfer_email, tax_id, default_currency, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name,
                trim($_POST['company_name'] ?? ''),
                trim($_POST['email'] ?? ''),
                trim($_POST['phone'] ?? ''),
                trim($_POST['address_line1'] ?? ''),
                trim($_POST['address_line2'] ?? ''),
                trim($_POST['city'] ?? ''),
                trim($_POST['state_province'] ?? ''),
                trim($_POST['postal_code'] ?? ''),
                trim($_POST['country'] ?? 'Canada'),
                $_POST['default_payment_method'] ?? 'bank_transfer',
                trim($_POST['bank_name'] ?? ''),
                trim($_POST['etransfer_email'] ?? ''),
                trim($_POST['tax_id'] ?? ''),
                $_POST['default_currency'] ?? 'CAD',
                trim($_POST['notes'] ?? ''),
                $user_id
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Payee created successfully',
                'payee_id' => $pdo->lastInsertId()
            ]);
            exit();
        
        case 'update_payee':
            $payee_id = intval($_POST['payee_id']);
            $name = trim($_POST['name'] ?? '');
            
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Payee name is required']);
                exit();
            }
            
            $stmt = $pdo->prepare("
                UPDATE payees SET name = ?, company_name = ?, email = ?, phone = ?, 
                    address_line1 = ?, address_line2 = ?, city = ?, state_province = ?, 
                    postal_code = ?, country = ?, default_payment_method = ?, 
                    bank_name = ?, etransfer_email = ?, tax_id = ?, default_currency = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name,
                trim($_POST['company_name'] ?? ''),
                trim($_POST['email'] ?? ''),
                trim($_POST['phone'] ?? ''),
                trim($_POST['address_line1'] ?? ''),
                trim($_POST['address_line2'] ?? ''),
                trim($_POST['city'] ?? ''),
                trim($_POST['state_province'] ?? ''),
                trim($_POST['postal_code'] ?? ''),
                trim($_POST['country'] ?? 'Canada'),
                $_POST['default_payment_method'] ?? 'bank_transfer',
                trim($_POST['bank_name'] ?? ''),
                trim($_POST['etransfer_email'] ?? ''),
                trim($_POST['tax_id'] ?? ''),
                $_POST['default_currency'] ?? 'CAD',
                trim($_POST['notes'] ?? ''),
                $payee_id
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Payee updated successfully']);
            exit();
        
        case 'delete_payee':
            $payee_id = intval($_POST['payee_id']);
            
            // Check if payee has expenses
            $check = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE payee_id = ?");
            $check->execute([$payee_id]);
            if ($check->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Payee has associated expenses and cannot be deleted']);
                exit();
            }
            
            $stmt = $pdo->prepare("DELETE FROM payees WHERE id = ?");
            $stmt->execute([$payee_id]);
            
            echo json_encode(['success' => true, 'message' => 'Payee deleted successfully']);
            exit();
        
        case 'get_payees':
            $payees = $pdo->query("SELECT * FROM payees WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'payees' => $payees]);
            exit();
        
        // =====================================================
        // BATCH PAYMENTS
        // =====================================================
        case 'create_batch':
            $batch_name = trim($_POST['batch_name'] ?? 'Batch ' . date('Y-m-d H:i'));
            $batch_date = $_POST['batch_date'] ?? date('Y-m-d');
            $payment_method = $_POST['payment_method'] ?? 'mixed';
            $currency = $_POST['currency'] ?? 'CAD';
            
            $stmt = $pdo->prepare("
                INSERT INTO payment_batches (batch_name, batch_date, payment_method, currency, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$batch_name, $batch_date, $payment_method, $currency, $user_id]);
            
            if ($isAjax) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Batch created successfully',
                    'batch_id' => $pdo->lastInsertId()
                ]);
                exit();
            }
            header("Location: dashboard.php?page=accounts_payable&tab=batches&status=success");
            exit();
        
        case 'add_to_batch':
            $batch_id = intval($_POST['batch_id']);
            $payee_id = intval($_POST['payee_id']);
            $expense_id = !empty($_POST['expense_id']) ? intval($_POST['expense_id']) : null;
            $amount = floatval($_POST['amount']);
            $payment_method = $_POST['payment_method'] ?? 'bank_transfer';
            
            $stmt = $pdo->prepare("
                INSERT INTO batch_payments (batch_id, payee_id, expense_id, amount, payment_method, currency)
                VALUES (?, ?, ?, ?, ?, (SELECT currency FROM payment_batches WHERE id = ?))
            ");
            $stmt->execute([$batch_id, $payee_id, $expense_id, $amount, $payment_method, $batch_id]);
            
            // Update batch total
            $pdo->prepare("
                UPDATE payment_batches SET total_amount = (
                    SELECT COALESCE(SUM(amount), 0) FROM batch_payments WHERE batch_id = ?
                ) WHERE id = ?
            ")->execute([$batch_id, $batch_id]);
            
            if ($isAjax) {
                echo json_encode(['success' => true, 'message' => 'Payment added to batch']);
                exit();
            }
            header("Location: dashboard.php?page=accounts_payable&tab=batches&status=success");
            exit();
        
        case 'process_batch_payment':
            $batch_id = intval($_POST['batch_id']);
            
            // Update batch status to processing
            $pdo->prepare("UPDATE payment_batches SET status = 'processing' WHERE id = ?")->execute([$batch_id]);
            
            // Get all payments in batch
            $payments = $pdo->prepare("SELECT * FROM batch_payments WHERE batch_id = ? AND status = 'pending'");
            $payments->execute([$batch_id]);
            
            $processed = 0;
            $failed = 0;
            
            while ($payment = $payments->fetch(PDO::FETCH_ASSOC)) {
                try {
                    // For Stripe payments, we would process through Stripe API here
                    // For now, mark as completed
                    $pdo->prepare("
                        UPDATE batch_payments SET status = 'completed', processed_at = NOW() WHERE id = ?
                    ")->execute([$payment['id']]);
                    $processed++;
                } catch (Exception $e) {
                    $pdo->prepare("
                        UPDATE batch_payments SET status = 'failed', notes = CONCAT(COALESCE(notes, ''), '\nError: ', ?) WHERE id = ?
                    ")->execute([$e->getMessage(), $payment['id']]);
                    $failed++;
                }
            }
            
            // Update batch status
            $final_status = $failed === 0 ? 'completed' : ($processed === 0 ? 'failed' : 'completed');
            $pdo->prepare("
                UPDATE payment_batches SET status = ?, processed_at = NOW() WHERE id = ?
            ")->execute([$final_status, $batch_id]);
            
            echo json_encode([
                'success' => true,
                'message' => "Processed $processed payments" . ($failed > 0 ? ", $failed failed" : "")
            ]);
            exit();
        
        // =====================================================
        // STRIPE VIRTUAL CARDS
        // =====================================================
        case 'create_virtual_card':
            // Get Stripe secret key
            $stripe_key_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'stripe_secret_key'");
            $stripe_secret = $stripe_key_stmt->fetchColumn();
            
            if (empty($stripe_secret)) {
                echo json_encode(['success' => false, 'message' => 'Stripe not configured']);
                exit();
            }
            
            $cardholder_name = trim($_POST['cardholder_name'] ?? '');
            $cardholder_email = trim($_POST['cardholder_email'] ?? '');
            $card_name = trim($_POST['card_name'] ?? '');
            $spending_limit = floatval($_POST['spending_limit'] ?? 500);
            $currency = strtolower($_POST['currency'] ?? 'cad');
            
            if (empty($cardholder_name) || empty($cardholder_email)) {
                echo json_encode(['success' => false, 'message' => 'Cardholder name and email are required']);
                exit();
            }
            
            try {
                // Check Stripe library exists
                $stripe_lib_path = __DIR__ . '/stripe-php/init.php';
                if (!file_exists($stripe_lib_path)) {
                    throw new Exception('Stripe library not installed. Please install it from System Tools.');
                }
                require_once $stripe_lib_path;
                \Stripe\Stripe::setApiKey($stripe_secret);
                
                // Check for existing cardholder or create new one
                $ch_check = $pdo->prepare("SELECT id, stripe_cardholder_id FROM stripe_cardholders WHERE email = ?");
                $ch_check->execute([$cardholder_email]);
                $existing_ch = $ch_check->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_ch) {
                    $cardholder_id = $existing_ch['stripe_cardholder_id'];
                    $local_ch_id = $existing_ch['id'];
                } else {
                    // Create Stripe cardholder
                    $cardholder = \Stripe\Issuing\Cardholder::create([
                        'name' => $cardholder_name,
                        'email' => $cardholder_email,
                        'type' => 'individual',
                        'billing' => [
                            'address' => [
                                'line1' => 'Address pending',
                                'city' => 'City',
                                'state' => 'ON',
                                'postal_code' => 'A1A1A1',
                                'country' => 'CA',
                            ],
                        ],
                    ]);
                    
                    $cardholder_id = $cardholder->id;
                    
                    // Save cardholder locally
                    $pdo->prepare("
                        INSERT INTO stripe_cardholders (user_id, stripe_cardholder_id, name, email, type, status)
                        VALUES (?, ?, ?, ?, 'individual', 'active')
                    ")->execute([$user_id, $cardholder_id, $cardholder_name, $cardholder_email]);
                    
                    $local_ch_id = $pdo->lastInsertId();
                }
                
                // Create virtual card
                $card = \Stripe\Issuing\Card::create([
                    'cardholder' => $cardholder_id,
                    'currency' => $currency,
                    'type' => 'virtual',
                    'spending_controls' => [
                        'spending_limits' => [
                            [
                                'amount' => intval($spending_limit * 100), // Amount in cents
                                'interval' => 'monthly',
                            ],
                        ],
                    ],
                ]);
                
                // Save card locally
                $pdo->prepare("
                    INSERT INTO stripe_virtual_cards (cardholder_id, stripe_card_id, card_name, last4, brand, 
                        exp_month, exp_year, currency, status, spending_limit, spending_limit_interval)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'inactive', ?, 'monthly')
                ")->execute([
                    $local_ch_id,
                    $card->id,
                    $card_name ?: 'Virtual Card ' . $card->last4,
                    $card->last4,
                    $card->brand,
                    $card->exp_month,
                    $card->exp_year,
                    $currency,
                    $spending_limit
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Virtual card created successfully',
                    'card' => [
                        'id' => $card->id,
                        'last4' => $card->last4,
                        'exp_month' => $card->exp_month,
                        'exp_year' => $card->exp_year,
                        'status' => $card->status
                    ]
                ]);
                
            } catch (\Stripe\Exception\ApiErrorException $e) {
                echo json_encode(['success' => false, 'message' => 'Stripe error: ' . $e->getMessage()]);
            }
            exit();
        
        case 'list_virtual_cards':
            $cards = $pdo->query("
                SELECT vc.*, ch.name as cardholder_name, ch.email as cardholder_email
                FROM stripe_virtual_cards vc
                JOIN stripe_cardholders ch ON vc.cardholder_id = ch.id
                ORDER BY vc.created_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'cards' => $cards]);
            exit();
        
        case 'activate_card':
            $card_id = intval($_POST['card_id']);
            
            // Get Stripe info
            $card_info = $pdo->prepare("SELECT stripe_card_id FROM stripe_virtual_cards WHERE id = ?");
            $card_info->execute([$card_id]);
            $stripe_card_id = $card_info->fetchColumn();
            
            if (!$stripe_card_id) {
                echo json_encode(['success' => false, 'message' => 'Card not found']);
                exit();
            }
            
            try {
                $stripe_key_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'stripe_secret_key'");
                $stripe_secret = $stripe_key_stmt->fetchColumn();
                
                // Check Stripe library exists
                $stripe_lib_path = __DIR__ . '/stripe-php/init.php';
                if (!file_exists($stripe_lib_path)) {
                    throw new Exception('Stripe library not installed');
                }
                require_once $stripe_lib_path;
                \Stripe\Stripe::setApiKey($stripe_secret);
                
                \Stripe\Issuing\Card::update($stripe_card_id, ['status' => 'active']);
                
                $pdo->prepare("UPDATE stripe_virtual_cards SET status = 'active' WHERE id = ?")->execute([$card_id]);
                
                echo json_encode(['success' => true, 'message' => 'Card activated']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
        
        // =====================================================
        // EXPORT FUNCTIONALITY
        // =====================================================
        case 'export_expenses':
            $export_type = $_POST['export_type'] ?? 'month';
            $year = intval($_POST['year'] ?? date('Y'));
            $month = intval($_POST['month'] ?? date('m'));
            $quarter = intval($_POST['quarter'] ?? ceil(date('m') / 3));
            $week = intval($_POST['week'] ?? date('W'));
            $include_receipts = isset($_POST['include_receipts']) && $_POST['include_receipts'] !== 'false';
            
            // Calculate date range based on export type
            switch ($export_type) {
                case 'week':
                    $period_start = date('Y-m-d', strtotime("$year-W" . str_pad($week, 2, '0', STR_PAD_LEFT)));
                    $period_end = date('Y-m-d', strtotime("$year-W" . str_pad($week, 2, '0', STR_PAD_LEFT) . "-7"));
                    break;
                case 'month':
                    $period_start = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
                    $period_end = date('Y-m-t', strtotime($period_start));
                    break;
                case 'quarter':
                    $q_month = ($quarter - 1) * 3 + 1;
                    $period_start = "$year-" . str_pad($q_month, 2, '0', STR_PAD_LEFT) . "-01";
                    $end_month = $q_month + 2;
                    $period_end = date('Y-m-t', strtotime("$year-" . str_pad($end_month, 2, '0', STR_PAD_LEFT) . "-01"));
                    break;
                case 'year':
                    $period_start = "$year-01-01";
                    $period_end = "$year-12-31";
                    break;
                default:
                    $period_start = $_POST['start_date'] ?? date('Y-m-01');
                    $period_end = $_POST['end_date'] ?? date('Y-m-d');
            }
            
            // Get expenses for period
            $expenses_stmt = $pdo->prepare("
                SELECT e.*, p.name as payee_name
                FROM expenses e
                LEFT JOIN payees p ON e.payee_id = p.id
                WHERE e.expense_date BETWEEN ? AND ?
                ORDER BY e.expense_date ASC
            ");
            $expenses_stmt->execute([$period_start, $period_end]);
            $expenses = $expenses_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get line items for each expense
            foreach ($expenses as &$expense) {
                $items_stmt = $pdo->prepare("SELECT * FROM expense_line_items WHERE expense_id = ?");
                $items_stmt->execute([$expense['id']]);
                $expense['line_items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Calculate totals
            $total_amount = array_sum(array_column($expenses, 'total_amount'));
            $total_tax = array_sum(array_column($expenses, 'tax_amount'));
            $expense_count = count($expenses);
            
            // Create export record
            $export_name = ucfirst($export_type) . " Export - " . ($export_type === 'year' ? $year : date('M Y', strtotime($period_start)));
            $pdo->prepare("
                INSERT INTO expense_exports (export_name, export_type, period_start, period_end, year, 
                    total_expenses, expense_count, includes_receipts, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)
            ")->execute([
                $export_name, $export_type, $period_start, $period_end, $year,
                $total_amount, $expense_count, $include_receipts ? 1 : 0, $user_id
            ]);
            
            echo json_encode([
                'success' => true,
                'export' => [
                    'name' => $export_name,
                    'type' => $export_type,
                    'period_start' => $period_start,
                    'period_end' => $period_end,
                    'total_amount' => $total_amount,
                    'total_tax' => $total_tax,
                    'expense_count' => $expense_count,
                    'expenses' => $expenses
                ]
            ]);
            exit();
        
        case 'get_available_years':
            // Get system activation year
            $activation = $pdo->query("SELECT activation_year FROM system_activation ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $start_year = $activation ? $activation['activation_year'] : 2026;
            $current_year = intval(date('Y'));
            
            $years = [];
            for ($y = $start_year; $y <= $current_year; $y++) {
                $years[] = $y;
            }
            
            echo json_encode(['success' => true, 'years' => $years, 'current_year' => $current_year]);
            exit();
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    ErrorLogger::error("Expense processing error: " . $e->getMessage());
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
    
    $redirect_page = 'accounting_expenses';
    if ($action === 'create_category' || $action === 'update_category' || $action === 'delete_category') {
        $redirect_page = 'expense_categories';
    }
    header("Location: dashboard.php?page=$redirect_page&status=error&message=" . urlencode($e->getMessage()));
    exit();
}
?>
