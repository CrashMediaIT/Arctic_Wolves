<?php
/**
 * Process Onboarding Actions
 * Handles staff onboarding with user creation, payroll setup, and Nextcloud integration
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once 'cloud_config.php';
require_once __DIR__ . '/lib/encryption.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Set security headers
setSecurityHeaders();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Validate CSRF token
checkCsrfToken();

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

/**
 * Generate a secure random password
 */
function generateTemporaryPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// Banking encryption now uses encryptPassword() from security.php

/**
 * Upload onboarding documents to Nextcloud
 */
function uploadOnboardingDocuments($pdo, $settings, $staffName, $year, $files) {
    $uploaded_paths = [];
    
    try {
        // Sanitize staff name
        $safeStaffName = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $staffName);
        $safeStaffName = str_replace(' ', '_', trim($safeStaffName));
        
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
                    $subfolder = 'Onboarding/' . $year . '/' . $safeStaffName;
                    
                    // Upload to RustFS via persistUploadedFile
                    $persist = persistUploadedFile($pdo, $tmp_path, $subfolder, $safe_filename);
                    $db_path = $persist['rustfs_url'] ?? null;
                    
                    $uploaded_paths[] = [
                        'original_name' => $original_name,
                        'remote_path' => $db_path,
                        'file_size' => filesize($tmp_path),
                        'content_type' => $content_type
                    ];
                    
                    // Also upload to Paperless-NGX with HR tag
                    $title = 'HR_' . $safeStaffName . '_' . $year . '_' . $safe_filename;
                    uploadToPaperless($pdo, $tmp_path, 'HR', $title);
                }
            }
        }
        
        return [
            'success' => true,
            'folder_path' => 'Onboarding/' . $year . '/' . $safeStaffName,
            'uploaded_files' => $uploaded_paths
        ];
        
    } catch (Exception $e) {
        ErrorLogger::error("Error uploading onboarding documents: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'uploaded_files' => $uploaded_paths
        ];
    }
}

/**
 * Export onboarding form data to Nextcloud
 */
