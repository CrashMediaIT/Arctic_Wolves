<?php
/**
 * DocuSeal API Client
 * 
 * Provides functions for connecting to DocuSeal API for document
 * e-signature workflows.
 * 
 * DocuSeal is an open-source document signing solution.
 * API Documentation: https://www.docuseal.co/docs/api
 * 
 * Features:
 * - Template management
 * - Submission (signature request) creation
 * - Signed document retrieval
 * - Status tracking via webhooks
 */

require_once __DIR__ . '/../db_config.php';

/**
 * Get DocuSeal settings from database
 */
function getDocuSealSettings($pdo) {
    $keys = [
        'docuseal_url',
        'docuseal_api_key',
        'docuseal_enabled',
        'docuseal_webhook_secret',
        'docuseal_verify_ssl'
    ];
    
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Default to SSL verification enabled for security
    if (!isset($settings['docuseal_verify_ssl'])) {
        $settings['docuseal_verify_ssl'] = '1';
    }
    
    return $settings;
}

/**
 * Check if SSL verification should be enabled
 * @param array $settings Settings array
 * @return bool True if SSL should be verified
 */
function shouldVerifyDocuSealSsl($settings) {
    return ($settings['docuseal_verify_ssl'] ?? '1') === '1';
}

/**
 * Make an API request to DocuSeal
 * 
 * @param array $settings DocuSeal settings
 * @param string $endpoint API endpoint (e.g., '/templates', '/submissions')
 * @param string $method HTTP method (GET, POST, PUT, DELETE)
 * @param array|null $data Request body data
 * @return array Response with success status
 */
function docuSealApiRequest($settings, $endpoint, $method = 'GET', $data = null) {
    if (empty($settings['docuseal_url'])) {
        return ['success' => false, 'message' => 'DocuSeal URL is not configured'];
    }
    
    if (empty($settings['docuseal_api_key'])) {
        return ['success' => false, 'message' => 'DocuSeal API key is not configured'];
    }
    
    $url = rtrim($settings['docuseal_url'], '/') . '/api' . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, shouldVerifyDocuSealSsl($settings));
    
    $headers = [
        'X-Auth-Token: ' . $settings['docuseal_api_key'],
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    switch (strtoupper($method)) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'API request failed: ' . $error];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'data' => $responseData,
            'status_code' => $httpCode
        ];
    }
    
    $errorMessage = isset($responseData['error']) ? $responseData['error'] : 'API returned status code: ' . $httpCode;
    return ['success' => false, 'message' => $errorMessage, 'status_code' => $httpCode];
}

/**
 * Test connection to DocuSeal API
 * 
 * @param array $settings API settings
 * @return array Result with success status and message
 */
function testDocuSealConnection($settings) {
    if (empty($settings['docuseal_url'])) {
        return ['success' => false, 'message' => 'DocuSeal URL is not configured'];
    }
    
    // Try to list templates to verify connection
    $result = docuSealApiRequest($settings, '/templates', 'GET');
    
    if ($result['success']) {
        $templateCount = is_array($result['data']) ? count($result['data']) : 0;
        return [
            'success' => true, 
            'message' => 'Connection successful! Found ' . $templateCount . ' template(s).',
            'template_count' => $templateCount
        ];
    }
    
    return $result;
}

/**
 * List all templates from DocuSeal
 * 
 * @param PDO $pdo Database connection
 * @param array $settings DocuSeal settings
 * @return array List of templates with 'id' and 'name' keys
 */
function listDocuSealTemplates($pdo, $settings) {
    $result = docuSealApiRequest($settings, '/templates', 'GET');
    
    if ($result['success']) {
        $data = $result['data'] ?? [];
        
        // Handle paginated response format where templates might be in 'data' key
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }
        
        // Filter to only include valid templates with 'id' and 'name' keys
        // This prevents errors when the API returns unexpected data structures
        $validTemplates = [];
        foreach ($data as $template) {
            if (is_array($template) && isset($template['id']) && isset($template['name'])) {
                $validTemplates[] = $template;
            }
        }
        
        return $validTemplates;
    }
    
    error_log("Failed to list DocuSeal templates: " . $result['message']);
    return [];
}

/**
 * Get a specific template from DocuSeal
 * 
 * @param array $settings DocuSeal settings
 * @param int $templateId Template ID
 * @return array|null Template data or null if not found
 */
function getDocuSealTemplate($settings, $templateId) {
    $result = docuSealApiRequest($settings, '/templates/' . intval($templateId), 'GET');
    
    if ($result['success']) {
        return $result['data'];
    }
    
    return null;
}

