<?php
// verify.php
session_start();
require 'db_config.php';

$msg = "";
$msg_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $code  = trim($_POST['code']);
    
    // Check if code matches user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND verification_code = ?");
    $stmt->execute([$email, $code]);
    
    if ($stmt->rowCount() > 0) {
        // Success: Mark Verified & Clear Code
        $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE email = ?")->execute([$email]);
        $_SESSION['success_msg'] = "Account verified successfully! You can now login.";
        header("Location: login.php");
        exit();
    } else {
        $msg = "Invalid verification code or email.";
        $msg_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Verify Account | Crash Hockey</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body style="display:flex; justify-content:center; align-items:center; height:100vh; background:#06080b;">
    <div class="stat-card" style="width:100%; max-width:400px; text-align:center;">
        <i class="fa-solid fa-envelope-circle-check" style="font-size: 40px; color: var(--neon); margin-bottom: 15px;"></i>
        <h2 style="color: #fff; margin-bottom: 10px;">Verify Your Account</h2>
        <p style="color: var(--text-dim); margin-bottom: 25px; font-size: 14px;">Enter the code sent to your email to activate your account.</p>
        
        <?php if($msg): ?>
            <div style="color: #ef4444; margin-bottom: 20px; font-weight: bold; background: rgba(239, 68, 68, 0.1); padding: 10px; border-radius: 4px;">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="email" name="email" placeholder="Confirm your Email" required style="width:100%; padding:12px; margin-bottom:10px; background:#06080b; border:1px solid var(--border); color:#fff; border-radius: 4px;">
            
            <input type="text" name="code" placeholder="123456" maxlength="6" required style="width:100%; padding:15px; margin-bottom:20px; background:#06080b; border:1px solid var(--neon); color:#fff; text-align:center; letter-spacing:8px; font-size:20px; font-weight: 800; border-radius: 4px;">
            
            <button type="submit" class="btn-primary" style="width:100%; padding:14px; border:none; cursor:pointer; font-weight: bold;">Verify Account</button>
        </form>
        
        <div style="margin-top: 20px;">
            <a href="login.php" style="color: var(--text-dim); font-size: 12px;">Back to Login</a>
        </div>
    </div>
</body>
</html>