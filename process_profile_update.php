<?php
session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/cloud_config.php';
require_once __DIR__ . '/lib/encryption.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// 1. GATEKEEPER: Ensure user is logged in
if (!isset($_SESSION['logged_in'])) { 
    header("Location: login.php"); 
    exit(); 
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
}

$current_user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
$action = $_POST['action'] ?? '';

// =========================================================
// ACTION 1: UPLOAD PROFILE PICTURE
// =========================================================
if ($action == 'upload_avatar') {
    $target_id = $_POST['user_id']; 
    
    // Only allow update if it's ME or if I am Admin/Coach
    if ($target_id != $current_user_id && $role != 'admin' && $role != 'coach') {
        die("Access Denied.");
    }

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_pic']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $nc_filename = "avatar_" . $target_id . "_" . time() . "." . $ext;
            
            // Upload to RustFS
            $persist = persistUploadedFile($pdo, $_FILES['profile_pic']['tmp_name'], 'profiles', $nc_filename);
            $db_path = $persist['rustfs_url'] ?? null;
            
            $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute([$db_path, $target_id]);
            
            // Store Nextcloud path for persistent recovery
            if (!empty($persist['nextcloud_path'])) {
                $pdo->prepare("UPDATE users SET nextcloud_image_path = ? WHERE id = ?")->execute([$persist['nextcloud_path'], $target_id]);
            }
                
            Auditor::log($pdo, $current_user_id, 'update', 'users', $target_id, ['action' => 'uploaded_avatar']);
            
            // Redirect logic
            if ($target_id == $current_user_id) {
                header("Location: dashboard.php?page=profile&msg=avatar_updated");
            } else {
                header("Location: dashboard.php?page=athlete_detail&id=$target_id&msg=avatar_updated");
            }
            exit();
        }
    }
    header("Location: dashboard.php?page=profile&error=upload_error");
    exit();
}

// =========================================================
// ACTION 2: UPDATE BASIC INFO (Email, Position, Arena)
// =========================================================
if ($action == 'update_info') {
    $email = trim($_POST['email']);
    $pos   = $_POST['position'];
    $arena = trim($_POST['primary_arena']);
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET email = ?, position = ?, primary_arena = ? WHERE id = ?");
        $stmt->execute([$email, $pos, $arena, $current_user_id]);
        Auditor::log($pdo, $current_user_id, 'update', 'users', $current_user_id, ['action' => 'updated_basic_info']);
        header("Location: dashboard.php?page=profile&msg=updated");
        exit();
    } catch (PDOException $e) {
        die("Error updating profile.");
    }
}

