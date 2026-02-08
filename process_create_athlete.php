<?php
// process_create_athlete.php
session_start();
require 'db_config.php';
require 'security.php';
require 'mailer.php';

// 1. SECURITY: Only Coach, Coach Plus, Health Coach, Team Coach, or Admin can run this
$coach_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $coach_roles)) {
    header("Location: dashboard.php"); 
    exit();
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
}

$coach_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Determine if this is from health coach roster - whitelist the valid redirect pages
$assign_to_health_coach = isset($_POST['assign_to_health_coach']) && $_POST['assign_to_health_coach'] === '1';
$redirect_page = $assign_to_health_coach ? 'health_coach_roster' : 'roster';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first = trim($_POST['first_name']);
    $last  = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $pos   = $_POST['position'] ?? null;
    $dob   = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    
    // Auto-generate a random password if one wasn't provided, or use the input
    $raw_pass = !empty($_POST['password']) ? $_POST['password'] : substr(str_shuffle('abcdefhkmnrstuvwxyz23456789'), 0, 8);
    $hash_pass = password_hash($raw_pass, PASSWORD_BCRYPT);

    // 2. CHECK DUPLICATE
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        header("Location: dashboard.php?page={$redirect_page}&error=email_taken");
        exit();
    }

    try {
        $pdo->beginTransaction();
        
        // 3. INSERT USER
        // is_verified = 1 (Instant Access)
        // force_pass_change = 1 (Must change password immediately)
        // is_active = 1 (Active immediately so they appear in rosters)
        // Set assigned_coach_id and created_by_coach_id for all coach types so athletes show in "My Athletes"
        if (in_array($user_role, ['coach', 'coach_plus', 'health_coach', 'team_coach']) || ($user_role === 'admin' && $assign_to_health_coach)) {
            $sql = "INSERT INTO users (first_name, last_name, email, password, role, position, birth_date, is_verified, force_pass_change, is_active, assigned_coach_id, created_by_coach_id) 
                    VALUES (?, ?, ?, ?, 'athlete', ?, ?, 1, 1, 1, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$first, $last, $email, $hash_pass, $pos, $dob, $coach_id, $coach_id]);
        } else {
            // Admin creating without health coach assignment
            $sql = "INSERT INTO users (first_name, last_name, email, password, role, position, birth_date, is_verified, force_pass_change, is_active) 
                    VALUES (?, ?, ?, ?, 'athlete', ?, ?, 1, 1, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$first, $last, $email, $hash_pass, $pos, $dob]);
        }
        
        $athlete_id = $pdo->lastInsertId();
        
        // 4. ASSIGN ATHLETE TO COACH ROSTER (for all coach roles)
        // Note: managed_athletes is for coach rosters, parent_athlete_relationships is for parents
        if (in_array($user_role, ['coach', 'coach_plus', 'health_coach', 'team_coach'])) {
            $assign_stmt = $pdo->prepare("
                INSERT INTO managed_athletes (coach_id, athlete_id, start_date, status) 
                VALUES (?, ?, CURDATE(), 'active')
            ");
            $assign_stmt->execute([$coach_id, $athlete_id]);
        }
        
        $pdo->commit();

        // 5. SEND EMAIL (Now Working!)
        try {
            sendEmail($email, 'manual_welcome', [
                'name' => $first,
                'email' => $email,
                'password' => $raw_pass
            ]);
        } catch (Exception $e) {
            error_log("Failed to send welcome email to {$email}: " . $e->getMessage());
            // Continue anyway - user is created
        }

        // Redirect back to appropriate roster page with success message
        header("Location: dashboard.php?page={$redirect_page}&status=athlete_created");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Athlete creation error: " . $e->getMessage());
        header("Location: dashboard.php?page={$redirect_page}&error=creation_failed");
        exit();
    }
}
?>