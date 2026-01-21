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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first = trim($_POST['first_name']);
    $last  = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $pos   = $_POST['position'];
    $dob   = $_POST['birth_date'];
    
    // Auto-generate a random password if one wasn't provided, or use the input
    $raw_pass = !empty($_POST['password']) ? $_POST['password'] : substr(str_shuffle('abcdefhkmnrstuvwxyz23456789'), 0, 8);
    $hash_pass = password_hash($raw_pass, PASSWORD_BCRYPT);

    // 2. CHECK DUPLICATE
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        header("Location: dashboard.php?page=athletes&error=email_taken");
        exit();
    }

    try {
        // 3. INSERT USER
        // is_verified = 1 (Instant Access)
        // force_pass_change = 1 (Must change password immediately)
        $sql = "INSERT INTO users (first_name, last_name, email, password, role, position, birth_date, is_verified, force_pass_change) 
                VALUES (?, ?, ?, ?, 'athlete', ?, ?, 1, 1)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$first, $last, $email, $hash_pass, $pos, $dob]);

        // 4. SEND EMAIL (Now Working!)
        sendEmail($email, 'manual_welcome', [
            'name' => $first,
            'email' => $email,
            'password' => $raw_pass
        ]);

        header("Location: dashboard.php?page=athletes&status=created");
        exit();

    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}
?>