// =========================================================
// ACTION 2B: COACH/ADMIN UPDATE ATHLETE INFO
// =========================================================
if ($action == 'coach_update_athlete') {
    $athlete_id = intval($_POST['athlete_id'] ?? 0);
    
    // Only admins and coaches can update athlete profiles
    if ($role !== 'admin' && $role !== 'coach' && $role !== 'coach_plus') {
        header("Location: dashboard.php?page=athlete_detail&id=$athlete_id&error=access_denied");
        exit();
    }
    
    if ($athlete_id <= 0) {
        header("Location: dashboard.php?error=invalid_id");
        exit();
    }
    
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $position = $_POST['position'] ?? null;
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $shooting_hand = $_POST['shooting_hand'] ?? null;
    
    try {
        // Encrypt PII fields if encryption is available
        $enc_first = class_exists('FieldEncryption') ? FieldEncryption::encrypt($first_name) : $first_name;
        $enc_last = class_exists('FieldEncryption') ? FieldEncryption::encrypt($last_name) : $last_name;
        $enc_email = class_exists('FieldEncryption') ? FieldEncryption::encrypt($email) : $email;
        
        $stmt = $pdo->prepare("
            UPDATE users SET first_name = ?, last_name = ?, email = ?, position = ?, birth_date = ?, shooting_hand = ?
            WHERE id = ?
        ");
        $stmt->execute([$enc_first, $enc_last, $enc_email, $position, $birth_date, $shooting_hand, $athlete_id]);
        
        Auditor::log($pdo, $current_user_id, 'update', 'users', $athlete_id, ['action' => 'coach_updated_athlete_profile']);
        
        header("Location: dashboard.php?page=athlete_detail&id=$athlete_id&msg=success");
        exit();
    } catch (PDOException $e) {
        ErrorLogger::error("Coach athlete profile update error: " . $e->getMessage());
        header("Location: dashboard.php?page=athlete_detail&id=$athlete_id&edit=1&error=update_failed");
        exit();
    }
}

// =========================================================
// ACTION 3: STANDARD PASSWORD CHANGE (Voluntary)
// =========================================================
if ($action == 'change_password') {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    
    // Validate that new password and confirm password match
    if ($new_pass !== $confirm_pass) {
        header("Location: dashboard.php?page=profile&tab=security&error=passwords_mismatch");
        exit();
    }
    
    // Validate password strength (minimum 8 characters)
    if (strlen($new_pass) < 8) {
        header("Location: dashboard.php?page=profile&tab=security&error=password_too_short");
        exit();
    }
    
    try {
        // Get current password hash from database
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify current password
        if (!$user || !password_verify($current_pass, $user['password'])) {
            header("Location: dashboard.php?page=profile&tab=security&error=invalid_current_password");
            exit();
        }
        
        // Hash new password and update
        $hash = password_hash($new_pass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $current_user_id]);
        
        Auditor::log($pdo, $current_user_id, 'update', 'users', $current_user_id, ['action' => 'changed_password']);
        
        header("Location: dashboard.php?page=profile&tab=security&msg=pass_updated");
        exit();
    } catch (PDOException $e) { 
        ErrorLogger::error("Password change error: " . $e->getMessage());
        header("Location: dashboard.php?page=profile&tab=security&error=password_change_failed");
        exit();
    }
}

