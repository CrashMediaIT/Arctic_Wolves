<?php
/**
 * Process Registration
 * Handles both Athlete and Parent registration flows
 * Parents can register multiple athletes during signup
 */
session_start();
require 'db_config.php';
require 'mailer.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/blocklist.php';
require_once __DIR__ . '/lib/encryption.php';

/**
 * Generate a unique email for an athlete based on parent's email
 * @param PDO $pdo Database connection
 * @param string $parentEmail Parent's email address
 * @param string $athleteFirstName Athlete's first name
 * @return string Unique email for the athlete
 */
function generateUniqueAthleteEmail($pdo, $parentEmail, $athleteFirstName) {
    $sanitizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $athleteFirstName));
    $baseEmail = preg_replace('/@/', '+' . $sanitizedName . '@', $parentEmail, 1);
    $athleteEmail = $baseEmail;
    
    // Check if this email exists, if so, add a number
    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->execute([$athleteEmail]);
    $counter = 1;
    
    while ($check_stmt->rowCount() > 0) {
        $athleteEmail = preg_replace('/@/', '+' . $sanitizedName . $counter . '@', $parentEmail, 1);
        $check_stmt->execute([$athleteEmail]);
        $counter++;
    }
    
    return $athleteEmail;
}

/**
 * Generate a secure random password
 * @param int $length Length of the password (default 16)
 * @return string Random password with mixed characters
 */
