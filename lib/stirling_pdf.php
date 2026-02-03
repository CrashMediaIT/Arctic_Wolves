<?php
/**
 * Stirling PDF API Client
 * 
 * Provides functions for connecting to Stirling PDF API for document
 * generation and e-signature workflows.
 * 
 * Features:
 * - PDF generation from templates
 * - E-signature request creation
 * - Signed document retrieval
 * - Status tracking
 */

require_once __DIR__ . '/../db_config.php';

/**
 * Get Stirling PDF settings from database
 */
function getStirlingPdfSettings($pdo) {
    $keys = [
        'stirling_pdf_url',
        'stirling_pdf_api_key',
        'stirling_pdf_enabled',
        'stirling_pdf_webhook_url',
        'stirling_pdf_default_template'
    ];
    
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    return $settings;
}

/**
 * Test connection to Stirling PDF API
 * 
 * @param array $settings API settings
 * @return array Result with success status and message
 */
function testStirlingPdfConnection($settings) {
    if (empty($settings['stirling_pdf_url'])) {
        return ['success' => false, 'message' => 'Stirling PDF URL is not configured'];
    }
    
    $url = rtrim($settings['stirling_pdf_url'], '/') . '/api/v1/info/status';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if (!empty($settings['stirling_pdf_api_key'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $settings['stirling_pdf_api_key']
        ]);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'Connection failed: ' . $error];
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'message' => 'Connection successful', 'status_code' => $httpCode];
    }
    
    return ['success' => false, 'message' => 'API returned status code: ' . $httpCode];
}

/**
 * Generate a contract PDF by merging template with data
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Stirling PDF settings
 * @param string $templatePath Path to PDF template
 * @param array $formData Data to merge into the template
 * @return array Result with success status and PDF content
 */
function generateContractPdf($pdo, $settings, $templatePath, $formData) {
    if (empty($settings['stirling_pdf_url'])) {
        return ['success' => false, 'message' => 'Stirling PDF URL is not configured'];
    }
    
    $url = rtrim($settings['stirling_pdf_url'], '/') . '/api/v1/misc/fill-form';
    
    // Read template file
    if (!file_exists($templatePath)) {
        return ['success' => false, 'message' => 'Template file not found: ' . $templatePath];
    }
    
    $templateContent = file_get_contents($templatePath);
    if ($templateContent === false) {
        return ['success' => false, 'message' => 'Failed to read template file'];
    }
    
    // Prepare form data as JSON
    $formDataJson = json_encode($formData);
    
    // Create multipart form data
    $boundary = uniqid();
    $delimiter = '-------------' . $boundary;
    
    $postData = '';
    
    // Add PDF file
    $postData .= "--" . $delimiter . "\r\n";
    $postData .= 'Content-Disposition: form-data; name="file"; filename="template.pdf"' . "\r\n";
    $postData .= "Content-Type: application/pdf\r\n\r\n";
    $postData .= $templateContent . "\r\n";
    
    // Add form data
    $postData .= "--" . $delimiter . "\r\n";
    $postData .= 'Content-Disposition: form-data; name="formData"' . "\r\n";
    $postData .= "Content-Type: application/json\r\n\r\n";
    $postData .= $formDataJson . "\r\n";
    
    $postData .= "--" . $delimiter . "--\r\n";
    
    $headers = [
        'Content-Type: multipart/form-data; boundary=' . $delimiter,
        'Content-Length: ' . strlen($postData)
    ];
    
    if (!empty($settings['stirling_pdf_api_key'])) {
        $headers[] = 'X-API-Key: ' . $settings['stirling_pdf_api_key'];
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'API request failed: ' . $error];
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'pdf_content' => $response,
            'message' => 'Contract PDF generated successfully'
        ];
    }
    
    return ['success' => false, 'message' => 'API returned status code: ' . $httpCode];
}

/**
 * Add signature field to a PDF document
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Stirling PDF settings
 * @param string $pdfContent PDF content to add signature to
 * @param array $signatureConfig Signature field configuration
 * @return array Result with success status and modified PDF
 */