// =========================================================
// ACTION 4: ADD TEAM HISTORY
// =========================================================
if ($action == 'add_team') {
    $name  = trim($_POST['team_name'] ?? '');
    $league = trim($_POST['league'] ?? '');
    $season_id = intval($_POST['season_id'] ?? 0);
    $year  = trim($_POST['season_year'] ?? '');
    $type  = trim($_POST['season_type'] ?? '');
    $position = trim($_POST['team_position'] ?? '');
    $is_current = isset($_POST['is_current']) && $_POST['is_current'] == '1' ? 1 : 0;
    
    // If a season_id was provided (from typeahead), fetch the season name
    $season_display = '';
    if ($season_id > 0) {
        try {
            $seasonStmt = $pdo->prepare("SELECT name FROM seasons WHERE id = ?");
            $seasonStmt->execute([$season_id]);
            $seasonRow = $seasonStmt->fetch(PDO::FETCH_ASSOC);
            if ($seasonRow) {
                $season_display = $seasonRow['name'];
            }
        } catch (PDOException $e) {
            // Fallback to manual fields
        }
    }
    
    // Fallback to manual fields if no season found from typeahead
    if (empty($season_display)) {
        $season_parts = array_filter([$type, $year], function($v) { return !empty($v); });
        $season_display = implode(' ', $season_parts);
    }

    if (empty($name)) {
        header("Location: dashboard.php?page=profile&tab=player&error=team_name_required");
        exit();
    }

    try {
        // If setting as current, reset other current flags
        if ($is_current) {
            $pdo->prepare("UPDATE athlete_teams SET is_current = 0 WHERE user_id = ? OR athlete_id = ?")->execute([$current_user_id, $current_user_id]);
        }
        // Insert new team with position
        $stmt = $pdo->prepare("INSERT INTO athlete_teams (user_id, athlete_id, team_name, league, season_year, season_type, season, position, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$current_user_id, $current_user_id, $name, $league, $year, $type, $season_display, $position, $is_current]);
        
        header("Location: dashboard.php?page=profile&tab=player&msg=team_added");
        exit();
    } catch (PDOException $e) { 
        ErrorLogger::error("Add team error: " . $e->getMessage());
        header("Location: dashboard.php?page=profile&tab=player&error=team_add_failed");
        exit();
    }
}

// =========================================================
// ACTION 4B: REMOVE TEAM FROM HISTORY
// =========================================================
if ($action == 'remove_team') {
    $team_id = intval($_POST['team_id'] ?? 0);
    
    if ($team_id <= 0) {
        header("Location: dashboard.php?page=profile&tab=player&error=invalid_team");
        exit();
    }

    try {
        // Only allow removing own teams
        $stmt = $pdo->prepare("DELETE FROM athlete_teams WHERE id = ? AND (user_id = ? OR athlete_id = ?)");
        $stmt->execute([$team_id, $current_user_id, $current_user_id]);
        
        header("Location: dashboard.php?page=profile&tab=player&msg=team_removed");
        exit();
    } catch (PDOException $e) { 
        ErrorLogger::error("Remove team error: " . $e->getMessage());
        header("Location: dashboard.php?page=profile&tab=player&error=team_remove_failed");
        exit();
    }
}

// =========================================================
// ACTION 4C: ADD TEAM FROM ROSTER (Select from org teams)
// =========================================================
if ($action == 'add_team_from_roster') {
    $combo = $_POST['roster_team_season'] ?? '';
    $position = trim($_POST['roster_position'] ?? '');
    $is_current = isset($_POST['is_current']) && $_POST['is_current'] == '1' ? 1 : 0;
    
    $parts = explode('|', $combo);
    if (count($parts) !== 2 || empty($position)) {
        header("Location: dashboard.php?page=profile&tab=player&error=invalid_selection");
        exit();
    }
    
    $team_id = intval($parts[0]);
    $season_id = intval($parts[1] ?? 0);
    
    if ($team_id <= 0) {
        header("Location: dashboard.php?page=profile&tab=player&error=invalid_selection");
        exit();
    }
    
    try {
        // Fetch team and season info
        if ($season_id > 0) {
            $info_stmt = $pdo->prepare("
                SELECT t.name as team_name, s.name as season_name
                FROM teams t
                INNER JOIN team_seasons ts ON ts.team_id = t.id
                INNER JOIN seasons s ON ts.season_id = s.id
                WHERE t.id = ? AND s.id = ?
            ");
            $info_stmt->execute([$team_id, $season_id]);
        } else {
            $info_stmt = $pdo->prepare("
                SELECT t.name as team_name, '' as season_name
                FROM teams t
                WHERE t.id = ?
            ");
            $info_stmt->execute([$team_id]);
        }
        $info = $info_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$info) {
            header("Location: dashboard.php?page=profile&tab=player&error=team_not_found");
            exit();
        }
        
        // If setting as current, reset other current flags
        if ($is_current) {
            $pdo->prepare("UPDATE athlete_teams SET is_current = 0 WHERE user_id = ? OR athlete_id = ?")->execute([$current_user_id, $current_user_id]);
        }
        
        // Insert into athlete_teams with team_id reference
        $stmt = $pdo->prepare("INSERT INTO athlete_teams (user_id, athlete_id, team_id, team_name, season, position, is_current) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$current_user_id, $current_user_id, $team_id, $info['team_name'], $info['season_name'], $position, $is_current]);
        
        header("Location: dashboard.php?page=profile&tab=player&msg=team_added");
        exit();
    } catch (PDOException $e) {
        ErrorLogger::error("Add team from roster error: " . $e->getMessage());
        header("Location: dashboard.php?page=profile&tab=player&error=team_add_failed");
        exit();
    }
}

// =========================================================
// ACTION 5: FORCE PASSWORD RESET (Mandatory First Login)
// =========================================================
if ($action == 'force_password_reset') {
    $uid  = $_POST['user_id'];
    $pass = $_POST['new_password'];
    
    // Security: Ensure the user editing is the logged in user
    if ($uid != $current_user_id) { 
        die("Unauthorized access."); 
    }
    
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    
    try {
        // 1. Update the password
        // 2. Set force_pass_change to 0 (unlocks the account)
        $stmt = $pdo->prepare("UPDATE users SET password = ?, force_pass_change = 0 WHERE id = ?");
        $stmt->execute([$hash, $uid]);
        
        // Redirect to dashboard now that they are unlocked
        header("Location: dashboard.php");
        exit();
    } catch (PDOException $e) {
        ErrorLogger::error("Password reset error: " . $e->getMessage(), ['user_id' => $uid ?? '']);
        header("Location: dashboard.php?page=profile&error=reset_failed");
        exit();
    }
}

// =========================================================
// ACTION 6: UPDATE PROFILE (First Name, Last Name, Email, Phone, Birth Date, Position, Primary Arena)
// =========================================================
if ($action == 'update_profile') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $birth_date = $_POST['birth_date'] ?? null;
    $position = $_POST['position'] ?? null;
    $primary_arena = trim($_POST['primary_arena'] ?? '');
    
    try {
        // Encrypt PII fields before storing (email kept as-is for login lookups)
        $enc_first_name = FieldEncryption::encrypt($first_name);
        $enc_last_name = FieldEncryption::encrypt($last_name);
        $enc_phone = $phone ? FieldEncryption::encrypt($phone) : null;
        $enc_birth_date = $birth_date ? FieldEncryption::encrypt($birth_date) : null;

        // Get current user email to check if it's being changed
        $stmt = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
        $old_email = $currentUser['email'];
        
        // Check if email is being changed
        if ($new_email !== $old_email) {
            // Generate a verification token for email change
            $email_change_token = bin2hex(random_bytes(32));
            $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Store the pending email change
            $stmt = $pdo->prepare("
                INSERT INTO email_change_requests (user_id, old_email, new_email, token, expires_at) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE new_email = ?, token = ?, expires_at = ?
            ");
            $stmt->execute([
                $current_user_id, $old_email, $new_email, $email_change_token, $token_expiry,
                $new_email, $email_change_token, $token_expiry
            ]);
            
            // Send confirmation email to OLD email address
            require_once __DIR__ . '/mailer.php';
            
            // Build confirmation link securely - use SERVER_NAME (more reliable) or validate HTTP_HOST
            $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
            // Validate host is not malicious
            if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}$/', $host) && $host !== 'localhost') {
                $host = 'localhost';
            }
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $confirm_link = $protocol . "://" . $host . "/dashboard.php?page=confirm_email&token=" . $email_change_token;
            
            sendEmail($old_email, 'email_change_confirmation', [
                'name' => $first_name,
                'old_email' => $old_email,
                'new_email' => $new_email,
                'confirm_link' => $confirm_link
            ]);
            
            // Update other fields but NOT the email
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, phone = ?, 
                    birth_date = ?, position = ?, primary_arena = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $enc_first_name, $enc_last_name, $enc_phone, 
                $enc_birth_date, $position, $primary_arena, $current_user_id
            ]);
            
            Auditor::log($pdo, $current_user_id, 'update', 'users', $current_user_id, ['action' => 'updated_profile_email_change_pending']);
            
            header("Location: dashboard.php?page=profile&msg=email_change_pending");
            exit();
        } else {
            // Email not changed, update all fields normally
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                    birth_date = ?, position = ?, primary_arena = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $enc_first_name, $enc_last_name, $new_email, $enc_phone, 
                $enc_birth_date, $position, $primary_arena, $current_user_id
            ]);
            Auditor::log($pdo, $current_user_id, 'update', 'users', $current_user_id, ['action' => 'updated_profile']);
            header("Location: dashboard.php?page=profile&msg=profile_updated");
            exit();
        }
    } catch (PDOException $e) {
        ErrorLogger::error("Profile update error: " . $e->getMessage());
        header("Location: dashboard.php?page=profile&error=update_failed");
        exit();
    }
}