function exportOnboardingData($pdo, $settings, $onboardingData, $staffName, $year) {
    try {
        // Sanitize staff name
        $safeStaffName = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $staffName);
        $safeStaffName = str_replace(' ', '_', trim($safeStaffName));
        
        // Create onboarding summary document
        $summary_content = "EMPLOYEE ONBOARDING RECORD\n";
        $summary_content .= "==========================\n\n";
        $summary_content .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        $summary_content .= "EMPLOYEE INFORMATION\n";
        $summary_content .= "--------------------\n";
        $summary_content .= "Name: " . ($onboardingData['first_name'] ?? '') . " " . ($onboardingData['last_name'] ?? '') . "\n";
        $summary_content .= "Email: " . ($onboardingData['email'] ?? 'N/A') . "\n";
        $summary_content .= "Phone: " . ($onboardingData['phone'] ?? 'N/A') . "\n";
        $summary_content .= "Role: " . ucfirst(str_replace('_', ' ', $onboardingData['role'] ?? 'N/A')) . "\n";
        $summary_content .= "Job Title: " . ($onboardingData['job_title'] ?? 'N/A') . "\n";
        $summary_content .= "Employment Type: " . ucfirst(str_replace('_', ' ', $onboardingData['employee_type'] ?? 'N/A')) . "\n";
        $summary_content .= "Start Date: " . ($onboardingData['start_date'] ?? 'N/A') . "\n";
        $summary_content .= "Date of Birth: " . ($onboardingData['date_of_birth'] ?? 'N/A') . "\n\n";
        
        $summary_content .= "ADDRESS\n";
        $summary_content .= "-------\n";
        $address = ($onboardingData['street_address'] ?? '');
        if (!empty($onboardingData['unit_number'])) $address .= ', ' . $onboardingData['unit_number'];
        $address .= "\n" . ($onboardingData['city'] ?? '') . ', ' . ($onboardingData['province'] ?? '') . ' ' . ($onboardingData['postal_code'] ?? '');
        $summary_content .= $address . "\n\n";
        
        $summary_content .= "EMERGENCY CONTACT\n";
        $summary_content .= "-----------------\n";
        $summary_content .= "Name: " . ($onboardingData['emergency_contact_name'] ?? 'N/A') . "\n";
        $summary_content .= "Phone: " . ($onboardingData['emergency_contact_phone'] ?? 'N/A') . "\n";
        $summary_content .= "Relationship: " . ($onboardingData['emergency_contact_relationship'] ?? 'N/A') . "\n\n";
        
        if (!empty($onboardingData['equipment'])) {
            $summary_content .= "EQUIPMENT ASSIGNED\n";
            $summary_content .= "------------------\n";
            foreach ($onboardingData['equipment'] as $equip) {
                if (!empty($equip['name'])) {
                    $summary_content .= "- " . $equip['name'] . " (" . ucfirst($equip['type']) . ")";
                    if (!empty($equip['serial'])) $summary_content .= " - S/N: " . $equip['serial'];
                    $summary_content .= "\n";
                }
            }
            $summary_content .= "\n";
        }
        
        if (!empty($onboardingData['perks'])) {
            $summary_content .= "PERKS ASSIGNED\n";
            $summary_content .= "--------------\n";
            foreach ($onboardingData['perks'] as $perk) {
                if (!empty($perk['name'])) {
                    $summary_content .= "- " . $perk['name'] . " (Qty: " . ($perk['quantity'] ?? 1) . ")\n";
                }
            }
            $summary_content .= "\n";
        }
        
        $summary_content .= "NOTES\n";
        $summary_content .= "-----\n";
        $summary_content .= ($onboardingData['notes'] ?? 'No additional notes') . "\n\n";
        
        $summary_content .= "PROCESSED BY\n";
        $summary_content .= "------------\n";
        $summary_content .= "Admin ID: " . ($onboardingData['processed_by'] ?? 'N/A') . "\n";
        $summary_content .= "Processed At: " . date('Y-m-d H:i:s') . "\n";
        
        // Upload summary and JSON to RustFS
        $rustfs = getRustFSSettings($pdo);
        $folder_key = 'Images/Onboarding/' . $year . '/' . $safeStaffName;
        $summary_path = null;
        $json_path = null;
        
        $filename = 'Onboarding_Summary_' . $safeStaffName . '_' . date('Y-m-d') . '.txt';
        $json_filename = 'Onboarding_Data_' . $safeStaffName . '_' . date('Y-m-d') . '.json';
        $json_content = json_encode($onboardingData, JSON_PRETTY_PRINT);
        
        if (isRustFSConfigured($rustfs)) {
            $r1 = uploadContentToRustFS($rustfs, $summary_content, $folder_key . '/' . $filename, 'text/plain');
            if ($r1['success']) $summary_path = $r1['url'];
            
            $r2 = uploadContentToRustFS($rustfs, $json_content, $folder_key . '/' . $json_filename, 'application/json');
            if ($r2['success']) $json_path = $r2['url'];
        }
        
        return [
            'success' => true,
            'folder_path' => $folder_key,
            'summary_file' => $summary_path ?? $folder_key . '/' . $filename,
            'json_file' => $json_path ?? $folder_key . '/' . $json_filename
        ];
        
    } catch (Exception $e) {
        ErrorLogger::error("Error exporting onboarding data: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Handle Create Onboarding
if ($action === 'create') {
    try {
        // Collect form data
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role']);
        $jobTitle = trim($_POST['job_title'] ?? '');
        $employeeType = trim($_POST['employee_type']);
        $startDate = trim($_POST['start_date']);
        $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
        $sinLastFour = trim($_POST['sin_last_four'] ?? '');
        
        // Address
        $streetAddress = trim($_POST['street_address']);
        $unitNumber = trim($_POST['unit_number'] ?? '');
        $city = trim($_POST['city']);
        $province = trim($_POST['province']);
        $postalCode = strtoupper(trim($_POST['postal_code']));
        
        // Emergency contact
        $emergencyName = trim($_POST['emergency_contact_name'] ?? '');
        $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');
        $emergencyRelationship = trim($_POST['emergency_contact_relationship'] ?? '');
        
        // Options
        $createAccount = isset($_POST['create_account']) ? 1 : 0;
        $createExtension = isset($_POST['create_extension']) ? 1 : 0;
        $setupPayroll = isset($_POST['setup_payroll']) ? 1 : 0;
        
        // Payroll details
        $payType = trim($_POST['pay_type'] ?? 'hourly');
        $payRate = floatval($_POST['pay_rate'] ?? 0);
        $payFrequency = trim($_POST['pay_frequency'] ?? 'bi-weekly');
        $institutionNumber = trim($_POST['institution_number'] ?? '');
        $transitNumber = trim($_POST['transit_number'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');
        
        // Equipment and Perks
        $equipment = $_POST['equipment'] ?? [];
        $perks = $_POST['perks'] ?? [];
        
        $notes = trim($_POST['notes'] ?? '');
        
        // Contract creation options
        $createContract = isset($_POST['create_contract']) ? 1 : 0;
        $docusealTemplateId = intval($_POST['docuseal_template_id'] ?? 0);
        $contractTitle = trim($_POST['contract_title'] ?? 'Employment Contract');
        $contractSalary = trim($_POST['contract_salary'] ?? '');
        $contractPayFrequency = trim($_POST['contract_pay_frequency'] ?? '');
        
        // Validation
        if (empty($firstName) || empty($lastName) || empty($email) || empty($role) || empty($startDate)) {
            throw new Exception('Required fields are missing');
        }
        
        // Check email doesn't already exist (if creating account)
        if ($createAccount) {
            $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $emailCheck->execute([$email]);
            if ($emailCheck->fetch()) {
                throw new Exception('An account with this email already exists');
            }
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            $newUserId = null;
            $tempPassword = null;
            
            // Create user account if requested
            if ($createAccount) {
                $tempPassword = generateTemporaryPassword();
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

                // Encrypt PII before storing (email kept as-is for login lookups)
                $enc_firstName = FieldEncryption::encrypt($firstName);
                $enc_lastName = FieldEncryption::encrypt($lastName);
                $enc_phone = $phone ? FieldEncryption::encrypt($phone) : null;
                $enc_dob = $dateOfBirth ? FieldEncryption::encrypt($dateOfBirth) : null;
                
                $userStmt = $pdo->prepare("
                    INSERT INTO users (email, password, first_name, last_name, role, phone, birth_date, job_title,
                                       is_active, is_verified, force_pass_change, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, NOW())
                ");
                $userStmt->execute([
                    $email, $hashedPassword, $enc_firstName, $enc_lastName, $role, 
                    $enc_phone, $enc_dob, $jobTitle ?: null
                ]);
                $newUserId = $pdo->lastInsertId();
            }
            
            // Create onboarding record with encrypted PII
            $enc_onboard_first = FieldEncryption::encrypt($firstName);
            $enc_onboard_last = FieldEncryption::encrypt($lastName);
            $enc_onboard_phone = $phone ? FieldEncryption::encrypt($phone) : null;
            $enc_onboard_dob = $dateOfBirth ? FieldEncryption::encrypt($dateOfBirth) : null;
            $enc_onboard_street = $streetAddress ? FieldEncryption::encrypt($streetAddress) : null;
            $enc_onboard_city = $city ? FieldEncryption::encrypt($city) : null;
            $enc_onboard_emerg_name = $emergencyName ? FieldEncryption::encrypt($emergencyName) : null;
            $enc_onboard_emerg_phone = $emergencyPhone ? FieldEncryption::encrypt($emergencyPhone) : null;

            $onboardStmt = $pdo->prepare("
                INSERT INTO employee_onboarding 
                (user_id, first_name, last_name, email, phone, role, job_title, create_extension, start_date, employee_type,
                 onboarding_status, personal_info_collected, sin_collected, sin_last_four, date_of_birth,
                 street_address, unit_number, city, province, postal_code,
                 emergency_contact_name, emergency_contact_phone, emergency_contact_relationship,
                 notes, processed_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_progress', 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $onboardStmt->execute([
                $newUserId, $enc_onboard_first, $enc_onboard_last, $email, $enc_onboard_phone, $role, $jobTitle ?: null, $createExtension, $startDate, $employeeType,
                !empty($sinLastFour) ? 1 : 0, $sinLastFour ?: null, $enc_onboard_dob,
                $enc_onboard_street, $unitNumber, $enc_onboard_city, $province, $postalCode,
                $enc_onboard_emerg_name, $enc_onboard_emerg_phone, $emergencyRelationship,
                $notes, $user_id
            ]);
            $onboardingId = $pdo->lastInsertId();
            
            // Setup payroll if requested and user was created
            if ($setupPayroll && $newUserId && $payRate > 0) {
                $payrollStmt = $pdo->prepare("
                    INSERT INTO employee_payroll 
                    (user_id, employee_type, pay_rate, pay_frequency, start_date, tax_province, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $payrollStmt->execute([$newUserId, $payType, $payRate, $payFrequency, $startDate, $province]);
                
                // Add banking if provided
                if (!empty($institutionNumber) && !empty($transitNumber) && !empty($accountNumber)) {
                    $encryptedAccount = encryptPassword($accountNumber);
                    $bankStmt = $pdo->prepare("
                        INSERT INTO employee_banking 
                        (user_id, institution_number, transit_number, account_number_encrypted, is_primary, created_at)
                        VALUES (?, ?, ?, ?, 1, NOW())
                    ");
                    $bankStmt->execute([$newUserId, $institutionNumber, $transitNumber, $encryptedAccount]);
                    
                    // Update onboarding record
                    $pdo->prepare("UPDATE employee_onboarding SET banking_info_collected = 1, payroll_setup_completed = 1 WHERE id = ?")->execute([$onboardingId]);
                }
                
                // Add address record
                $addrStmt = $pdo->prepare("
                    INSERT INTO employee_addresses 
                    (user_id, address_type, street_address, unit_number, city, province, postal_code, is_primary, created_at)
                    VALUES (?, 'home', ?, ?, ?, ?, ?, 1, NOW())
                ");
                $addrStmt->execute([$newUserId, $streetAddress, $unitNumber, $city, $province, $postalCode]);
            }
            
            // Add equipment
            $equipmentAdded = false;
            foreach ($equipment as $equip) {
                if (!empty($equip['type']) && !empty($equip['name'])) {
                    $equipStmt = $pdo->prepare("
                        INSERT INTO onboarding_equipment 
                        (onboarding_id, equipment_type, equipment_name, serial_number, issued_date, assigned_by, created_at)
                        VALUES (?, ?, ?, ?, CURDATE(), ?, NOW())
                    ");
                    $equipStmt->execute([$onboardingId, $equip['type'], $equip['name'], $equip['serial'] ?? null, $user_id]);
                    $equipmentAdded = true;
                }
            }
            if ($equipmentAdded) {
                $pdo->prepare("UPDATE employee_onboarding SET equipment_assigned = 1 WHERE id = ?")->execute([$onboardingId]);
            }
            
            // Add perks
            $perksAdded = false;
            foreach ($perks as $perk) {
                if (!empty($perk['type']) && !empty($perk['name'])) {
                    $perkStmt = $pdo->prepare("
                        INSERT INTO onboarding_perks 
                        (onboarding_id, perk_type, perk_name, quantity, issued_date, assigned_by, created_at)
                        VALUES (?, ?, ?, ?, CURDATE(), ?, NOW())
                    ");
                    $perkStmt->execute([$onboardingId, $perk['type'], $perk['name'], $perk['quantity'] ?? 1, $user_id]);
                    $perksAdded = true;
                }
            }
            if ($perksAdded) {
                $pdo->prepare("UPDATE employee_onboarding SET perks_assigned = 1 WHERE id = ?")->execute([$onboardingId]);
            }
            
            // Phone Extension Request - send email to IT
            $extensionRequested = false;
            if ($createExtension && $newUserId) {
                try {
                    require_once 'mailer.php';
                    $staffDisplayName = $firstName . ' ' . $lastName;
                    sendEmail('it@arcticwolves.ca', 'extension_request', [
                        'staff_name' => $staffDisplayName,
                        'email' => $email,
                        'role' => $role,
                        'job_title' => $jobTitle ?: $role,
                        'start_date' => $startDate
                    ]);
                    $extensionRequested = true;
                    ErrorLogger::info("Extension request email sent to IT for $staffDisplayName");
                } catch (Exception $emailError) {
                    ErrorLogger::error("Extension request email error: " . $emailError->getMessage());
                    // Continue without email - not critical
                }
            }
            
            // Upload documents and data to RustFS
            $nextcloudFolder = null;
            $uploadedDocs = [];
            
            try {
                    $staffName = $firstName . ' ' . $lastName;
                    $year = date('Y');
                    
                    // Upload documents if provided
                    if (!empty($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
                        $uploadResult = uploadOnboardingDocuments($pdo, [], $staffName, $year, $_FILES['documents']);
                        
                        if ($uploadResult['success']) {
                            $nextcloudFolder = $uploadResult['folder_path'];
                            $uploadedDocs = $uploadResult['uploaded_files'];
                            
                            // Save document records
                            foreach ($uploadedDocs as $doc) {
                                $docStmt = $pdo->prepare("
                                    INSERT INTO onboarding_documents 
                                    (onboarding_id, document_type, document_name, nextcloud_path, file_size, status, uploaded_by, created_at)
                                    VALUES (?, 'other', ?, ?, ?, 'received', ?, NOW())
                                ");
                                $docStmt->execute([$onboardingId, $doc['original_name'], $doc['remote_path'], $doc['file_size'], $user_id]);
                            }
                        }
                    }
                    
                    // Export form data to RustFS
                    $onboardingData = [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'phone' => $phone,
                        'role' => $role,
                        'employee_type' => $employeeType,
                        'start_date' => $startDate,
                        'date_of_birth' => $dateOfBirth,
                        'street_address' => $streetAddress,
                        'unit_number' => $unitNumber,
                        'city' => $city,
                        'province' => $province,
                        'postal_code' => $postalCode,
                        'emergency_contact_name' => $emergencyName,
                        'emergency_contact_phone' => $emergencyPhone,
                        'emergency_contact_relationship' => $emergencyRelationship,
                        'equipment' => $equipment,
                        'perks' => $perks,
                        'notes' => $notes,
                        'processed_by' => $user_id,
                        'account_created' => $createAccount ? 'Yes' : 'No',
                        'payroll_setup' => $setupPayroll ? 'Yes' : 'No'
                    ];
                    
                    $exportResult = exportOnboardingData($pdo, [], $onboardingData, $staffName, $year);
                    
                    if ($exportResult['success'] && empty($nextcloudFolder)) {
                        $nextcloudFolder = $exportResult['folder_path'];
                    }
            } catch (Exception $ncError) {
                ErrorLogger::error("Document upload error: " . $ncError->getMessage());
                // Continue without upload - not critical
            }
            
            // Update onboarding record with Nextcloud folder
            if ($nextcloudFolder) {
                $pdo->prepare("UPDATE employee_onboarding SET nextcloud_folder = ? WHERE id = ?")->execute([$nextcloudFolder, $onboardingId]);
            }
            
            // Create and send employment contract if requested
            $contractCreated = false;
            $contractSent = false;
            $contractId = null;
            
            if ($createContract && $docusealTemplateId > 0) {
                try {
                    require_once 'lib/docuseal.php';
                    
                    // Build employee address for contract
                    $employeeAddress = $streetAddress;
                    if (!empty($unitNumber)) {
                        $employeeAddress .= ', ' . $unitNumber;
                    }
                    $employeeAddress .= "\n" . $city . ', ' . $province . ' ' . $postalCode;
                    
                    // Prepare contract data
                    $contractData = [
                        'employee_name' => $firstName . ' ' . $lastName,
                        'employee_address' => $employeeAddress,
                        'start_date' => $startDate,
                        'position' => ucfirst(str_replace('_', ' ', $role)),
                        'salary' => $contractSalary,
                        'pay_frequency' => $contractPayFrequency
                    ];
                    
                    // Create contract record in database
                    $contractStmt = $pdo->prepare("
                        INSERT INTO employee_contracts 
                        (onboarding_id, employee_name, employee_email, contract_title, 
                         contract_data, status, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, 'draft', ?, NOW())
                    ");
                    $contractStmt->execute([
                        $onboardingId,
                        $firstName . ' ' . $lastName,
                        $email,
                        $contractTitle,
                        json_encode($contractData),
                        $user_id
                    ]);
                    $contractId = $pdo->lastInsertId();
                    $contractCreated = true;
                    
                    // Send the contract for e-signature via DocuSeal
                    $esignResult = createEsignatureRequest(
                        $pdo,
                        $contractId,
                        $docusealTemplateId,
                        $email,
                        $firstName . ' ' . $lastName,
                        $contractData
                    );
                    
                    if ($esignResult['success']) {
                        $contractSent = true;
                        
                        // Update onboarding record to indicate contract was sent
                        $pdo->prepare("UPDATE employee_onboarding SET contract_sent = 1, contract_id = ? WHERE id = ?")->execute([$contractId, $onboardingId]);
                    } else {
                        ErrorLogger::error("Failed to send contract for signature: " . $esignResult['message']);
                    }
                    
                } catch (Exception $contractError) {
                    ErrorLogger::error("Error creating/sending contract during onboarding: " . $contractError->getMessage());
                    // Continue without contract - not critical to fail the entire onboarding
                }
            }
            
            // Audit log
            $auditData = [
                'action' => 'EMPLOYEE_ONBOARDING_CREATED',
                'onboarding_id' => $onboardingId,
                'staff_name' => $firstName . ' ' . $lastName,
                'role' => $role,
                'account_created' => $createAccount,
                'user_id_created' => $newUserId,
                'payroll_setup' => $setupPayroll,
                'equipment_count' => count(array_filter($equipment, function($e) { return !empty($e['name']); })),
                'perks_count' => count(array_filter($perks, function($p) { return !empty($p['name']); })),
                'documents_uploaded' => count($uploadedDocs),
                'nextcloud_folder' => $nextcloudFolder,
                'contract_created' => $contractCreated,
                'contract_sent' => $contractSent,
                'contract_id' => $contractId,
                'extension_requested' => $extensionRequested
            ];
            
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, table_name, record_id, new_values, ip_address, user_agent, created_at)
                VALUES (?, 'CREATE', 'employee_onboarding', ?, ?, ?, ?, NOW())
            ");
            $auditStmt->execute([
                $user_id, $onboardingId, json_encode($auditData),
                getClientIP(), $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            $pdo->commit();
            
            // Send welcome email with credentials (after commit to ensure account exists)
            if ($createAccount && $tempPassword) {
                require_once 'mailer.php';
                sendEmail($email, 'manual_welcome', [
                    'name' => $firstName . ' ' . $lastName,
                    'email' => $email,
                    'password' => $tempPassword
                ]);
            }
            
            $successMsg = 'Onboarding started for ' . $firstName . ' ' . $lastName;
            if ($createAccount) {
                $successMsg .= '. User account created with temporary password.';
            }
            if ($extensionRequested) {
                $successMsg .= ' Phone extension request sent to IT.';
            } elseif ($createExtension && !$extensionRequested) {
                $successMsg .= ' Note: Could not send extension request to IT.';
            }
            if ($contractSent) {
                $successMsg .= ' Employment contract sent for e-signature.';
            } elseif ($contractCreated) {
                $successMsg .= ' Contract created but could not be sent for signature.';
            }
            
            $_SESSION['flash_message'] = $successMsg;
            $_SESSION['flash_type'] = 'success';
            header('Location: dashboard.php?page=onboarding&tab=list');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: dashboard.php?page=onboarding&tab=new');
        exit;
    }
}

// Handle Complete Onboarding
if ($action === 'complete') {
    try {
        $onboardingId = intval($_POST['onboarding_id']);
        
        $updateStmt = $pdo->prepare("
            UPDATE employee_onboarding 
            SET onboarding_status = 'completed', completed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$onboardingId]);
        Auditor::log($pdo, $user_id, 'UPDATE', 'employee_onboarding', $onboardingId, ['action' => 'Completed onboarding']);
        
        $_SESSION['flash_message'] = 'Onboarding marked as completed';
        $_SESSION['flash_type'] = 'success';
        header('Location: dashboard.php?page=onboarding&tab=list');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: dashboard.php?page=onboarding&tab=list');
        exit;
    }
}

// Handle Cancel Onboarding
if ($action === 'cancel') {
    try {
        $onboardingId = intval($_POST['onboarding_id']);
        
        $updateStmt = $pdo->prepare("
            UPDATE employee_onboarding 
            SET onboarding_status = 'cancelled', updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$onboardingId]);
        Auditor::log($pdo, $user_id, 'UPDATE', 'employee_onboarding', $onboardingId, ['action' => 'Cancelled onboarding']);
        
        $_SESSION['flash_message'] = 'Onboarding cancelled';
        $_SESSION['flash_type'] = 'success';
        header('Location: dashboard.php?page=onboarding&tab=list');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: dashboard.php?page=onboarding&tab=list');
        exit;
    }
}

// If no valid action, redirect to onboarding page
header('Location: dashboard.php?page=onboarding');
exit;
