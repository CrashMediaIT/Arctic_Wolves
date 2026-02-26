<?php
/**
 * Process Employee Contract Actions
 * Handles contract creation, e-signature requests, and signed document processing
 * Uses DocuSeal API for e-signature handling
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once 'cloud_config.php';
require_once 'lib/docuseal.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Set security headers
setSecurityHeaders();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Validate CSRF token (except for webhook callbacks)
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action !== 'webhook_callback') {
    checkCsrfToken();
}

$user_id = $_SESSION['user_id'];

// JSON actions that return JSON response
$json_actions = ['create', 'send_for_signature', 'get_status', 'list_contracts', 'list_templates', 'resend', 'cancel', 
                 'docuseal_create_template', 'docuseal_update_template', 'docuseal_delete_template', 
                 'docuseal_clone_template', 'docuseal_get_template', 'docuseal_list_templates'];
$is_json = in_array($action, $json_actions);

if ($is_json) {
    header('Content-Type: application/json');
}

try {
    switch ($action) {
        
        /**
         * Create a new contract from template
         */
        case 'create':
            $templateId = intval($_POST['template_id'] ?? 0);
            $onboardingId = !empty($_POST['onboarding_id']) ? intval($_POST['onboarding_id']) : null;
            $employeeName = trim($_POST['employee_name'] ?? '');
            $employeeEmail = trim($_POST['employee_email'] ?? '');
            $contractTitle = trim($_POST['contract_title'] ?? 'Employment Contract');
            $contractData = $_POST['contract_data'] ?? [];
            
            // Validation
            if (empty($employeeName) || empty($employeeEmail)) {
                throw new Exception('Employee name and email are required');
            }
            
            if (!filter_var($employeeEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address');
            }
            
            // Get template if specified
            $template = null;
            if ($templateId > 0) {
                $stmt = $pdo->prepare("SELECT * FROM contract_templates WHERE id = ? AND is_active = 1");
                $stmt->execute([$templateId]);
                $template = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$template) {
                    throw new Exception('Template not found or inactive');
                }
            }
            
            // If onboarding ID is provided and employee details are empty, use onboarding data as fallback
            if ($onboardingId) {
                $stmt = $pdo->prepare("SELECT * FROM employee_onboarding WHERE id = ?");
                $stmt->execute([$onboardingId]);
                $onboarding = $stmt->fetch(PDO::FETCH_ASSOC);
                $onboarding = decryptUserRow($onboarding);
                
                if ($onboarding) {
                    // Only use onboarding data as fallback if user didn't provide values
                    if (empty($employeeName)) {
                        $employeeName = $onboarding['first_name'] . ' ' . $onboarding['last_name'];
                    }
                    if (empty($employeeEmail)) {
                        $employeeEmail = $onboarding['email'];
                    }
                }
            }
            
            // Create contract record
            $stmt = $pdo->prepare("
                INSERT INTO employee_contracts 
                (onboarding_id, template_id, employee_name, employee_email, contract_title, 
                 contract_data, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, NOW())
            ");
            $stmt->execute([
                $onboardingId,
                $templateId > 0 ? $templateId : null,
                $employeeName,
                $employeeEmail,
                $contractTitle,
                json_encode($contractData),
                $user_id
            ]);
            
            $contractId = $pdo->lastInsertId();
            
            // Audit log
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, table_name, record_id, new_values, ip_address, created_at)
                VALUES (?, 'CREATE', 'employee_contracts', ?, ?, ?, NOW())
            ");
            $auditStmt->execute([
                $user_id,
                $contractId,
                json_encode(['employee_name' => $employeeName, 'template_id' => $templateId]),
                getClientIP()
            ]);
            Auditor::log($pdo, $user_id, 'create', 'employee_contracts', $contractId, ['action' => 'Created contract', 'employee_name' => $employeeName]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Contract created successfully',
                'contract_id' => $contractId
            ]);
            break;
            
        /**
         * Send contract for e-signature
         */
        case 'send_for_signature':
            $contractId = intval($_POST['contract_id'] ?? 0);
            $docusealTemplateId = intval($_POST['docuseal_template_id'] ?? 0);
            
            if ($contractId <= 0) {
                throw new Exception('Invalid contract ID');
            }
            
            // Get contract
            $stmt = $pdo->prepare("
                SELECT ec.*, ct.docuseal_template_id as template_docuseal_id, ct.name as template_name
                FROM employee_contracts ec
                LEFT JOIN contract_templates ct ON ec.template_id = ct.id
                WHERE ec.id = ?
            ");
            $stmt->execute([$contractId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contract) {
                throw new Exception('Contract not found');
            }
            
            if ($contract['status'] !== 'draft') {
                throw new Exception('Contract has already been sent or signed');
            }
            
            // Get DocuSeal settings
            $settings = getDocuSealSettings($pdo);
            
            if (empty($settings['docuseal_enabled']) || $settings['docuseal_enabled'] !== '1') {
                throw new Exception('DocuSeal is not enabled. Please configure it in System Tools.');
            }
            
            // Use template DocuSeal ID from POST or from linked template
            $templateId = $docusealTemplateId > 0 ? $docusealTemplateId : ($contract['template_docuseal_id'] ?? 0);
            
            if ($templateId <= 0) {
                throw new Exception('No DocuSeal template selected. Please select a template or configure the DocuSeal template ID.');
            }
            
            // Parse contract data for pre-filling
            $contractData = json_decode($contract['contract_data'], true) ?? [];
            
            // Create e-signature request via DocuSeal
            $esignResult = createEsignatureRequest(
                $pdo,
                $contractId,
                $templateId,
                $contract['employee_email'],
                $contract['employee_name'],
                $contractData
            );
            
            if (!$esignResult['success']) {
                throw new Exception('Failed to create e-signature request: ' . $esignResult['message']);
            }
            
            // Audit log
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, table_name, record_id, new_values, ip_address, created_at)
                VALUES (?, 'UPDATE', 'employee_contracts', ?, ?, ?, NOW())
            ");
            $auditStmt->execute([
                $user_id,
                $contractId,
                json_encode(['action' => 'sent_for_signature', 'docuseal_template_id' => $templateId]),
                getClientIP()
            ]);
            Auditor::log($pdo, $user_id, 'update', 'employee_contracts', $contractId, ['action' => 'Sent for signature']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Contract sent for signature via DocuSeal. The employee will receive an email with signing instructions.',
                'signing_url' => $esignResult['signing_url'] ?? null,
                'expires_at' => $esignResult['expires_at'] ?? null
            ]);
            break;
            
        /**
         * Get contract status
         */
        case 'get_status':
            $contractId = intval($_POST['contract_id'] ?? $_GET['contract_id'] ?? 0);
            
            if ($contractId <= 0) {
                throw new Exception('Invalid contract ID');
            }
            
            $contract = getContractStatus($pdo, $contractId);
            
            if (!$contract) {
                throw new Exception('Contract not found');
            }
            
            echo json_encode([
                'success' => true,
                'contract' => [
                    'id' => $contract['id'],
                    'employee_name' => $contract['employee_name'],
                    'employee_email' => $contract['employee_email'],
                    'contract_title' => $contract['contract_title'],
                    'template_name' => $contract['template_name'],
                    'status' => $contract['status'],
                    'sent_at' => $contract['sent_at'],
                    'signed_at' => $contract['signed_at'],
                    'signed_date' => $contract['signed_date'],
                    'nextcloud_path' => $contract['nextcloud_path']
                ]
            ]);
            break;
            
        /**
         * List all contracts
         */
        case 'list_contracts':
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(100, max(1, intval($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $perPage;
            $status = $_GET['status'] ?? null;
            
            $whereClause = '';
            $params = [];
            
            if ($status && in_array($status, ['draft', 'pending_signature', 'signed', 'expired', 'cancelled'])) {
                $whereClause = 'WHERE ec.status = ?';
                $params[] = $status;
            }
            
            // Get total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM employee_contracts ec $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // Get contracts
            $params[] = $perPage;
            $params[] = $offset;
            
            $stmt = $pdo->prepare("
                SELECT ec.*, ct.name as template_name,
                       u.first_name as created_first, u.last_name as created_last
                FROM employee_contracts ec
                LEFT JOIN contract_templates ct ON ec.template_id = ct.id
                LEFT JOIN users u ON ec.created_by = u.id
                $whereClause
                ORDER BY ec.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute($params);
            $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'contracts' => $contracts,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($total / $perPage)
                ]
            ]);
            break;
            
        /**
         * List contract templates
         */
        case 'list_templates':
            $templates = listContractTemplates($pdo);
            
            echo json_encode([
                'success' => true,
                'templates' => $templates
            ]);
            break;
            
        /**
         * Resend e-signature request
         */
        case 'resend':
            $contractId = intval($_POST['contract_id'] ?? 0);
            
            if ($contractId <= 0) {
                throw new Exception('Invalid contract ID');
            }
            
            // Get contract
            $stmt = $pdo->prepare("SELECT * FROM employee_contracts WHERE id = ?");
            $stmt->execute([$contractId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contract) {
                throw new Exception('Contract not found');
            }
            
            if ($contract['status'] !== 'pending_signature') {
                throw new Exception('Contract is not pending signature');
            }
            
            // Check if we have a DocuSeal signing URL
            if (empty($contract['signing_url'])) {
                throw new Exception('No signing URL available. The contract may need to be re-sent via DocuSeal.');
            }
            
            // Check if signing link hasn't expired
            if (!empty($contract['signing_token_expires']) && strtotime($contract['signing_token_expires']) < time()) {
                throw new Exception('Signing link has expired. Please create a new e-signature request.');
            }
            
            // Resend email with DocuSeal signing URL
            $emailSent = sendEsignatureRequestEmail(
                $contract['employee_email'],
                $contract['employee_name'],
                $contract['signing_url'],
                $contract['contract_title']
            );
            
            echo json_encode([
                'success' => true,
                'message' => $emailSent ? 'E-signature request resent successfully' : 'Failed to send email',
                'email_sent' => $emailSent
            ]);
            break;
            
        /**
         * Cancel a contract
         */
        case 'cancel':
            $contractId = intval($_POST['contract_id'] ?? 0);
            
            if ($contractId <= 0) {
                throw new Exception('Invalid contract ID');
            }
            
            // Get contract
            $stmt = $pdo->prepare("SELECT * FROM employee_contracts WHERE id = ?");
            $stmt->execute([$contractId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contract) {
                throw new Exception('Contract not found');
            }
            
            if ($contract['status'] === 'signed') {
                throw new Exception('Cannot cancel a signed contract');
            }
            
            // Update status
            $stmt = $pdo->prepare("
                UPDATE employee_contracts 
                SET status = 'cancelled', 
                    signing_token = NULL,
                    signing_token_expires = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$contractId]);
            
            // Clean up temp file
            if (!empty($contract['temp_file_path']) && file_exists($contract['temp_file_path'])) {
                unlink($contract['temp_file_path']);
            }
            
            // Audit log
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, table_name, record_id, new_values, ip_address, created_at)
                VALUES (?, 'UPDATE', 'employee_contracts', ?, ?, ?, NOW())
            ");
            $auditStmt->execute([
                $user_id,
                $contractId,
                json_encode(['action' => 'cancelled']),
                getClientIP()
            ]);
            Auditor::log($pdo, $user_id, 'update', 'employee_contracts', $contractId, ['action' => 'Contract cancelled']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Contract cancelled successfully'
            ]);
            break;
            
        /**
         * DocuSeal Template Management - Create template from uploaded file
         */
        case 'docuseal_create_template':
            $templateName = trim($_POST['template_name'] ?? '');
            
            if (empty($templateName)) {
                throw new Exception('Template name is required');
            }
            
            if (!isset($_FILES['template_file']) || $_FILES['template_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Please upload a valid PDF or DOCX file');
            }
            
            $file = $_FILES['template_file'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Validate file type
            if (!in_array($extension, ['pdf', 'docx', 'doc'])) {
                throw new Exception('Only PDF and DOCX files are allowed');
            }
            
            // Validate file size (max 50MB)
            if ($file['size'] > 50 * 1024 * 1024) {
                throw new Exception('File size must not exceed 50MB');
            }
            
            // Get DocuSeal settings
            $settings = getDocuSealSettings($pdo);
            
            if (empty($settings['docuseal_enabled']) || $settings['docuseal_enabled'] !== '1') {
                throw new Exception('DocuSeal is not enabled. Please configure it in System Tools.');
            }
            
            // Create template in DocuSeal
            $result = createDocuSealTemplateFromUpload($pdo, $settings, $file, $templateName);
            
            if (!$result['success']) {
                throw new Exception('Failed to create template: ' . $result['message']);
            }
            
            // Audit log
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, table_name, record_id, new_values, ip_address, created_at)
                VALUES (?, 'CREATE', 'docuseal_templates', ?, ?, ?, NOW())
            ");
            $docusealTemplateId = $result['template']['id'] ?? 0;
            $auditStmt->execute([
                $user_id,
                $docusealTemplateId,
                json_encode(['name' => $templateName, 'file' => $file['name']]),
                getClientIP()
            ]);
            Auditor::log($pdo, $user_id, 'create', 'docuseal_templates', $docusealTemplateId, ['action' => 'Created DocuSeal template', 'name' => $templateName]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template created successfully in DocuSeal',
                'template' => $result['template']
            ]);
            break;
            
        /**
         * DocuSeal Template Management - Update template
         */
        case 'docuseal_update_template':
            $templateId = intval($_POST['template_id'] ?? 0);
            $templateName = trim($_POST['template_name'] ?? '');
            $externalId = trim($_POST['external_id'] ?? '');
            $folderName = trim($_POST['folder_name'] ?? '');
            
            if ($templateId <= 0) {
                throw new Exception('Invalid template ID');
            }
            
            if (empty($templateName)) {
                throw new Exception('Template name is required');
            }
            
            // Get DocuSeal settings
            $settings = getDocuSealSettings($pdo);
            
            if (empty($settings['docuseal_enabled']) || $settings['docuseal_enabled'] !== '1') {
                throw new Exception('DocuSeal is not enabled');
            }
            
            // Build update data
            $updateData = ['name' => $templateName];
            if (!empty($externalId)) {
                $updateData['external_id'] = $externalId;
            }
            if (!empty($folderName)) {
                $updateData['folder_name'] = $folderName;
            }
            
            // Update template in DocuSeal
            $result = updateDocuSealTemplate($settings, $templateId, $updateData);
            
            if (!$result['success']) {
                throw new Exception('Failed to update template: ' . $result['message']);
            }
            
            // Audit log
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, table_name, record_id, new_values, ip_address, created_at)
                VALUES (?, 'UPDATE', 'docuseal_templates', ?, ?, ?, NOW())
            ");
            $auditStmt->execute([
                $user_id,
                $templateId,
                json_encode($updateData),
                getClientIP()
            ]);
            Auditor::log($pdo, $user_id, 'update', 'docuseal_templates', $templateId, ['action' => 'Updated DocuSeal template']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template updated successfully',
                'template' => $result['template']
            ]);
            break;
            
        /**
         * DocuSeal Template Management - Delete template
         */
        case 'docuseal_delete_template':
            $templateId = intval($_POST['template_id'] ?? 0);
            
            if ($templateId <= 0) {
                throw new Exception('Invalid template ID');
            }
            
            // Get DocuSeal settings
            $settings = getDocuSealSettings($pdo);
            
            if (empty($settings['docuseal_enabled']) || $settings['docuseal_enabled'] !== '1') {
                throw new Exception('DocuSeal is not enabled');
            }
            
            // Delete template from DocuSeal
            $result = deleteDocuSealTemplate($settings, $templateId);
            
            if (!$result['success']) {
                throw new Exception('Failed to delete template: ' . $result['message']);
            }
            
            // Audit log
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, table_name, record_id, new_values, ip_address, created_at)
                VALUES (?, 'DELETE', 'docuseal_templates', ?, ?, ?, NOW())
            ");
            $auditStmt->execute([
                $user_id,
                $templateId,
                json_encode(['action' => 'deleted']),
                getClientIP()
            ]);
            Auditor::log($pdo, $user_id, 'delete', 'docuseal_templates', $templateId, ['action' => 'Deleted DocuSeal template']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);
            break;
            
        /**
         * DocuSeal Template Management - Clone template
         */
        case 'docuseal_clone_template':
            $templateId = intval($_POST['template_id'] ?? 0);
            $newName = trim($_POST['new_name'] ?? '');
            
            if ($templateId <= 0) {
                throw new Exception('Invalid template ID');
            }
            
            if (empty($newName)) {
                throw new Exception('New template name is required');
            }
            
            // Get DocuSeal settings
            $settings = getDocuSealSettings($pdo);
            
            if (empty($settings['docuseal_enabled']) || $settings['docuseal_enabled'] !== '1') {
                throw new Exception('DocuSeal is not enabled');
            }
            
            // Clone template in DocuSeal
            $result = cloneDocuSealTemplate($settings, $templateId, $newName);
            
            if (!$result['success']) {
                throw new Exception('Failed to clone template: ' . $result['message']);
            }
            
            // Audit log
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, table_name, record_id, new_values, ip_address, created_at)
                VALUES (?, 'CREATE', 'docuseal_templates', ?, ?, ?, NOW())
            ");
            $newTemplateId = $result['template']['id'] ?? 0;
            $auditStmt->execute([
                $user_id,
                $newTemplateId,
                json_encode(['cloned_from' => $templateId, 'new_name' => $newName]),
                getClientIP()
            ]);
            Auditor::log($pdo, $user_id, 'create', 'docuseal_templates', $newTemplateId, ['action' => 'Cloned DocuSeal template', 'cloned_from' => $templateId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template cloned successfully',
                'template' => $result['template']
            ]);
            break;
            
        /**
         * DocuSeal Template Management - Get template details
         */
        case 'docuseal_get_template':
            $templateId = intval($_POST['template_id'] ?? $_GET['template_id'] ?? 0);
            
            if ($templateId <= 0) {
                throw new Exception('Invalid template ID');
            }
            
            // Get DocuSeal settings
            $settings = getDocuSealSettings($pdo);
            
            if (empty($settings['docuseal_enabled']) || $settings['docuseal_enabled'] !== '1') {
                throw new Exception('DocuSeal is not enabled');
            }
            
            // Get template details from DocuSeal
            $result = getDocuSealTemplateDetails($settings, $templateId);
            
            if (!$result['success']) {
                throw new Exception('Failed to get template: ' . $result['message']);
            }
            
            echo json_encode([
                'success' => true,
                'template' => $result['template']
            ]);
            break;
            
        /**
         * DocuSeal Template Management - List all templates
         */
        case 'docuseal_list_templates':
            // Get DocuSeal settings
            $settings = getDocuSealSettings($pdo);
            
            if (empty($settings['docuseal_enabled']) || $settings['docuseal_enabled'] !== '1') {
                throw new Exception('DocuSeal is not enabled');
            }
            
            // List templates from DocuSeal
            $templates = listDocuSealTemplates($pdo, $settings);
            
            echo json_encode([
                'success' => true,
                'templates' => $templates,
                'count' => count($templates)
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    ErrorLogger::error('Employee contracts error: ' . $e->getMessage());
    if ($is_json) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: dashboard.php?page=employee_contracts');
        exit;
    }
}