// =========================================================
// ACTION 7: UPDATE PLAYER INFO (Height, Weight, Handedness, Catching Hand, Jersey Number, Team, League)
// =========================================================
if ($action == 'update_player_info') {
    $height = $_POST['height'] ?? null;
    $weight = $_POST['weight'] ?? null;
    $handedness = $_POST['handedness'] ?? null;
    $catching_hand = $_POST['catching_hand'] ?? null;
    $jersey_number = $_POST['jersey_number'] ?? null;
    $team = trim($_POST['team'] ?? '');
    $league = trim($_POST['league'] ?? '');
    
    try {
        // Check if athlete_stats record exists for this user
        $stmt = $pdo->prepare("SELECT id FROM athlete_stats WHERE user_id = ? LIMIT 1");
        $stmt->execute([$current_user_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE athlete_stats 
                SET height = ?, weight = ?, handedness = ?, catching_hand = ?, jersey_number = ?, team = ?, league = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$height, $weight, $handedness, $catching_hand, $jersey_number, $team, $league, $current_user_id]);
        } else {
            // Create new record
            $stmt = $pdo->prepare("
                INSERT INTO athlete_stats (user_id, height, weight, handedness, catching_hand, jersey_number, team, league)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$current_user_id, $height, $weight, $handedness, $catching_hand, $jersey_number, $team, $league]);
        }
        
        header("Location: dashboard.php?page=profile&tab=player&msg=player_info_updated");
        exit();
    } catch (PDOException $e) {
        ErrorLogger::error("Player info update error: " . $e->getMessage());
        header("Location: dashboard.php?page=profile&tab=player&error=update_failed");
        exit();
    }
}

