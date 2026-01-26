<?php
// process_create_athlete.php
session_start();
require 'db_config.php';
require 'mailer.php';

// 1. SECURITY: Only Coach or Admin can run this
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'coach')) {
    header("Location: dashboard.php"); 
    exit();
}

$coach_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

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
        header("Location: dashboard.php?page=roster&error=email_taken");
        exit();
    }

    try {
        $pdo->beginTransaction();
        
        // 3. INSERT USER
        // is_verified = 1 (Instant Access)
        // force_pass_change = 1 (Must change password immediately)
        $sql = "INSERT INTO users (first_name, last_name, email, password, role, position, birth_date, is_verified, force_pass_change) 
                VALUES (?, ?, ?, ?, 'athlete', ?, ?, 1, 1)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$first, $last, $email, $hash_pass, $pos, $dob]);
        
        $athlete_id = $pdo->lastInsertId();
        
        // 4. ASSIGN ATHLETE TO COACH (if coach is creating the athlete)
        if ($user_role === 'coach') {
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

        // Redirect back to roster page with success message
        header("Location: dashboard.php?page=roster&status=athlete_created");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Athlete creation error: " . $e->getMessage());
        header("Location: dashboard.php?page=roster&error=creation_failed");
        exit();
    }
}
?>