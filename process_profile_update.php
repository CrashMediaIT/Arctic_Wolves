<?php
session_start();
require 'db_config.php';
require 'security.php';

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
            if (!is_dir('uploads')) { mkdir('uploads'); }
            $new_name = "uploads/avatar_" . $target_id . "_" . time() . "." . $ext;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $new_name)) {
                $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute([$new_name, $target_id]);
                
                // Redirect logic
                if ($target_id == $current_user_id) {
                    header("Location: dashboard.php?page=profile&msg=avatar_updated");
                } else {
                    header("Location: dashboard.php?page=athlete_detail&id=$target_id&msg=avatar_updated");
                }
                exit();
            }
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
        header("Location: dashboard.php?page=profile&msg=updated");
        exit();
    } catch (PDOException $e) {
        die("Error updating profile.");
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
        
        header("Location: dashboard.php?page=profile&tab=security&msg=pass_updated");
        exit();
    } catch (PDOException $e) { 
        error_log("Password change error: " . $e->getMessage());
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
    $year  = trim($_POST['season_year'] ?? '');
    $type  = trim($_POST['season_type'] ?? '');
    $position = trim($_POST['team_position'] ?? '');
    $is_current = isset($_POST['is_current']) && $_POST['is_current'] == '1' ? 1 : 0;
    
    // Build season display string, handling empty values properly
    $season_parts = array_filter([$type, $year], function($v) { return !empty($v); });
    $season_display = implode(' ', $season_parts);

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
        error_log("Add team error: " . $e->getMessage());
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
        error_log("Remove team error: " . $e->getMessage());
        header("Location: dashboard.php?page=profile&tab=player&error=team_remove_failed");
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
        die("Error processing reset: " . $e->getMessage());
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
                $first_name, $last_name, $phone, 
                $birth_date, $position, $primary_arena, $current_user_id
            ]);
            
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
                $first_name, $last_name, $new_email, $phone, 
                $birth_date, $position, $primary_arena, $current_user_id
            ]);
            header("Location: dashboard.php?page=profile&msg=profile_updated");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
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
        error_log("Player info update error: " . $e->getMessage());
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
        error_log("Performance stats update error: " . $e->getMessage());
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
            $upload_dir = 'uploads/profiles/';
            if (!is_dir($upload_dir)) { 
                mkdir($upload_dir, 0755, true); 
            }
            $new_name = $upload_dir . "profile_" . $current_user_id . "_" . time() . "." . $ext;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $new_name)) {
                // Delete old profile image if exists
                $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                $stmt->execute([$current_user_id]);
                $old_image = $stmt->fetchColumn();
                if ($old_image && file_exists($old_image)) {
                    unlink($old_image);
                }
                
                // Update database
                $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?")
                    ->execute([$new_name, $current_user_id]);
                
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
        
        if ($old_image && file_exists($old_image)) {
            unlink($old_image);
        }
        
        $pdo->prepare("UPDATE users SET profile_image = NULL WHERE id = ?")
            ->execute([$current_user_id]);
        
        header("Location: dashboard.php?page=profile&msg=photo_removed");
        exit();
    } catch (PDOException $e) {
        error_log("Photo removal error: " . $e->getMessage());
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
        error_log("Preference update error: " . $e->getMessage());
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
        error_log("Email change confirmation error: " . $e->getMessage());
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
        error_log("PIN update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

// Fallback
header("Location: dashboard.php");
exit();
?>