// =========================================================
// ACTION 7B: UPDATE PERFORMANCE STATS (Games, Goals, Assists, etc.)
// =========================================================
if ($action == 'update_performance_stats') {
    // Server-side validation with max(0, ...) to prevent negative values
    $games_played = max(0, min(9999, intval($_POST['games_played'] ?? 0)));
    $goals = max(0, min(9999, intval($_POST['goals'] ?? 0)));
    $assists = max(0, min(9999, intval($_POST['assists'] ?? 0)));
    $plus_minus = max(-999, min(999, intval($_POST['plus_minus'] ?? 0))); // +/- can be negative
    $shots = max(0, min(9999, intval($_POST['shots'] ?? 0)));
    $penalty_minutes = max(0, min(9999, intval($_POST['penalty_minutes'] ?? 0)));
    $goals_against = max(0, min(9999, intval($_POST['goals_against'] ?? 0)));
    $shots_against = max(0, min(9999, intval($_POST['shots_against'] ?? 0)));
    $saves = max(0, min(9999, intval($_POST['saves'] ?? 0)));
    
    // Calculate derived stats
    $points = $goals + $assists;
    
    // Calculate save percentage based on saves and shots_against
    // Using user-provided saves value directly for calculation
    $save_percentage = 0;
    if ($shots_against > 0) {
        // Use saves input directly: save% = (saves / shots_against) * 100
        $save_percentage = ($saves / $shots_against) * 100;
        $save_percentage = max(0, min(100, $save_percentage));
    }
    
    // Calculate GAA (Goals Against Average) if games_played > 0
    $gaa = 0;
    if ($games_played > 0) {
        $gaa = $goals_against / $games_played;
    }
    
    try {
        // Check if athlete_stats record exists for this user
        $stmt = $pdo->prepare("SELECT id FROM athlete_stats WHERE user_id = ? LIMIT 1");
        $stmt->execute([$current_user_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE athlete_stats 
                SET games_played = ?, goals = ?, assists = ?, points = ?, plus_minus = ?, 
                    shots = ?, penalty_minutes = ?, goals_against = ?, shots_against = ?, 
                    saves = ?, save_percentage = ?, gaa = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $games_played, $goals, $assists, $points, $plus_minus, 
                $shots, $penalty_minutes, $goals_against, $shots_against, 
                $saves, $save_percentage, $gaa, $current_user_id
            ]);
        } else {
            // Create new record
            $stmt = $pdo->prepare("
                INSERT INTO athlete_stats (user_id, games_played, goals, assists, points, plus_minus, 
                    shots, penalty_minutes, goals_against, shots_against, saves, save_percentage, gaa)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $current_user_id, $games_played, $goals, $assists, $points, $plus_minus, 
                $shots, $penalty_minutes, $goals_against, $shots_against, $saves, $save_percentage, $gaa
            ]);
        }
        
        header("Location: dashboard.php?page=profile&tab=player&msg=stats_updated");
        exit();
    } catch (PDOException $e) {
        ErrorLogger::error("Performance stats update error: " . $e->getMessage());
        header("Location: dashboard.php?page=profile&tab=player&error=stats_update_failed");
        exit();
    }
}

