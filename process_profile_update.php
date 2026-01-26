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
                $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?")->execute([$new_name, $target_id]);
                
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
    $raw_pass = $_POST['password'];
    $hash = password_hash($raw_pass, PASSWORD_BCRYPT);
    
    try {
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $current_user_id]);
        header("Location: dashboard.php?page=profile&msg=pass_updated");
        exit();
    } catch (PDOException $e) { die("Error."); }
}

// =========================================================
// ACTION 4: ADD TEAM HISTORY
// =========================================================
if ($action == 'add_team') {
    $name  = trim($_POST['team_name'] ?? '');
    $league = trim($_POST['league'] ?? '');
    $year  = trim($_POST['season_year'] ?? '');
    $type  = trim($_POST['season_type'] ?? '');
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
        // Insert new team
        $stmt = $pdo->prepare("INSERT INTO athlete_teams (user_id, athlete_id, team_name, league, season_year, season_type, season, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$current_user_id, $current_user_id, $name, $league, $year, $type, $season_display, $is_current]);
        
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
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $birth_date = $_POST['birth_date'] ?? null;
    $position = $_POST['position'] ?? null;
    $primary_arena = trim($_POST['primary_arena'] ?? '');
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                birth_date = ?, position = ?, primary_arena = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $first_name, $last_name, $email, $phone, 
            $birth_date, $position, $primary_arena, $current_user_id
        ]);
        header("Location: dashboard.php?page=profile&msg=profile_updated");
        exit();
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

// Fallback
header("Location: dashboard.php");
exit();
?>