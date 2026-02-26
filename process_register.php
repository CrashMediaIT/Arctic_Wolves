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
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';
require_once __DIR__ . '/lib/rate_limiter.php';
require_once __DIR__ . '/lib/input_sanitizer.php';

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
    // Rate limiting: max 5 registration attempts per IP per 30 minutes
    if ($db_connected && $pdo) {
        $rateLimiter = new RateLimiter($pdo);
        if (!$rateLimiter->isIPAllowed('registration', 5, 1800)) {
            header("Location: register.php?error=rate_limited");
            exit();
        }
    }

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        header("Location: register.php?error=csrf_invalid");
        exit();
    }

    // Verify reCAPTCHA v3 token
    $recaptcha_token = $_POST['recaptcha_token'] ?? '';
    // Load reCAPTCHA secret key from database (encrypted in system_settings)
    $recaptcha_secret = '';
    if ($db_connected && $pdo) {
        try {
            $rc_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'recaptcha_secret_key'");
            $rc_stmt->execute();
            $rc_val = $rc_stmt->fetchColumn();
            if (!empty($rc_val) && function_exists('decryptCredential')) {
                $recaptcha_secret = decryptCredential($rc_val);
            }
        } catch (PDOException $e) {
            // Setting may not exist yet
        }
    }
    if (!empty($recaptcha_secret)) {
        $recaptcha_valid = false;
        if (!empty($recaptcha_token)) {
            $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
            $verify_data = http_build_query([
                'secret' => $recaptcha_secret,
                'response' => $recaptcha_token,
                'remoteip' => getClientIP()
            ]);
            $verify_opts = ['http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $verify_data,
                'timeout' => 5
            ]];
            $verify_context = stream_context_create($verify_opts);
            $verify_response = @file_get_contents($verify_url, false, $verify_context);
            if ($verify_response !== false) {
                $verify_result = json_decode($verify_response, true);
                if (isset($verify_result['success']) && $verify_result['success'] === true
                    && isset($verify_result['score']) && $verify_result['score'] >= 0.5) {
                    $recaptcha_valid = true;
                }
            }
        }
        if (!$recaptcha_valid) {
            header("Location: register.php?error=captcha_failed");
            exit();
        }
    }
    
    // Get common fields with input sanitization
    $first = InputSanitizer::sanitizeText(trim($_POST['first_name'] ?? ''));
    $last  = InputSanitizer::sanitizeText(trim($_POST['last_name'] ?? ''));
    $email = InputSanitizer::sanitizeEmail(trim($_POST['email'] ?? ''));
    $phone = InputSanitizer::sanitizePhone(trim($_POST['phone'] ?? ''));
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

    // Validate password complexity (uppercase, lowercase, digit)
    if (!preg_match('/[A-Z]/', $pass) || !preg_match('/[a-z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
        header("Location: register.php?error=invalid_data");
        exit();
    }

    // Validate name format (letters, spaces, hyphens, apostrophes only)
    if (!preg_match('/^[a-zA-ZÀ-ÿ\s\'\-]{1,100}$/u', $first) || !preg_match('/^[a-zA-ZÀ-ÿ\s\'\-]{1,100}$/u', $last)) {
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
    $clientIp = getClientIP();
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
            $client_ip = getClientIP();
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
            $client_ip = getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $agree_sql = "INSERT INTO user_agreements (user_id, agreement_type, agreement_version, accepted_at, ip_address, user_agent, signature_status, promotional_opt_in, share_evaluations_potential_teams) VALUES (?, ?, '1.0', NOW(), ?, ?, 'signed', ?, ?)";
            $agree_stmt = $pdo->prepare($agree_sql);
            $agree_stmt->execute([$parent_id, 'waiver', $client_ip, $user_agent, $promotional_opt_in, $share_evaluations_potential]);
            $agree_stmt->execute([$parent_id, 'privacy_policy', $client_ip, $user_agent, $promotional_opt_in, $share_evaluations_potential]);
            
            // Process athletes if any
            $athletes = $_POST['athletes'] ?? [];
            
            foreach ($athletes as $athlete_data) {
                $athlete_first = InputSanitizer::sanitizeText(trim($athlete_data['first_name'] ?? ''));
                $athlete_last  = InputSanitizer::sanitizeText(trim($athlete_data['last_name'] ?? ''));
                $athlete_dob   = InputSanitizer::sanitizeDate($athlete_data['birth_date'] ?? null);
                $athlete_pos   = InputSanitizer::sanitizeText($athlete_data['position'] ?? '');
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
        
        // Handle parent invitation acceptance (if registering via invitation link)
        $invitation_token = $_POST['invitation_token'] ?? $_SESSION['parent_invitation_token'] ?? null;
        if ($invitation_token && $role === 'parent') {
            try {
                $inv_stmt = $pdo->prepare("SELECT * FROM parent_invitations WHERE token = ? AND status = 'pending' AND expires_at > NOW()");
                $inv_stmt->execute([$invitation_token]);
                $invitation = $inv_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($invitation) {
                    // Get athlete IDs from invitation
                    $ath_stmt = $pdo->prepare("SELECT athlete_id FROM parent_invitation_athletes WHERE invitation_id = ?");
                    $ath_stmt->execute([$invitation['id']]);
                    $inv_athlete_ids = $ath_stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Create managed_athletes entries
                    $ma_stmt = $pdo->prepare("INSERT IGNORE INTO managed_athletes (parent_id, athlete_id, relationship, can_book, can_view_stats, status) VALUES (?, ?, ?, 1, 1, 'active')");
                    foreach ($inv_athlete_ids as $inv_ath_id) {
                        $ma_stmt->execute([$parent_id, $inv_ath_id, $invitation['relationship']]);
                    }
                    
                    // Also add to parent_athlete_relationships
                    $par_stmt = $pdo->prepare("INSERT IGNORE INTO parent_athlete_relationships (parent_id, athlete_id, relationship_type) VALUES (?, ?, ?)");
                    foreach ($inv_athlete_ids as $inv_ath_id) {
                        $par_stmt->execute([$parent_id, $inv_ath_id, $invitation['relationship']]);
                    }
                    
                    // Mark invitation as accepted
                    $upd_stmt = $pdo->prepare("UPDATE parent_invitations SET status = 'accepted', accepted_by = ?, accepted_at = NOW() WHERE id = ?");
                    $upd_stmt->execute([$parent_id, $invitation['id']]);
                    
                    // Clear session token
                    unset($_SESSION['parent_invitation_token']);
                }
            } catch (PDOException $e) {
                ErrorLogger::error("Invitation acceptance during registration error: " . $e->getMessage());
            }
        }
        
        // Commit transaction
        $pdo->commit();

        $registered_id = ($role === 'athlete') ? $new_user_id : $parent_id;
        Auditor::log($pdo, $registered_id, 'create', 'users', $registered_id, ['action' => 'user_registered', 'role' => $role, 'email' => $email]);

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
        ErrorLogger::error("Registration error: " . $e->getMessage());
        header("Location: register.php?error=database_error");
        exit();
    }
}
?>