function generateSecurePassword($length = 16) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $charsLength = strlen($chars);
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $charsLength - 1)];
    }
    
    return $password;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        header("Location: register.php?error=csrf_invalid");
        exit();
    }
    
    // Get common fields
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role  = $_POST['role'] ?? 'athlete';
    $pass  = $_POST['password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    
    // Validate required fields
    if (empty($first) || empty($last) || empty($email) || empty($pass)) {
        header("Location: register.php?error=invalid_data");
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.php?error=invalid_data");
        exit();
    }
    
    // Validate password match
    if ($pass !== $confirm_pass) {
        header("Location: register.php?error=password_mismatch");
        exit();
    }
    
    // Validate password length
    if (strlen($pass) < 8) {
        header("Location: register.php?error=invalid_data");
        exit();
    }
    
    // Validate role
    if (!in_array($role, ['athlete', 'parent'])) {
        $role = 'athlete';
    }

    // Validate agreements
    $waiver_accepted = isset($_POST['waiver_accepted']);
    $privacy_accepted = isset($_POST['privacy_accepted']);
    $promotional_opt_in = isset($_POST['promotional_opt_in']) ? 1 : 0;
    $share_evaluations_potential = isset($_POST['share_evaluations_potential_teams']) ? 1 : 0;

    if (!$waiver_accepted || !$privacy_accepted) {
        header("Location: register.php?error=agreements_not_accepted");
        exit();
    }
    
    // 1. CHECK DUPLICATE EMAIL
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        header("Location: register.php?error=email_taken");
        exit();
    }

    // 2. CHECK BLOCKLIST (email, name, or IP)
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? null;
    $blockCheck = Blocklist::checkRegistration($pdo, $email, $first, $last, $clientIp);
    if ($blockCheck['blocked']) {
        header("Location: register.php?error=blocked");
        exit();
    }

    $hash_pass = password_hash($pass, PASSWORD_BCRYPT);
    $verify_code = rand(100000, 999999);

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Encrypt PII fields before storing (email kept as-is for login lookups)
        $enc_first = FieldEncryption::encrypt($first);
        $enc_last = FieldEncryption::encrypt($last);
        $enc_phone = $phone ? FieldEncryption::encrypt($phone) : null;
        
        if ($role === 'athlete') {
            // ATHLETE REGISTRATION
            $pos = $_POST['position'] ?? '';
            $dob = $_POST['birth_date'] ?? null;
            $enc_dob = $dob ? FieldEncryption::encrypt($dob) : null;
            
            $sql = "INSERT INTO users (first_name, last_name, email, password, role, position, birth_date, phone, is_verified, verification_code, agreements_accepted, promotional_opt_in) 
                    VALUES (?, ?, ?, ?, 'athlete', ?, ?, ?, 0, ?, 1, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$enc_first, $enc_last, $email, $hash_pass, $pos ?: null, $enc_dob, $enc_phone, $verify_code, $promotional_opt_in]);

            $new_user_id = $pdo->lastInsertId();

            // Record agreement acceptance
            $client_ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $agree_sql = "INSERT INTO user_agreements (user_id, agreement_type, agreement_version, accepted_at, ip_address, user_agent, signature_status, promotional_opt_in, share_evaluations_potential_teams) VALUES (?, ?, '1.0', NOW(), ?, ?, 'signed', ?, ?)";
            $agree_stmt = $pdo->prepare($agree_sql);
            $agree_stmt->execute([$new_user_id, 'waiver', $client_ip, $user_agent, $promotional_opt_in, $share_evaluations_potential]);
            $agree_stmt->execute([$new_user_id, 'privacy_policy', $client_ip, $user_agent, $promotional_opt_in, $share_evaluations_potential]);
            
        } else {
            // PARENT REGISTRATION
            $sql = "INSERT INTO users (first_name, last_name, email, password, role, phone, is_verified, verification_code, agreements_accepted, promotional_opt_in) 
                    VALUES (?, ?, ?, ?, 'parent', ?, 0, ?, 1, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$enc_first, $enc_last, $email, $hash_pass, $enc_phone, $verify_code, $promotional_opt_in]);
            
            $parent_id = $pdo->lastInsertId();

            // Record agreement acceptance for parent
            $client_ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $agree_sql = "INSERT INTO user_agreements (user_id, agreement_type, agreement_version, accepted_at, ip_address, user_agent, signature_status, promotional_opt_in, share_evaluations_potential_teams) VALUES (?, ?, '1.0', NOW(), ?, ?, 'signed', ?, ?)";
            $agree_stmt = $pdo->prepare($agree_sql);
            $agree_stmt->execute([$parent_id, 'waiver', $client_ip, $user_agent, $promotional_opt_in, $share_evaluations_potential]);
            $agree_stmt->execute([$parent_id, 'privacy_policy', $client_ip, $user_agent, $promotional_opt_in, $share_evaluations_potential]);
            
            // Process athletes if any
            $athletes = $_POST['athletes'] ?? [];
            
            foreach ($athletes as $athlete_data) {
                $athlete_first = trim($athlete_data['first_name'] ?? '');
                $athlete_last  = trim($athlete_data['last_name'] ?? '');
                $athlete_dob   = $athlete_data['birth_date'] ?? null;
                $athlete_pos   = $athlete_data['position'] ?? '';
                $use_alt_email = isset($athlete_data['use_alt_email']);
                $alt_email     = trim($athlete_data['alt_email'] ?? '');
                
                // Skip empty athlete entries
                if (empty($athlete_first) || empty($athlete_last)) {
                    continue;
                }
                
                // Determine notification email
                // By default, notifications go to parent's email
                // If alternate email is provided and enabled, use that for the athlete's email
                $athlete_email = $email; // Parent's email as default
                if ($use_alt_email && !empty($alt_email) && filter_var($alt_email, FILTER_VALIDATE_EMAIL)) {
                    // Check if alt email already exists
                    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $check_stmt->execute([$alt_email]);
                    if ($check_stmt->rowCount() == 0) {
                        $athlete_email = $alt_email;
                    }
                }
                
                // If athlete email is same as parent, create a unique email for the athlete
                // by appending a suffix (this allows multiple athletes under one parent email)
                if ($athlete_email === $email) {
                    $athlete_email = generateUniqueAthleteEmail($pdo, $email, $athlete_first);
                }
                
                // Generate secure random password for athlete (they can reset later or parent can manage)
                $athlete_password = generateSecurePassword(16);
                $athlete_hash_pass = password_hash($athlete_password, PASSWORD_DEFAULT);
                
                // Insert athlete with encrypted PII
                $enc_athlete_first = FieldEncryption::encrypt($athlete_first);
                $enc_athlete_last = FieldEncryption::encrypt($athlete_last);
                $enc_athlete_dob = $athlete_dob ? FieldEncryption::encrypt($athlete_dob) : null;

                $athlete_sql = "INSERT INTO users (first_name, last_name, email, password, role, position, birth_date, is_verified, force_pass_change) 
                                VALUES (?, ?, ?, ?, 'athlete', ?, ?, 1, 1)";
                
                $athlete_stmt = $pdo->prepare($athlete_sql);
                $athlete_stmt->execute([
                    $enc_athlete_first, 
                    $enc_athlete_last, 
                    $athlete_email, 
                    $athlete_hash_pass, 
                    $athlete_pos ?: null, 
                    $enc_athlete_dob
                ]);
                
                $athlete_id = $pdo->lastInsertId();
                
                // Create parent-athlete relationship
                $rel_sql = "INSERT INTO parent_athlete_relationships (parent_id, athlete_id, relationship_type) 
                            VALUES (?, ?, 'parent')";
                $rel_stmt = $pdo->prepare($rel_sql);
                $rel_stmt->execute([$parent_id, $athlete_id]);
            }
        }
        
        // Commit transaction
        $pdo->commit();

        // 3. SEND VERIFICATION EMAIL
        sendEmail($email, 'verification', [
            'name' => $first,
            'code' => $verify_code
        ]);

        // 4. REDIRECT TO VERIFY PAGE
        header("Location: verify.php");
        exit();

    } catch (PDOException $e) {
        // Rollback on error
        $pdo->rollBack();
        error_log("Registration error: " . $e->getMessage());
        header("Location: register.php?error=database_error");
        exit();
    }
}
?>