// =========================================================
// ACTION 8: UPLOAD PROFILE PHOTO
// =========================================================
if ($action == 'upload_photo') {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['profile_photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $profile_filename = "profile_" . $current_user_id . "_" . time() . "." . $ext;
            
            $persist = persistUploadedFile($pdo, $_FILES['profile_photo']['tmp_name'], 'profiles', $profile_filename);
            $db_path = $persist['rustfs_url'] ?? null;
            
            if ($persist['success']) {
                // Delete old profile image from RustFS
                $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                $stmt->execute([$current_user_id]);
                $old_image = $stmt->fetchColumn();
                if ($old_image && preg_match('#^https?://#', $old_image)) {
                    try {
                        $rustfs = getRustFSSettings($pdo);
                        if (isRustFSConfigured($rustfs)) {
                            $base_url = getRustFSBaseUrl($rustfs);
                            if (strpos($old_image, $base_url) === 0) {
                                $object_key = substr($old_image, strlen($base_url) + 1);
                                deleteFromRustFS($rustfs, $object_key);
                            }
                        }
                    } catch (Exception $delErr) {
                        error_log("RustFS delete old profile image: " . $delErr->getMessage());
                    }
                }
                
                // Update database with RustFS URL or relative path
                $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?")
                    ->execute([$db_path, $current_user_id]);
                
                // Store persistent path for recovery
                if (!empty($persist['nextcloud_path'])) {
                    $pdo->prepare("UPDATE users SET nextcloud_image_path = ? WHERE id = ?")->execute([$persist['nextcloud_path'], $current_user_id]);
                }
                
                header("Location: dashboard.php?page=profile&msg=photo_uploaded");
                exit();
            }
        }
    }
    header("Location: dashboard.php?page=profile&error=upload_failed");
    exit();
}

// =========================================================
// ACTION 9: REMOVE PROFILE PHOTO
// =========================================================
if ($action == 'remove_photo') {
    try {
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $old_image = $stmt->fetchColumn();
        
        if ($old_image && !preg_match('#^https?://#', $old_image) && file_exists($old_image)) {
            unlink($old_image);
        }
        
        $pdo->prepare("UPDATE users SET profile_image = NULL WHERE id = ?")
            ->execute([$current_user_id]);
        
        header("Location: dashboard.php?page=profile&msg=photo_removed");
        exit();
    } catch (PDOException $e) {
        ErrorLogger::error("Photo removal error: " . $e->getMessage());
        header("Location: dashboard.php?page=profile&error=removal_failed");
        exit();
    }
}