function addSignatureField($pdo, $settings, $pdfContent, $signatureConfig) {
    if (empty($settings['stirling_pdf_url'])) {
        return ['success' => false, 'message' => 'Stirling PDF URL is not configured'];
    }
    
    $url = rtrim($settings['stirling_pdf_url'], '/') . '/api/v1/security/add-signature';
    
    // Create multipart form data
    $boundary = uniqid();
    $delimiter = '-------------' . $boundary;
    
    $postData = '';
    
    // Add PDF file
    $postData .= "--" . $delimiter . "\r\n";
    $postData .= 'Content-Disposition: form-data; name="file"; filename="contract.pdf"' . "\r\n";
    $postData .= "Content-Type: application/pdf\r\n\r\n";
    $postData .= $pdfContent . "\r\n";
    
    // Add signature configuration
    foreach ($signatureConfig as $key => $value) {
        $postData .= "--" . $delimiter . "\r\n";
        $postData .= 'Content-Disposition: form-data; name="' . $key . '"' . "\r\n\r\n";
        $postData .= $value . "\r\n";
    }
    
    $postData .= "--" . $delimiter . "--\r\n";
    
    $headers = [
        'Content-Type: multipart/form-data; boundary=' . $delimiter,
        'Content-Length: ' . strlen($postData)
    ];
    
    if (!empty($settings['stirling_pdf_api_key'])) {
        $headers[] = 'X-API-Key: ' . $settings['stirling_pdf_api_key'];
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'API request failed: ' . $error];
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'pdf_content' => $response,
            'message' => 'Signature field added successfully'
        ];
    }
    
    return ['success' => false, 'message' => 'API returned status code: ' . $httpCode];
}

/**
 * Create an e-signature request for a contract
 * This generates a unique signing URL for the recipient
 * 
 * @param PDO $pdo Database connection
 * @param int $contractId Contract ID from database
 * @param string $recipientEmail Recipient email address
 * @param string $recipientName Recipient name
 * @param string $pdfContent PDF content to sign
 * @return array Result with success status and signing URL
 */
