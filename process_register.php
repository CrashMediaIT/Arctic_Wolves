<?php
// process_register.php
session_start();
require 'db_config.php';
require 'mailer.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first = trim($_POST['first_name']);
    $last  = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $pos   = $_POST['position'];
    $dob   = $_POST['birth_date'];
    $pass  = $_POST['password'];
    
    // 1. CHECK DUPLICATE
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        header("Location: register.php?error=email_taken");
        exit();
    }

    $hash_pass = password_hash($pass, PASSWORD_BCRYPT);
    $verify_code = rand(100000, 999999);

    try {
        // 2. INSERT USER -> is_verified = 0 (Requires Code)
        $sql = "INSERT INTO users (first_name, last_name, email, password, role, position, birth_date, is_verified, verification_code) 
                VALUES (?, ?, ?, ?, 'athlete', ?, ?, 0, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$first, $last, $email, $hash_pass, $pos, $dob, $verify_code]);

        // 3. SEND EMAIL
        sendEmail($email, 'verification', [
            'name' => $first,
            'code' => $verify_code
        ]);

        // 4. REDIRECT TO VERIFY PAGE
        header("Location: verify.php");
        exit();

    } catch (PDOException $e) {
        die("Registration error: " . $e->getMessage());
    }
}
?>