/**
 * Create a submission (signature request) in DocuSeal
 * 
 * @param PDO $pdo Database connection
 * @param array $settings DocuSeal settings
 * @param int $templateId DocuSeal template ID
 * @param array $submitters Array of submitter information
 * @param array $fields Optional pre-filled field values
 * @return array Result with submission data
 */
function createDocuSealSubmission($pdo, $settings, $templateId, $submitters, $fields = []) {
    $submissionData = [
        'template_id' => $templateId,
        'submitters' => $submitters
    ];
    
    // Add pre-filled fields if provided
    if (!empty($fields)) {
        $submissionData['fields'] = $fields;
    }
    
    // Send notification email
    $submissionData['send_email'] = true;
    
    $result = docuSealApiRequest($settings, '/submissions', 'POST', $submissionData);
    
    if ($result['success']) {
        return [
            'success' => true,
            'submission' => $result['data'],
            'message' => 'Submission created successfully'
        ];
    }
    
    return $result;
}

/**
 * Get submission status from DocuSeal
 * 
 * @param array $settings DocuSeal settings
 * @param int $submissionId Submission ID
 * @return array Submission data with status
 */
function getDocuSealSubmission($settings, $submissionId) {
    $result = docuSealApiRequest($settings, '/submissions/' . intval($submissionId), 'GET');
    
    if ($result['success']) {
        return [
            'success' => true,
            'submission' => $result['data']
        ];
    }
    
    return $result;
}

/**
 * Get submitters for a submission
 * 
 * @param array $settings DocuSeal settings
 * @param int $submissionId Submission ID
 * @return array Submitter data
 */
function getDocuSealSubmitters($settings, $submissionId) {
    $result = docuSealApiRequest($settings, '/submitters?submission_id=' . intval($submissionId), 'GET');
    
    if ($result['success']) {
        return $result['data'] ?? [];
    }
    
    return [];
}

/**
 * Download signed document from DocuSeal
 * 
 * @param array $settings DocuSeal settings
 * @param int $submissionId Submission ID
 * @return array Result with document content
 */
function downloadDocuSealDocument($settings, $submissionId) {
    // Get submission to find the document URL
    $submission = getDocuSealSubmission($settings, $submissionId);
    
    if (!$submission['success']) {
        return $submission;
    }
    
    $documents = $submission['submission']['documents'] ?? [];
    
    if (empty($documents)) {
        return ['success' => false, 'message' => 'No documents found in submission'];
    }
    
    // Get the first (combined) document
    $documentUrl = $documents[0]['url'] ?? null;
    
    if (!$documentUrl) {
        return ['success' => false, 'message' => 'Document URL not found'];
    }
    
    // Download the document
    $ch = curl_init($documentUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, shouldVerifyDocuSealSsl($settings));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Auth-Token: ' . $settings['docuseal_api_key']
    ]);
    
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'Failed to download document: ' . $error];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'message' => 'Failed to download document. HTTP code: ' . $httpCode];
    }
    
    return [
        'success' => true,
        'content' => $content,
        'filename' => $documents[0]['filename'] ?? 'signed_contract.pdf'
    ];
}

/**
 * Create an e-signature request for a contract
 * Creates a DocuSeal submission and stores the reference
 * 
 * @param PDO $pdo Database connection
 * @param int $contractId Contract ID from database
 * @param int $docusealTemplateId DocuSeal template ID
 * @param string $recipientEmail Recipient email address
 * @param string $recipientName Recipient name
 * @param array $prefillData Pre-filled form data
 * @return array Result with success status and signing URL
 */