function createEsignatureRequest($pdo, $contractId, $recipientEmail, $recipientName, $pdfContent) {
    // Generate a unique signing token
    $signingToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    // Store the unsigned PDF temporarily
    $tempDir = sys_get_temp_dir() . '/arctic_wolves_contracts';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    $tempFile = $tempDir . '/' . $signingToken . '.pdf';
    if (file_put_contents($tempFile, $pdfContent) === false) {
        return ['success' => false, 'message' => 'Failed to store contract for signing'];
    }
    
    // Generate signing URL (this would be your application's signing page)
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
               '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $signingUrl = $baseUrl . '/sign_contract.php?token=' . $signingToken;
    
    // Update contract record with signing token
    try {
        $stmt = $pdo->prepare("
            UPDATE employee_contracts 
            SET signing_token = ?, 
                signing_token_expires = ?,
                temp_file_path = ?,
                status = 'pending_signature',
                sent_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$signingToken, $expiresAt, $tempFile, $contractId]);
        
        return [
            'success' => true,
            'signing_url' => $signingUrl,
            'signing_token' => $signingToken,
            'expires_at' => $expiresAt,
            'message' => 'E-signature request created successfully'
        ];
        
    } catch (PDOException $e) {
        error_log("Failed to create e-signature request: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Process a signed contract
 * 
 * @param PDO $pdo Database connection
 * @param string $signingToken The signing token
 * @param string $signatureData Base64 encoded signature image
 * @return array Result with success status
 */
function processSignedContract($pdo, $signingToken, $signatureData) {
    // Validate signing token
    $stmt = $pdo->prepare("
        SELECT ec.*, eo.first_name, eo.last_name, eo.email
        FROM employee_contracts ec
        LEFT JOIN employee_onboarding eo ON ec.onboarding_id = eo.id
        WHERE ec.signing_token = ? 
        AND ec.signing_token_expires > NOW()
        AND ec.status = 'pending_signature'
    ");
    $stmt->execute([$signingToken]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contract) {
        return ['success' => false, 'message' => 'Invalid or expired signing token'];
    }
    
    // Read the unsigned PDF
    if (!file_exists($contract['temp_file_path'])) {
        return ['success' => false, 'message' => 'Contract file not found'];
    }
    
    $pdfContent = file_get_contents($contract['temp_file_path']);
    if ($pdfContent === false) {
        return ['success' => false, 'message' => 'Failed to read contract file'];
    }
    
    // Get Stirling PDF settings
    $settings = getStirlingPdfSettings($pdo);
    
    // Apply signature to PDF using Stirling PDF
    $signedResult = applySignatureToPdf($pdo, $settings, $pdfContent, $signatureData);
    
    if (!$signedResult['success']) {
        return $signedResult;
    }
    
    // Upload signed contract to Nextcloud
    require_once __DIR__ . '/../cloud_config.php';
    $ncSettings = getNextcloudSettings($pdo);
    
    $employeeName = $contract['first_name'] . '_' . $contract['last_name'];
    $dateSigned = date('Y-m-d');
    $year = date('Y');
    $month = date('m');
    
    $uploadResult = uploadSignedContract(
        $pdo, 
        $ncSettings, 
        $signedResult['pdf_content'],
        $employeeName,
        $dateSigned,
        $year,
        $month
    );
    
    if (!$uploadResult['success']) {
        error_log("Failed to upload signed contract to Nextcloud: " . $uploadResult['message']);
        // Continue anyway - we'll store locally
    }
    
    // Update contract status
    try {
        $stmt = $pdo->prepare("
            UPDATE employee_contracts 
            SET status = 'signed',
                signed_at = NOW(),
                signed_date = CURDATE(),
                nextcloud_path = ?,
                signing_token = NULL,
                signing_token_expires = NULL
            WHERE id = ?
        ");
        $stmt->execute([
            $uploadResult['remote_path'] ?? null,
            $contract['id']
        ]);
        
        // Clean up temp file
        if (file_exists($contract['temp_file_path'])) {
            unlink($contract['temp_file_path']);
        }
        
        return [
            'success' => true,
            'message' => 'Contract signed successfully',
            'nextcloud_path' => $uploadResult['remote_path'] ?? null
        ];
        
    } catch (PDOException $e) {
        error_log("Failed to update signed contract: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Apply signature image to PDF using Stirling PDF
 * 
 * @param PDO $pdo Database connection
 * @param array $settings Stirling PDF settings
 * @param string $pdfContent PDF content
 * @param string $signatureData Base64 encoded signature image
 * @return array Result with signed PDF content
 */
function applySignatureToPdf($pdo, $settings, $pdfContent, $signatureData) {
    if (empty($settings['stirling_pdf_url'])) {
        return ['success' => false, 'message' => 'Stirling PDF URL is not configured'];
    }
    
    $url = rtrim($settings['stirling_pdf_url'], '/') . '/api/v1/misc/add-image';
    
    // Decode signature data (remove data URL prefix if present)
    if (strpos($signatureData, 'data:image') === 0) {
        $signatureData = preg_replace('/^data:image\/\w+;base64,/', '', $signatureData);
    }
    $signatureImage = base64_decode($signatureData);
    
    if ($signatureImage === false) {
        return ['success' => false, 'message' => 'Invalid signature data'];
    }
    
    // Create multipart form data
    $boundary = uniqid();
    $delimiter = '-------------' . $boundary;
    
    $postData = '';
    
    // Add PDF file
    $postData .= "--" . $delimiter . "\r\n";
    $postData .= 'Content-Disposition: form-data; name="file"; filename="contract.pdf"' . "\r\n";
    $postData .= "Content-Type: application/pdf\r\n\r\n";
    $postData .= $pdfContent . "\r\n";
    
    // Add signature image
    $postData .= "--" . $delimiter . "\r\n";
    $postData .= 'Content-Disposition: form-data; name="image"; filename="signature.png"' . "\r\n";
    $postData .= "Content-Type: image/png\r\n\r\n";
    $postData .= $signatureImage . "\r\n";
    
    // Add position parameters (signature at bottom of last page)
    $postData .= "--" . $delimiter . "\r\n";
    $postData .= 'Content-Disposition: form-data; name="x"' . "\r\n\r\n";
    $postData .= "100\r\n";
    
    $postData .= "--" . $delimiter . "\r\n";
    $postData .= 'Content-Disposition: form-data; name="y"' . "\r\n\r\n";
    $postData .= "100\r\n";
    
    $postData .= "--" . $delimiter . "\r\n";
    $postData .= 'Content-Disposition: form-data; name="pageNumber"' . "\r\n\r\n";
    $postData .= "-1\r\n"; // Last page
    
    $postData .= "--" . $delimiter . "--\r\n";
    
    $headers = [
        'Content-Type: multipart/form-data; boundary=' . $delimiter,
        'Content-Length: ' . strlen($postData)
    ];
    
    if (!empty($settings['stirling_pdf_api_key'])) {
        $headers[] = 'X-API-Key: ' . $settings['stirling_pdf_api_key'];
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'API request failed: ' . $error];
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'pdf_content' => $response,
            'message' => 'Signature applied successfully'
        ];
    }
    
    return ['success' => false, 'message' => 'API returned status code: ' . $httpCode];
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
        
        // Get base HR directory
        $hrDir = $settings['nextcloud_hr_dir'] ?? '/Arctic_Wolves/HR';
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
                   eo.first_name, eo.last_name, eo.email,
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
 * List all contract templates
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
 * Send e-signature request email
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