// =========================================================
// ACTION 10: UPDATE NOTIFICATION PREFERENCE (AJAX)
// =========================================================
if ($action == 'update_preference') {
    header('Content-Type: application/json');
    
    $preference = $_POST['preference'] ?? '';
    $value = intval($_POST['value'] ?? 0);
    
    // Validate preference name
    $allowed_prefs = ['email_notifications', 'session_reminders', 'goal_updates', 'marketing_emails'];
    if (!in_array($preference, $allowed_prefs)) {
        echo json_encode(['success' => false, 'message' => 'Invalid preference']);
        exit();
    }
    
    try {
        // Check if user_preferences table exists, create record if needed
        $stmt = $pdo->prepare("
            INSERT INTO user_preferences (user_id, preference_key, preference_value) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE preference_value = ?
        ");
        $stmt->execute([$current_user_id, $preference, $value, $value]);
        
        echo json_encode(['success' => true, 'message' => 'Preference saved']);
    } catch (PDOException $e) {
        ErrorLogger::error("Preference update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

// =========================================================
// ACTION 11: CONFIRM EMAIL CHANGE
// =========================================================
if ($action == 'confirm_email_change') {
    $token = trim($_POST['token'] ?? $_GET['token'] ?? '');
    
    if (empty($token)) {
        header("Location: dashboard.php?page=profile&error=invalid_or_expired_token");
        exit();
    }
    
    try {
        // Find the email change request
        $stmt = $pdo->prepare("
            SELECT * FROM email_change_requests 
            WHERE token = ? AND user_id = ? AND expires_at > NOW() AND confirmed_at IS NULL
        ");
        $stmt->execute([$token, $current_user_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            header("Location: dashboard.php?page=profile&error=invalid_or_expired_token");
            exit();
        }
        
        // Update the user's email
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$request['new_email'], $current_user_id]);
        
        // Mark the request as confirmed
        $stmt = $pdo->prepare("UPDATE email_change_requests SET confirmed_at = NOW() WHERE id = ?");
        $stmt->execute([$request['id']]);
        
        header("Location: dashboard.php?page=profile&msg=email_changed");
        exit();
    } catch (PDOException $e) {
        ErrorLogger::error("Email change confirmation error: " . $e->getMessage());
        header("Location: dashboard.php?page=profile&error=email_change_failed");
        exit();
    }
}

// =========================================================
// ACTION 12: SET/CHANGE PIN
// =========================================================
if ($action == 'update_pin') {
    header('Content-Type: application/json');
    
    $new_pin = $_POST['new_pin'] ?? '';
    $confirm_pin = $_POST['confirm_pin'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    
    // Validate PIN format (4 digits)
    if (!preg_match('/^\d{4}$/', $new_pin)) {
        echo json_encode(['success' => false, 'message' => 'PIN must be exactly 4 digits']);
        exit();
    }
    
    if ($new_pin !== $confirm_pin) {
        echo json_encode(['success' => false, 'message' => 'PINs do not match']);
        exit();
    }
    
    try {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($current_password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit();
        }
        
        // Hash the PIN
        $pinHash = password_hash($new_pin, PASSWORD_DEFAULT);
        
        // Insert or update PIN (compatible with older MySQL versions)
        $stmt = $pdo->prepare("
            INSERT INTO staff_pins (user_id, pin_hash, is_active) 
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE pin_hash = ?, is_active = 1
        ");
        $stmt->execute([$current_user_id, $pinHash, $pinHash]);
        
        echo json_encode(['success' => true, 'message' => 'PIN updated successfully']);
    } catch (PDOException $e) {
        ErrorLogger::error("PIN update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

// =========================================================
// ACTION 12B: GET OWN SIP PASSWORD (for auto-connect)
// =========================================================
if ($action == 'get_sip_password') {
    header('Content-Type: application/json');
    
    $staff_roles = ['admin', 'coach', 'health_coach', 'front_desk_staff', 'hr', 'accounting'];
    if (!in_array($role, $staff_roles)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT sip_password FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!empty($row['sip_password'])) {
            $decrypted = FieldEncryption::decrypt($row['sip_password']);
            echo json_encode(['success' => true, 'password' => $decrypted]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No saved password']);
        }
    } catch (PDOException $e) {
        ErrorLogger::error("Get SIP password error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error retrieving password']);
    }
    exit();
}

// =========================================================
// ACTION 13: UPDATE OWN SIP SETTINGS
// =========================================================
if ($action == 'update_own_sip') {
    header('Content-Type: application/json');
    
    // Staff-only: verify the user has a staff role
    $staff_roles = ['admin', 'coach', 'health_coach', 'front_desk_staff', 'hr', 'accounting'];
    if (!in_array($role, $staff_roles)) {
        echo json_encode(['success' => false, 'message' => 'SIP settings are only available to staff members']);
        exit();
    }
    
    $sip_username = trim($_POST['sip_username'] ?? '');
    $sip_domain = trim($_POST['sip_domain'] ?? '');
    $sip_password = $_POST['sip_password'] ?? '';
    $sip_extension = trim($_POST['sip_extension'] ?? '');
    $sip_did = trim($_POST['sip_did'] ?? '');
    $sip_wss_port = intval($_POST['sip_wss_port'] ?? 7443);
    if ($sip_wss_port < 1 || $sip_wss_port > 65535) { $sip_wss_port = 7443; }
    
    // Only encrypt and update password if user entered a new one
    $update_password = !empty($sip_password);
    $encrypted_password = $update_password ? FieldEncryption::encrypt($sip_password) : null;
    
    try {
        // Admins can update their own extension and DID
        if ($role === 'admin') {
            if ($update_password) {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET sip_username = ?, sip_domain = ?, sip_password = ?, sip_extension = ?, sip_did = ?, sip_wss_port = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $sip_username ?: null,
                    $sip_domain ?: null,
                    $encrypted_password,
                    $sip_extension ?: null,
                    $sip_did ?: null,
                    $sip_wss_port,
                    $current_user_id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET sip_username = ?, sip_domain = ?, sip_extension = ?, sip_did = ?, sip_wss_port = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $sip_username ?: null,
                    $sip_domain ?: null,
                    $sip_extension ?: null,
                    $sip_did ?: null,
                    $sip_wss_port,
                    $current_user_id
                ]);
            }
        } else {
            if ($update_password) {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET sip_username = ?, sip_domain = ?, sip_password = ?, sip_wss_port = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $sip_username ?: null,
                    $sip_domain ?: null,
                    $encrypted_password,
                    $sip_wss_port,
                    $current_user_id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET sip_username = ?, sip_domain = ?, sip_wss_port = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $sip_username ?: null,
                    $sip_domain ?: null,
                    $sip_wss_port,
                    $current_user_id
                ]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'SIP settings updated']);
    } catch (PDOException $e) {
        ErrorLogger::error("Update own SIP error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update SIP settings']);
    }
    exit();
}

// =========================================================
// ACTION 14: ADD PHONE DIRECTORY ENTRY (Admin only)
// =========================================================
if ($action == 'add_directory_entry') {
    header('Content-Type: application/json');
    
    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit();
    }
    
    $display_name = trim($_POST['display_name'] ?? '');
    $extension = trim($_POST['extension'] ?? '');
    $did = trim($_POST['did'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $entry_type = trim($_POST['entry_type'] ?? 'other');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($display_name)) {
        echo json_encode(['success' => false, 'message' => 'Name is required']);
        exit();
    }
    
    // Validate entry_type
    $valid_types = ['room', 'shared', 'external', 'other'];
    if (!in_array($entry_type, $valid_types)) {
        $entry_type = 'other';
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO phone_directory_entries (display_name, extension, did, email, entry_type, description, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $display_name,
            $extension ?: null,
            $did ?: null,
            $email ?: null,
            $entry_type,
            $description ?: null,
            $current_user_id
        ]);
        echo json_encode(['success' => true, 'message' => 'Directory entry added']);
    } catch (PDOException $e) {
        ErrorLogger::error("Add directory entry error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to add directory entry']);
    }
    exit();
}

// =========================================================
// ACTION 15: DELETE PHONE DIRECTORY ENTRY (Admin only)
// =========================================================
if ($action == 'delete_directory_entry') {
    header('Content-Type: application/json');
    
    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit();
    }
    
    $entry_id = intval($_POST['entry_id'] ?? 0);
    if ($entry_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM phone_directory_entries WHERE id = ?");
        $stmt->execute([$entry_id]);
        echo json_encode(['success' => true, 'message' => 'Directory entry removed']);
    } catch (PDOException $e) {
        ErrorLogger::error("Delete directory entry error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to remove directory entry']);
    }
    exit();
}

// Fallback
header("Location: dashboard.php");
exit();
?>