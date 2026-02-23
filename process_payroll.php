<?php
/**
 * Process Payroll Actions
 * Handles all payroll operations with Stripe integration and Nextcloud document storage
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once 'cloud_config.php';
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

// Banking encryption now uses encryptPassword()/decryptPassword() from security.php

/**
 * Calculate payroll deductions based on CRA rates
 */
function calculateDeductions($grossPay, $payrollInfo, $pdo) {
    $year = date('Y');
    $deductions = [
        'cpp' => 0,
        'ei' => 0,
        'federal_tax' => 0,
        'provincial_tax' => 0,
        'pension' => 0
    ];
    
    // Calculate pay periods per year based on frequency
    $payPeriodsPerYear = match($payrollInfo['pay_frequency']) {
        'weekly' => 52,
        'bi-weekly' => 26,
        'semi-monthly' => 24,
        'monthly' => 12,
        default => 26
    };
    
    // Get CRA rates for current year
    $ratesQuery = "SELECT * FROM cra_tax_rates WHERE tax_year = ?";
    $stmt = $pdo->prepare($ratesQuery);
    $stmt->execute([$year]);
    $rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cppRate = null;
    $eiRate = null;
    $federalBrackets = [];
    $provincialBrackets = [];
    $federalBasic = 0;
    $provincialBasic = 0;
    
    foreach ($rates as $rate) {
        if ($rate['rate_type'] === 'cpp' && $rate['province'] === null) {
            $cppRate = $rate;
        } elseif ($rate['rate_type'] === 'ei' && $rate['province'] === null) {
            $eiRate = $rate;
        } elseif ($rate['rate_type'] === 'federal_basic') {
            $federalBasic = $rate['basic_exemption'];
        } elseif ($rate['rate_type'] === 'federal_bracket') {
            $federalBrackets[] = $rate;
        } elseif ($rate['rate_type'] === 'provincial_basic' && $rate['province'] === $payrollInfo['tax_province']) {
            $provincialBasic = $rate['basic_exemption'];
        } elseif ($rate['rate_type'] === 'provincial_bracket' && $rate['province'] === $payrollInfo['tax_province']) {
            $provincialBrackets[] = $rate;
        }
    }
    
    // CPP calculation (if not exempt)
    if (!$payrollInfo['cpp_exempt'] && $cppRate) {
        $basicExemption = $cppRate['basic_exemption'] / $payPeriodsPerYear; // Per pay period
        $maxPensionable = $cppRate['max_pensionable_earnings'];
        $cppableEarnings = max(0, $grossPay - $basicExemption);
        $deductions['cpp'] = round($cppableEarnings * ($cppRate['rate_percentage'] / 100), 2);
    }
    
    // EI calculation (if not exempt)
    if (!$payrollInfo['ei_exempt'] && $eiRate) {
        $maxInsurable = $eiRate['max_insurable_earnings'] / $payPeriodsPerYear; // Per pay period
        $eiableEarnings = min($grossPay, $maxInsurable);
        $deductions['ei'] = round($eiableEarnings * ($eiRate['rate_percentage'] / 100), 2);
    }
    
    // Federal tax calculation (simplified - annualize then de-annualize)
    $annualIncome = $grossPay * $payPeriodsPerYear;
    $taxableIncome = max(0, $annualIncome - $federalBasic);
    $federalTax = 0;
    
    usort($federalBrackets, function($a, $b) {
        return $a['bracket_min'] - $b['bracket_min'];
    });
    
    foreach ($federalBrackets as $bracket) {
        if ($taxableIncome > $bracket['bracket_min']) {
            $bracketMax = $bracket['bracket_max'] ?? PHP_FLOAT_MAX;
            $bracketAmount = min($taxableIncome, $bracketMax) - $bracket['bracket_min'];
            if ($bracketAmount > 0) {
                $federalTax += $bracketAmount * ($bracket['rate_percentage'] / 100);
            }
        }
    }
    $deductions['federal_tax'] = round($federalTax / $payPeriodsPerYear, 2); // Per pay period
    
    // Provincial tax calculation
    $provincialTaxableIncome = max(0, $annualIncome - $provincialBasic);
    $provincialTax = 0;
    
    usort($provincialBrackets, function($a, $b) {
        return $a['bracket_min'] - $b['bracket_min'];
    });
    
    foreach ($provincialBrackets as $bracket) {
        if ($provincialTaxableIncome > $bracket['bracket_min']) {
            $bracketMax = $bracket['bracket_max'] ?? PHP_FLOAT_MAX;
            $bracketAmount = min($provincialTaxableIncome, $bracketMax) - $bracket['bracket_min'];
            if ($bracketAmount > 0) {
                $provincialTax += $bracketAmount * ($bracket['rate_percentage'] / 100);
            }
        }
    }
    $deductions['provincial_tax'] = round($provincialTax / $payPeriodsPerYear, 2);
    
    // Pension deduction
    if ($payrollInfo['pension_enrolled']) {
        $deductions['pension'] = round($grossPay * ($payrollInfo['pension_contribution_rate'] / 100), 2);
    }
    
    return $deductions;
}