function createEsignatureRequest($pdo, $contractId, $docusealTemplateId, $recipientEmail, $recipientName, $prefillData = []) {
    $settings = getDocuSealSettings($pdo);
    
    if (empty($settings['docuseal_enabled']) || $settings['docuseal_enabled'] !== '1') {
        return ['success' => false, 'message' => 'DocuSeal is not enabled'];
    }
    
    // Create submitter array for DocuSeal
    $submitters = [
        [
            'email' => $recipientEmail,
            'name' => $recipientName,
            'role' => 'Employee' // Default role - should match template
        ]
    ];
    
    // Convert prefill data to DocuSeal fields format
    $fields = [];
    foreach ($prefillData as $key => $value) {
        if (!empty($value)) {
            $fields[] = [
                'name' => $key,
                'default_value' => $value
            ];
        }
    }
    
    // Create submission in DocuSeal
    $result = createDocuSealSubmission($pdo, $settings, $docusealTemplateId, $submitters, $fields);
    
    if (!$result['success']) {
        return $result;
    }
    
    $submission = $result['submission'];
    
    // DocuSeal API returns different response formats:
    // - When creating submissions: returns array of submitter objects with submission_id
    // - When getting submissions: returns object with id field
    // Handle both formats for compatibility
    $submissionId = null;
    $signingUrl = null;
    
    if (is_array($submission) && isset($submission[0])) {
        // Response is array of submitters (from POST /submissions)
        $submissionId = $submission[0]['submission_id'] ?? null;
        if (isset($submission[0]['slug'])) {
            $signingUrl = rtrim($settings['docuseal_url'], '/') . '/s/' . $submission[0]['slug'];
        }
    } elseif (isset($submission['id'])) {
        // Response is submission object (from GET /submissions/:id)
        $submissionId = $submission['id'];
    }
    
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    // Update contract record with DocuSeal submission info
    try {
        $stmt = $pdo->prepare("
            UPDATE employee_contracts 
            SET docuseal_submission_id = ?, 
                signing_url = ?,
                signing_token_expires = ?,
                status = 'pending_signature',
                sent_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$submissionId, $signingUrl, $expiresAt, $contractId]);
        
        return [
            'success' => true,
            'submission_id' => $submissionId,
            'signing_url' => $signingUrl,
            'expires_at' => $expiresAt,
            'message' => 'E-signature request created successfully'
        ];
        
    } catch (PDOException $e) {
        error_log("Failed to update contract with DocuSeal submission: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Process webhook callback from DocuSeal when a document is signed
 * 
 * @param PDO $pdo Database connection
 * @param array $webhookData Webhook payload
 * @return array Result with success status
 */
function processDocuSealWebhook($pdo, $webhookData) {
    $eventType = $webhookData['event_type'] ?? '';
    $submissionId = $webhookData['data']['submission_id'] ?? null;
    
    if ($eventType !== 'submission.completed' || !$submissionId) {
        return ['success' => false, 'message' => 'Invalid webhook event'];
    }
    
    // Find the contract by DocuSeal submission ID
    $stmt = $pdo->prepare("SELECT * FROM employee_contracts WHERE docuseal_submission_id = ?");
    $stmt->execute([$submissionId]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contract) {
        return ['success' => false, 'message' => 'Contract not found for submission'];
    }
    
    // Get settings and download the signed document
    $settings = getDocuSealSettings($pdo);
    $downloadResult = downloadDocuSealDocument($settings, $submissionId);
    
    if (!$downloadResult['success']) {
        error_log("Failed to download signed document: " . $downloadResult['message']);
    }
    
    // Upload to Nextcloud if download was successful
    $nextcloudPath = null;
    if ($downloadResult['success']) {
        require_once __DIR__ . '/../cloud_config.php';
        $ncSettings = getNextcloudSettings($pdo);
        
        $employeeName = str_replace(' ', '_', $contract['employee_name'] ?? 'Unknown');
        $dateSigned = date('Y-m-d');
        $year = date('Y');
        $month = date('m');
        
        $uploadResult = uploadSignedContract(
            $pdo, 
            $ncSettings, 
            $downloadResult['content'],
            $employeeName,
            $dateSigned,
            $year,
            $month
        );
        
        if ($uploadResult['success']) {
            $nextcloudPath = $uploadResult['remote_path'];
        }
    }
    
    // Update contract status
    try {
        $stmt = $pdo->prepare("
            UPDATE employee_contracts 
            SET status = 'signed',
                signed_at = NOW(),
                signed_date = CURDATE(),
                nextcloud_path = ?
            WHERE id = ?
        ");
        $stmt->execute([$nextcloudPath, $contract['id']]);
        
        // Send confirmation email
        require_once __DIR__ . '/../mailer.php';
        sendContractSignedEmail(
            $contract['employee_email'],
            $contract['employee_name'],
            $contract['contract_title']
        );
        
        return [
            'success' => true,
            'message' => 'Contract marked as signed',
            'nextcloud_path' => $nextcloudPath
        ];
        
    } catch (PDOException $e) {
        error_log("Failed to update signed contract: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Upload signed contract to Nextcloud
 * Path format: /HR/Employee Contract/YYYY/MM/EmployeeName_DateSigned.pdf
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Nextcloud settings
 * @param string $pdfContent Signed PDF content
 * @param string $employeeName Employee name (will be sanitized)
 * @param string $dateSigned Date signed (Y-m-d format)
 * @param string $year Year for folder structure
 * @param string $month Month for folder structure
 * @return array Result with success status and remote path
 */
function uploadSignedContract($pdo, $settings, $pdfContent, $employeeName, $dateSigned, $year, $month) {
    try {
        $connection = connectNextcloud($settings);
        
        // Get base HR directory from settings, default to a generic path
        // The nextcloud_hr_dir setting should be configured in System Tools > Nextcloud
        $hrDir = $settings['nextcloud_hr_dir'] ?? '/HR';
        $contractsDir = $hrDir . '/Employee Contract';
        
        // Sanitize employee name for filename
        $safeEmployeeName = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $employeeName);
        $safeEmployeeName = str_replace(' ', '_', trim($safeEmployeeName));
        
        // Create folder structure: /HR/Employee Contract/YYYY/MM
        $folderPath = ensureNextcloudPath($connection, $contractsDir, [$year, $month]);
        
        // Build filename: EmployeeName_DateSigned.pdf
        $filename = $safeEmployeeName . '_' . $dateSigned . '.pdf';
        $remotePath = $folderPath . '/' . $filename;
        
        // Upload the signed contract
        uploadToNextcloud($connection, $remotePath, $pdfContent, 'application/pdf');
        
        return [
            'success' => true,
            'folder_path' => $folderPath,
            'filename' => $filename,
            'remote_path' => $remotePath
        ];
        
    } catch (Exception $e) {
        error_log("Error uploading signed contract: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get contract status by ID
 * 
 * @param PDO $pdo Database connection
 * @param int $contractId Contract ID
 * @return array|false Contract data or false if not found
 */
function getContractStatus($pdo, $contractId) {
    try {
        $stmt = $pdo->prepare("
            SELECT ec.*, 
                   eo.first_name, eo.last_name, eo.email as onboarding_email,
                   ct.name as template_name
            FROM employee_contracts ec
            LEFT JOIN employee_onboarding eo ON ec.onboarding_id = eo.id
            LEFT JOIN contract_templates ct ON ec.template_id = ct.id
            WHERE ec.id = ?
        ");
        $stmt->execute([$contractId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get contract status: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and update contract status from DocuSeal
 * 
 * @param PDO $pdo Database connection
 * @param int $contractId Contract ID
 * @return array Result with current status
 */
function refreshContractStatus($pdo, $contractId) {
    $contract = getContractStatus($pdo, $contractId);
    
    if (!$contract || empty($contract['docuseal_submission_id'])) {
        return ['success' => false, 'message' => 'Contract or submission not found'];
    }
    
    $settings = getDocuSealSettings($pdo);
    $submissionResult = getDocuSealSubmission($settings, $contract['docuseal_submission_id']);
    
    if (!$submissionResult['success']) {
        return $submissionResult;
    }
    
    $submission = $submissionResult['submission'];
    $status = $submission['status'] ?? 'pending';
    
    // Map DocuSeal status to our contract status
    $contractStatus = $contract['status'];
    if ($status === 'completed' && $contract['status'] !== 'signed') {
        // Document was signed - trigger the webhook processing
        $webhookData = [
            'event_type' => 'submission.completed',
            'data' => ['submission_id' => $contract['docuseal_submission_id']]
        ];
        processDocuSealWebhook($pdo, $webhookData);
        $contractStatus = 'signed';
    }
    
    return [
        'success' => true,
        'docuseal_status' => $status,
        'contract_status' => $contractStatus
    ];
}

/**
 * List all contract templates from database
 * 
 * @param PDO $pdo Database connection
 * @return array List of templates
 */
function listContractTemplates($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT * FROM contract_templates 
            WHERE is_active = 1 
            ORDER BY name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to list contract templates: " . $e->getMessage());
        return [];
    }
}

/**
 * Send e-signature request email (for cases where DocuSeal email is disabled)
 * 
 * @param string $toEmail Recipient email
 * @param string $recipientName Recipient name
 * @param string $signingUrl URL for signing the contract
 * @param string $contractTitle Contract title
 * @return bool Success status
 */
function sendEsignatureRequestEmail($toEmail, $recipientName, $signingUrl, $contractTitle) {
    require_once __DIR__ . '/../mailer.php';
    
    return sendEmail($toEmail, 'esignature_request', [
        'name' => $recipientName,
        'signing_url' => $signingUrl,
        'contract_title' => $contractTitle
    ]);
}

/**
 * Send contract signed confirmation email
 * 
 * @param string $toEmail Recipient email
 * @param string $recipientName Recipient name
 * @param string $contractTitle Contract title
 * @return bool Success status
 */
function sendContractSignedEmail($toEmail, $recipientName, $contractTitle) {
    require_once __DIR__ . '/../mailer.php';
    
    return sendEmail($toEmail, 'contract_signed', [
        'name' => $recipientName,
        'contract_title' => $contractTitle
    ]);
}
