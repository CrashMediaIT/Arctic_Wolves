<?php
/**
 * Process Recurring Expenses
 * Handles CRUD operations for recurring expenses/contracts and document uploads
 * Documents are stored in Nextcloud under /accounting/contracts/
 */
session_start();
require_once 'db_config.php';
require_once 'security.php';

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

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

// Allowed MIME types for contract document uploads
$allowed_mime_types = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg',
    'image/png'
];

/**
 * Upload contract document to Nextcloud
 * Structure: /accounting/contracts/{CompanyName}/{ContractPurpose}_{StartDate}/
 */
function uploadContractToNextcloud($pdo, $local_file_path, $vendor_name, $contract_type, $start_date, $original_filename) {
    try {
        $settings = getNextcloudSettings($pdo);
        
        if (empty($settings['nextcloud_url']) || empty($settings['nextcloud_username'])) {
            return ['success' => false, 'message' => 'Nextcloud not configured'];
        }
        
        $connection = connectNextcloud($settings);
        $contracts_dir = $settings['nextcloud_contracts_dir'] ?? '/accounting/contracts';
        
        // Sanitize vendor name for folder
        $safe_vendor = preg_replace('/[^a-zA-Z0-9\-_ ]/', '', $vendor_name);
        $safe_vendor = trim(substr($safe_vendor, 0, 50));
        if (empty($safe_vendor)) $safe_vendor = 'Unknown_Vendor';
        
        // Create contract purpose folder name
        $safe_type = preg_replace('/[^a-zA-Z0-9\-_ ]/', '', $contract_type ?: 'General');
        $safe_type = trim(substr($safe_type, 0, 50));
        $date_str = date('Y-m-d', strtotime($start_date ?: 'now'));
        $purpose_folder = $safe_type . '_' . $date_str;
        
        // Create folder structure: /accounting/contracts/{CompanyName}/{ContractPurpose_StartDate}/
        $folder_path = ensureNextcloudPath($connection, $contracts_dir, [$safe_vendor, $purpose_folder]);
        
        // Upload file
        $file_content = file_get_contents($local_file_path);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $content_type = finfo_file($finfo, $local_file_path);
        finfo_close($finfo);
        
        // Sanitize filename
        $safe_filename = preg_replace('/[^a-zA-Z0-9\-_.]/', '_', $original_filename);
        $remote_path = $folder_path . '/' . $safe_filename;
        
        uploadToNextcloud($connection, $remote_path, $file_content, $content_type);
        
        return [
            'success' => true,
            'cloud_path' => $remote_path,
            'filename' => $safe_filename
        ];
        
    } catch (Exception $e) {
        error_log("Nextcloud contract upload error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

try {
    switch ($action) {
        case 'create':
            $vendor_name = trim($_POST['vendor_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $contract_type = trim($_POST['contract_type'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $frequency = $_POST['frequency'] ?? 'monthly';
            $contract_start_date = $_POST['contract_start_date'] ?? null;
            $contract_end_date = !empty($_POST['contract_end_date']) ? $_POST['contract_end_date'] : null;
            $renewal_date = !empty($_POST['renewal_date']) ? $_POST['renewal_date'] : null;
            $next_payment_date = !empty($_POST['next_payment_date']) ? $_POST['next_payment_date'] : null;
            $payment_method = $_POST['payment_method'] ?? null;
            $category = $_POST['category'] ?? null;
            $notes = trim($_POST['notes'] ?? '');
            $auto_renew = isset($_POST['auto_renew']) ? 1 : 0;
            $contact_name = trim($_POST['contact_name'] ?? '');
            $contact_email = trim($_POST['contact_email'] ?? '');
            $contact_phone = trim($_POST['contact_phone'] ?? '');
            $company_phone = trim($_POST['company_phone'] ?? '');
            $company_email = trim($_POST['company_email'] ?? '');
            
            if (empty($vendor_name) || $amount <= 0 || empty($contract_start_date)) {
                header("Location: dashboard.php?page=expenses&expenses_tab=recurring&status=error&message=" . urlencode('Vendor name, amount, and start date are required'));
                exit();
            }
            
            if (!in_array($frequency, ['monthly', 'quarterly', 'semi_annual', 'annual'])) {
                $frequency = 'monthly';
            }
            
            $stmt = $pdo->prepare("INSERT INTO recurring_expenses 
                (vendor_name, description, contract_type, amount, frequency, contract_start_date, contract_end_date, 
                 renewal_date, next_payment_date, payment_method, category, notes, auto_renew, 
                 contact_name, contact_email, contact_phone, company_phone, company_email, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
            $stmt->execute([
                $vendor_name, $description, $contract_type, $amount, $frequency,
                $contract_start_date, $contract_end_date, $renewal_date, $next_payment_date,
                $payment_method, $category, $notes, $auto_renew,
                $contact_name ?: null, $contact_email ?: null, $contact_phone ?: null,
                $company_phone ?: null, $company_email ?: null, $user_id
            ]);
            
            $recurring_id = $pdo->lastInsertId();
            $nextcloud_path = null;
            
            // Handle contract file upload
            if (!empty($_FILES['contract_file']['name']) && $_FILES['contract_file']['error'] === UPLOAD_ERR_OK) {
                $validation = validateFileUpload($_FILES['contract_file'], $allowed_mime_types);
                if (!$validation['success']) {
                    header("Location: dashboard.php?page=expenses&expenses_tab=recurring&status=error&message=" . urlencode('Contract file: ' . $validation['error']));
                    exit();
                }
                $tmp_path = $_FILES['contract_file']['tmp_name'];
                $original_name = $_FILES['contract_file']['name'];
                $file_size = $_FILES['contract_file']['size'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $tmp_path);
                finfo_close($finfo);
                
                // Upload to Nextcloud
                $upload_result = null;
                if (function_exists('getNextcloudSettings')) {
                    $upload_result = uploadContractToNextcloud($pdo, $tmp_path, $vendor_name, $contract_type, $contract_start_date, $original_name);
                }
                
                // Save to local uploads as fallback
                $local_path = 'uploads/contracts/' . $recurring_id . '_' . basename($original_name);
                if (!is_dir('uploads/contracts')) {
                    mkdir('uploads/contracts', 0755, true);
                }
                move_uploaded_file($tmp_path, $local_path);
                
                $nc_path = ($upload_result && $upload_result['success']) ? $upload_result['cloud_path'] : null;
                if ($nc_path) $nextcloud_path = $nc_path;
                
                $doc_stmt = $pdo->prepare("INSERT INTO recurring_expense_documents 
                    (recurring_expense_id, document_type, file_name, file_path, nextcloud_path, file_size, mime_type, uploaded_by) 
                    VALUES (?, 'contract', ?, ?, ?, ?, ?, ?)");
                $doc_stmt->execute([$recurring_id, $original_name, $local_path, $nc_path, $file_size, $mime_type, $user_id]);
            }
            
            // Handle additional files
            if (!empty($_FILES['additional_files']['name'][0])) {
                for ($i = 0; $i < count($_FILES['additional_files']['name']); $i++) {
                    if ($_FILES['additional_files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    
                    // Validate file type server-side
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $detected_mime = finfo_file($finfo, $_FILES['additional_files']['tmp_name'][$i]);
                    finfo_close($finfo);
                    if (!in_array($detected_mime, $allowed_mime_types)) continue;
                    
                    $tmp_path = $_FILES['additional_files']['tmp_name'][$i];
                    $original_name = $_FILES['additional_files']['name'][$i];
                    $file_size = $_FILES['additional_files']['size'][$i];
                    $mime_type = $detected_mime;
                    
                    $upload_result = null;
                    if (function_exists('getNextcloudSettings')) {
                        $upload_result = uploadContractToNextcloud($pdo, $tmp_path, $vendor_name, $contract_type, $contract_start_date, $original_name);
                    }
                    
                    $local_path = 'uploads/contracts/' . $recurring_id . '_' . $i . '_' . basename($original_name);
                    move_uploaded_file($tmp_path, $local_path);
                    
                    $nc_path = ($upload_result && $upload_result['success']) ? $upload_result['cloud_path'] : null;
                    
                    $doc_stmt = $pdo->prepare("INSERT INTO recurring_expense_documents 
                        (recurring_expense_id, document_type, file_name, file_path, nextcloud_path, file_size, mime_type, uploaded_by) 
                        VALUES (?, 'other', ?, ?, ?, ?, ?, ?)");
                    $doc_stmt->execute([$recurring_id, $original_name, $local_path, $nc_path, $file_size, $mime_type, $user_id]);
                }
            }
            
            // Update the recurring expense with the Nextcloud path
            if ($nextcloud_path) {
                $pdo->prepare("UPDATE recurring_expenses SET nextcloud_path = ? WHERE id = ?")->execute([$nextcloud_path, $recurring_id]);
            }
            
            header("Location: dashboard.php?page=expenses&expenses_tab=recurring&status=success");
            exit();
            break;
            
        case 'upload_documents':
            $recurring_expense_id = intval($_POST['recurring_expense_id'] ?? 0);
            $document_type = $_POST['document_type'] ?? 'other';
            
            if ($recurring_expense_id <= 0) {
                header("Location: dashboard.php?page=expenses&expenses_tab=recurring&status=error&message=" . urlencode('Invalid recurring expense'));
                exit();
            }
            
            // Get the recurring expense details
            $re_stmt = $pdo->prepare("SELECT * FROM recurring_expenses WHERE id = ?");
            $re_stmt->execute([$recurring_expense_id]);
            $recurring_expense = $re_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$recurring_expense) {
                header("Location: dashboard.php?page=expenses&expenses_tab=recurring&status=error&message=" . urlencode('Recurring expense not found'));
                exit();
            }
            
            if (!in_array($document_type, ['contract', 'insurance', 'invoice', 'amendment', 'other'])) {
                $document_type = 'other';
            }
            
            if (!empty($_FILES['documents']['name'][0])) {
                if (!is_dir('uploads/contracts')) {
                    mkdir('uploads/contracts', 0755, true);
                }
                
                for ($i = 0; $i < count($_FILES['documents']['name']); $i++) {
                    if ($_FILES['documents']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    
                    // Validate file type server-side
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $detected_mime = finfo_file($finfo, $_FILES['documents']['tmp_name'][$i]);
                    finfo_close($finfo);
                    if (!in_array($detected_mime, $allowed_mime_types)) continue;
                    
                    $tmp_path = $_FILES['documents']['tmp_name'][$i];
                    $original_name = $_FILES['documents']['name'][$i];
                    $file_size = $_FILES['documents']['size'][$i];
                    $mime_type = $detected_mime;
                    
                    $upload_result = null;
                    if (function_exists('getNextcloudSettings')) {
                        $upload_result = uploadContractToNextcloud(
                            $pdo, $tmp_path, 
                            $recurring_expense['vendor_name'], 
                            $recurring_expense['contract_type'], 
                            $recurring_expense['contract_start_date'], 
                            $original_name
                        );
                    }
                    
                    $local_path = 'uploads/contracts/' . $recurring_expense_id . '_' . time() . '_' . $i . '_' . basename($original_name);
                    move_uploaded_file($tmp_path, $local_path);
                    
                    $nc_path = ($upload_result && $upload_result['success']) ? $upload_result['cloud_path'] : null;
                    
                    $doc_stmt = $pdo->prepare("INSERT INTO recurring_expense_documents 
                        (recurring_expense_id, document_type, file_name, file_path, nextcloud_path, file_size, mime_type, uploaded_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $doc_stmt->execute([$recurring_expense_id, $document_type, $original_name, $local_path, $nc_path, $file_size, $mime_type, $user_id]);
                }
            }
            
            header("Location: dashboard.php?page=expenses&expenses_tab=recurring&status=success");
            exit();
            break;
            
        default:
            header("Location: dashboard.php?page=expenses&expenses_tab=recurring");
            exit();
    }
} catch (Exception $e) {
    error_log("Recurring expense error: " . $e->getMessage());
    header("Location: dashboard.php?page=expenses&expenses_tab=recurring&status=error&message=" . urlencode('An error occurred: ' . $e->getMessage()));
    exit();
}
?>