/**
 * Upload payroll documents to Nextcloud
 */
function uploadPayrollDocuments($pdo, $settings, $staffName, $year, $documentType, $content, $filename) {
    try {
        $connection = connectNextcloud($settings);
        
        // Base payroll directory
        $payrollDir = $settings['nextcloud_hr_dir'] ?? '/Arctic_Wolves/HR';
        $payrollDir .= '/Payroll';
        
        // Sanitize staff name
        $safeStaffName = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $staffName);
        $safeStaffName = str_replace(' ', '_', trim($safeStaffName));
        
        // Create folder structure: /HR/Payroll/YYYY/StaffName
        $folderPath = ensureNextcloudPath($connection, $payrollDir, [$year, $safeStaffName]);
        
        // Upload file to Nextcloud
        $remotePath = $folderPath . '/' . $filename;
        uploadToNextcloud($connection, $remotePath, $content, 'application/pdf');
        
        // Also upload to Paperless-NGX with HR tag
        $tmpFile = sys_get_temp_dir() . '/' . uniqid('payroll_') . '.pdf';
        file_put_contents($tmpFile, $content);
        $title = 'HR_Payroll_' . $safeStaffName . '_' . $year . '_' . $filename;
        uploadToPaperless($pdo, $tmpFile, 'HR', $title);
        if (file_exists($tmpFile)) { unlink($tmpFile); }
        
        return [
            'success' => true,
            'folder_path' => $folderPath,
            'file_path' => $remotePath
        ];
    } catch (Exception $e) {
        ErrorLogger::error("Error uploading payroll document: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Handle Add Employee to Payroll
if ($action === 'add_employee') {
    try {
        $employeeUserId = intval($_POST['user_id']);
        $startDate = trim($_POST['start_date']);
        $employeeType = trim($_POST['employee_type']);
        $payRate = floatval($_POST['pay_rate']);
        $payFrequency = trim($_POST['pay_frequency']);
        $taxProvince = trim($_POST['tax_province']);
        $cppEnabled = isset($_POST['cpp_enabled']) ? 1 : 0;
        $eiEnabled = isset($_POST['ei_enabled']) ? 1 : 0;
        $pensionEnrolled = isset($_POST['pension_enrolled']) ? 1 : 0;
        $pensionRate = floatval($_POST['pension_contribution_rate'] ?? 0);
        $employerMatch = floatval($_POST['employer_pension_match'] ?? 0);
        
        // Address fields
        $streetAddress = trim($_POST['street_address']);
        $unitNumber = trim($_POST['unit_number'] ?? '');
        $city = trim($_POST['city']);
        $addressProvince = trim($_POST['address_province']);
        $postalCode = strtoupper(trim($_POST['postal_code']));
        
        // Banking fields
        $institutionNumber = trim($_POST['institution_number']);
        $transitNumber = trim($_POST['transit_number']);
        $accountNumber = trim($_POST['account_number']);
        
        $notes = trim($_POST['notes'] ?? '');
        
        // Validation
        if (empty($employeeUserId) || empty($startDate) || empty($employeeType) || $payRate <= 0) {
            throw new Exception('Required fields are missing');
        }
        
        // Verify user exists and is staff
        $userStmt = $pdo->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE id = ? AND role IN ('admin', 'coach', 'health_coach', 'team_coach') AND is_active = 1");
        $userStmt->execute([$employeeUserId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('User not found or not eligible for payroll');
        }
        $user = decryptUserRow($user);
        
        // Check if already on payroll
        $existsStmt = $pdo->prepare("SELECT id FROM employee_payroll WHERE user_id = ?");
        $existsStmt->execute([$employeeUserId]);
        if ($existsStmt->fetch()) {
            throw new Exception('Employee is already on payroll');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Insert payroll record
            $payrollStmt = $pdo->prepare("
                INSERT INTO employee_payroll 
                (user_id, employee_type, pay_rate, pay_frequency, start_date, tax_province, 
                 cpp_exempt, ei_exempt, pension_enrolled, pension_contribution_rate, employer_pension_match, 
                 status, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
            ");
            $payrollStmt->execute([
                $employeeUserId, $employeeType, $payRate, $payFrequency, $startDate, $taxProvince,
                $cppEnabled ? 0 : 1, $eiEnabled ? 0 : 1, $pensionEnrolled, $pensionRate, $employerMatch,
                $notes
            ]);
            
            // Insert address
            $addressStmt = $pdo->prepare("
                INSERT INTO employee_addresses 
                (user_id, address_type, street_address, unit_number, city, province, postal_code, is_primary, created_at)
                VALUES (?, 'home', ?, ?, ?, ?, ?, 1, NOW())
            ");
            $addressStmt->execute([$employeeUserId, $streetAddress, $unitNumber, $city, $addressProvince, $postalCode]);
            
            // Insert encrypted banking info
            $encryptedAccount = encryptPassword($accountNumber);
            $bankingStmt = $pdo->prepare("
                INSERT INTO employee_banking 
                (user_id, institution_number, transit_number, account_number_encrypted, account_type, is_primary, created_at)
                VALUES (?, ?, ?, ?, 'checking', 1, NOW())
            ");
            $bankingStmt->execute([$employeeUserId, $institutionNumber, $transitNumber, $encryptedAccount]);
            
            // Audit log
            $auditData = [
                'action' => 'PAYROLL_EMPLOYEE_ADDED',
                'user_id' => $employeeUserId,
                'employee_name' => $user['first_name'] . ' ' . $user['last_name'],
                'employee_type' => $employeeType,
                'pay_rate' => $payRate,
                'added_by' => $user_id
            ];
            
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, table_name, record_id, new_values, ip_address, user_agent, created_at)
                VALUES (?, 'CREATE', 'employee_payroll', ?, ?, ?, ?, NOW())
            ");
            $auditStmt->execute([
                $user_id, $employeeUserId, json_encode($auditData),
                $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            $pdo->commit();
            
            $_SESSION['flash_message'] = 'Successfully added ' . $user['first_name'] . ' ' . $user['last_name'] . ' to payroll';
            $_SESSION['flash_type'] = 'success';
            header('Location: dashboard.php?page=payroll&tab=employees');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: dashboard.php?page=payroll&tab=add');
        exit;
    }
}

// Handle Update Employee Payroll
if ($action === 'update_employee') {
    try {
        $payrollId = intval($_POST['payroll_id']);
        $employeeType = trim($_POST['employee_type']);
        $payRate = floatval($_POST['pay_rate']);
        $payFrequency = trim($_POST['pay_frequency']);
        $taxProvince = trim($_POST['tax_province']);
        $cppEnabled = isset($_POST['cpp_enabled']) ? 1 : 0;
        $eiEnabled = isset($_POST['ei_enabled']) ? 1 : 0;
        $pensionEnrolled = isset($_POST['pension_enrolled']) ? 1 : 0;
        $pensionRate = floatval($_POST['pension_contribution_rate'] ?? 0);
        $employerMatch = floatval($_POST['employer_pension_match'] ?? 0);
        $startDate = !empty($_POST['start_date']) ? trim($_POST['start_date']) : null;
        $federalTd1 = !empty($_POST['federal_td1_claim']) ? floatval($_POST['federal_td1_claim']) : null;
        $provincialTd1 = !empty($_POST['provincial_td1_claim']) ? floatval($_POST['provincial_td1_claim']) : null;
        $additionalTax = !empty($_POST['additional_tax_deduction']) ? floatval($_POST['additional_tax_deduction']) : null;
        $notes = trim($_POST['notes'] ?? '');
        
        $updateStmt = $pdo->prepare("
            UPDATE employee_payroll SET
                employee_type = ?,
                pay_rate = ?,
                pay_frequency = ?,
                tax_province = ?,
                cpp_exempt = ?,
                ei_exempt = ?,
                pension_enrolled = ?,
                pension_contribution_rate = ?,
                employer_pension_match = ?,
                start_date = ?,
                federal_td1_claim = ?,
                provincial_td1_claim = ?,
                additional_tax_deduction = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([
            $employeeType, $payRate, $payFrequency, $taxProvince,
            $cppEnabled ? 0 : 1, $eiEnabled ? 0 : 1, $pensionEnrolled,
            $pensionRate, $employerMatch, $startDate,
            $federalTd1, $provincialTd1, $additionalTax, $notes,
            $payrollId
        ]);
        
        Auditor::log($pdo, $user_id, 'update', 'employee_payroll', $payrollId, ['action' => 'updated_payroll_settings', 'pay_rate' => $payRate, 'employee_type' => $employeeType]);
        
        $_SESSION['flash_message'] = 'Payroll settings updated successfully';
        $_SESSION['flash_type'] = 'success';
        header('Location: dashboard.php?page=payroll&tab=employees');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: dashboard.php?page=payroll&tab=employees');
        exit;
    }
}

// Handle Remove Employee from Payroll
if ($action === 'remove_employee') {
    try {
        $payrollId = intval($_POST['payroll_id']);
        
        // Soft delete - mark as terminated
        $updateStmt = $pdo->prepare("UPDATE employee_payroll SET status = 'terminated', end_date = CURDATE(), updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$payrollId]);
        
        Auditor::log($pdo, $user_id, 'update', 'employee_payroll', $payrollId, ['action' => 'removed_from_payroll', 'status' => 'terminated']);
        
        $_SESSION['flash_message'] = 'Employee removed from payroll';
        $_SESSION['flash_type'] = 'success';
        header('Location: dashboard.php?page=payroll&tab=employees');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: dashboard.php?page=payroll&tab=employees');
        exit;
    }
}

// Handle Run Payroll
if ($action === 'run_payroll') {
    try {
        $payPeriodStart = trim($_POST['pay_period_start']);
        $payPeriodEnd = trim($_POST['pay_period_end']);
        $payDate = trim($_POST['pay_date']);
        $employees = $_POST['employees'] ?? [];
        
        if (empty($employees)) {
            throw new Exception('No employees selected for payroll');
        }
        
        $pdo->beginTransaction();
        
        try {
            foreach ($employees as $empUserId) {
                // Get payroll info
                $payrollStmt = $pdo->prepare("
                    SELECT ep.*, u.first_name, u.last_name 
                    FROM employee_payroll ep 
                    JOIN users u ON ep.user_id = u.id 
                    WHERE ep.user_id = ? AND ep.status = 'active'
                ");
                $payrollStmt->execute([$empUserId]);
                $payrollInfo = $payrollStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$payrollInfo) continue;
                
                // Calculate gross pay based on type
                if ($payrollInfo['employee_type'] === 'salary') {
                    // Salary divided by pay periods per year
                    $periodsPerYear = $payrollInfo['pay_frequency'] === 'bi-weekly' ? 26 : 
                                     ($payrollInfo['pay_frequency'] === 'weekly' ? 52 : 
                                     ($payrollInfo['pay_frequency'] === 'semi-monthly' ? 24 : 12));
                    $grossPay = $payrollInfo['pay_rate'] / $periodsPerYear;
                } else {
                    // Hourly - calculate standard hours based on pay frequency
                    // Standard work week is 40 hours in Canada
                    $standardWeeklyHours = 40;
                    $hoursWorked = match($payrollInfo['pay_frequency']) {
                        'weekly' => $standardWeeklyHours,        // 40 hours per week
                        'bi-weekly' => $standardWeeklyHours * 2, // 80 hours per 2 weeks
                        'semi-monthly' => $standardWeeklyHours * 2.17, // ~86.8 hours
                        'monthly' => $standardWeeklyHours * 4.33, // ~173.2 hours
                        default => $standardWeeklyHours * 2
                    };
                    $grossPay = $payrollInfo['pay_rate'] * $hoursWorked;
                }
                
                // Calculate deductions
                $deductions = calculateDeductions($grossPay, $payrollInfo, $pdo);
                $totalDeductions = array_sum($deductions);
                $netPay = $grossPay - $totalDeductions;
                
                // Get YTD amounts
                $ytdStmt = $pdo->prepare("
                    SELECT 
                        COALESCE(SUM(gross_pay), 0) as ytd_gross,
                        COALESCE(SUM(cpp_deduction), 0) as ytd_cpp,
                        COALESCE(SUM(ei_deduction), 0) as ytd_ei,
                        COALESCE(SUM(federal_tax + provincial_tax), 0) as ytd_tax
                    FROM payroll_history 
                    WHERE user_id = ? AND YEAR(pay_date) = YEAR(?)
                ");
                $ytdStmt->execute([$empUserId, $payDate]);
                $ytd = $ytdStmt->fetch(PDO::FETCH_ASSOC);
                
                // Insert payroll history
                $historyStmt = $pdo->prepare("
                    INSERT INTO payroll_history 
                    (user_id, pay_period_start, pay_period_end, pay_date, gross_pay,
                     cpp_deduction, ei_deduction, federal_tax, provincial_tax, pension_deduction,
                     total_deductions, net_pay, ytd_gross, ytd_cpp, ytd_ei, ytd_tax,
                     payment_status, processed_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                ");
                $historyStmt->execute([
                    $empUserId, $payPeriodStart, $payPeriodEnd, $payDate, round($grossPay, 2),
                    $deductions['cpp'], $deductions['ei'], $deductions['federal_tax'], $deductions['provincial_tax'], $deductions['pension'],
                    round($totalDeductions, 2), round($netPay, 2),
                    $ytd['ytd_gross'] + $grossPay, $ytd['ytd_cpp'] + $deductions['cpp'],
                    $ytd['ytd_ei'] + $deductions['ei'], $ytd['ytd_tax'] + $deductions['federal_tax'] + $deductions['provincial_tax'],
                    $user_id
                ]);
            }
            
            $pdo->commit();
            
            Auditor::log($pdo, $user_id, 'create', 'payroll_history', null, ['action' => 'ran_payroll', 'employee_count' => count($employees), 'pay_period' => "$payPeriodStart to $payPeriodEnd"]);
            
            $_SESSION['flash_message'] = 'Payroll calculated for ' . count($employees) . ' employee(s). Review and process payments.';
            $_SESSION['flash_type'] = 'success';
            header('Location: dashboard.php?page=payroll&tab=run');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: dashboard.php?page=payroll&tab=run');
        exit;
    }
}

// Handle Generate T4s
if ($action === 'generate_all_t4s') {
    try {
        $taxYear = intval($_POST['tax_year']);
        
        // Get all employees with payroll history for the tax year
        $employeesStmt = $pdo->prepare("
            SELECT DISTINCT ep.user_id, u.first_name, u.last_name, ep.tax_province,
                   ea.street_address, ea.unit_number, ea.city, ea.province, ea.postal_code
            FROM employee_payroll ep
            JOIN users u ON ep.user_id = u.id
            LEFT JOIN employee_addresses ea ON ep.user_id = ea.user_id AND ea.is_primary = 1
            WHERE EXISTS (
                SELECT 1 FROM payroll_history ph 
                WHERE ph.user_id = ep.user_id AND YEAR(ph.pay_date) = ?
            )
        ");
        $employeesStmt->execute([$taxYear]);
        $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $generated = 0;
        $ncSettings = getNextcloudSettings($pdo);
        
        foreach ($employees as $emp) {
            // Get year totals
            $totalsStmt = $pdo->prepare("
                SELECT 
                    SUM(gross_pay) as total_income,
                    SUM(cpp_deduction) as total_cpp,
                    SUM(ei_deduction) as total_ei,
                    SUM(federal_tax + provincial_tax) as total_tax,
                    SUM(pension_deduction) as total_pension
                FROM payroll_history 
                WHERE user_id = ? AND YEAR(pay_date) = ?
            ");
            $totalsStmt->execute([$emp['user_id'], $taxYear]);
            $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Build address
            $address = $emp['street_address'];
            if ($emp['unit_number']) $address .= ', ' . $emp['unit_number'];
            $address .= "\n" . $emp['city'] . ', ' . $emp['province'] . ' ' . $emp['postal_code'];
            
            // Insert or update T4 record
            $t4Stmt = $pdo->prepare("
                INSERT INTO t4_slips 
                (user_id, tax_year, employment_income, cpp_contributions, ei_premiums, 
                 income_tax_deducted, rpp_contributions, province_of_employment, employee_address,
                 generated_at, generated_by, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'generated')
                ON DUPLICATE KEY UPDATE
                    employment_income = VALUES(employment_income),
                    cpp_contributions = VALUES(cpp_contributions),
                    ei_premiums = VALUES(ei_premiums),
                    income_tax_deducted = VALUES(income_tax_deducted),
                    rpp_contributions = VALUES(rpp_contributions),
                    employee_address = VALUES(employee_address),
                    generated_at = NOW(),
                    status = 'generated'
            ");
            $t4Stmt->execute([
                $emp['user_id'], $taxYear, $totals['total_income'], $totals['total_cpp'],
                $totals['total_ei'], $totals['total_tax'], $totals['total_pension'],
                $emp['tax_province'], $address, $user_id
            ]);
            
            // Upload to Nextcloud if configured
            if (!empty($ncSettings['nextcloud_url'])) {
                $staffName = $emp['first_name'] . ' ' . $emp['last_name'];
                $filename = 'T4_' . str_replace(' ', '_', $staffName) . '_' . $taxYear . '.txt';
                $content = "T4 STATEMENT OF REMUNERATION PAID\n";
                $content .= "Tax Year: $taxYear\n\n";
                $content .= "Employee: $staffName\n";
                $content .= "Address: " . str_replace("\n", ", ", $address) . "\n\n";
                $content .= "Box 14 - Employment Income: $" . number_format($totals['total_income'], 2) . "\n";
                $content .= "Box 16 - CPP Contributions: $" . number_format($totals['total_cpp'], 2) . "\n";
                $content .= "Box 18 - EI Premiums: $" . number_format($totals['total_ei'], 2) . "\n";
                $content .= "Box 20 - RPP Contributions: $" . number_format($totals['total_pension'], 2) . "\n";
                $content .= "Box 22 - Income Tax Deducted: $" . number_format($totals['total_tax'], 2) . "\n";
                
                $uploadResult = uploadPayrollDocuments($pdo, $ncSettings, $staffName, $taxYear, 'T4', $content, $filename);
                
                if ($uploadResult['success']) {
                    $updatePath = $pdo->prepare("UPDATE t4_slips SET nextcloud_path = ? WHERE user_id = ? AND tax_year = ?");
                    $updatePath->execute([$uploadResult['file_path'], $emp['user_id'], $taxYear]);
                }
            }
            
            $generated++;
        }
        
        $_SESSION['flash_message'] = "Generated $generated T4 slip(s) for tax year $taxYear";
        
        Auditor::log($pdo, $user_id, 'create', 't4_slips', null, ['action' => 'generated_t4s', 'tax_year' => $taxYear, 'count' => $generated]);
        
        $_SESSION['flash_type'] = 'success';
        header('Location: dashboard.php?page=payroll&tab=t4');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: dashboard.php?page=payroll&tab=t4');
        exit;
    }
}

// Handle Update CRA Rates
if ($action === 'update_cra_rates') {
    try {
        $taxYear = intval($_POST['tax_year']);
        
        // Copy rates from previous year with placeholder values
        // In production, this would fetch from CRA API or database
        $copyStmt = $pdo->prepare("
            INSERT INTO cra_tax_rates 
            (tax_year, rate_type, province, bracket_min, bracket_max, rate_percentage, 
             max_pensionable_earnings, max_insurable_earnings, basic_exemption, effective_date, notes)
            SELECT ?, rate_type, province, bracket_min, bracket_max, rate_percentage,
                   max_pensionable_earnings, max_insurable_earnings, basic_exemption, 
                   CONCAT(?, '-01-01'), CONCAT('Copied from ', tax_year, ' - Update as needed')
            FROM cra_tax_rates 
            WHERE tax_year = ?
            ON DUPLICATE KEY UPDATE notes = CONCAT('Rate exists for ', ?)
        ");
        $copyStmt->execute([$taxYear, $taxYear, $taxYear - 1, $taxYear]);
        
        $_SESSION['flash_message'] = "Tax rates for $taxYear loaded. Please review and update as needed.";
        $_SESSION['flash_type'] = 'success';
        header('Location: dashboard.php?page=payroll&tab=rates');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: dashboard.php?page=payroll&tab=rates');
        exit;
    }
}

// If no valid action, redirect to payroll page
header('Location: dashboard.php?page=payroll');
exit;
