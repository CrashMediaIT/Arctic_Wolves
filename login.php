<?php
session_start();
require 'db_config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$show_verify_link = false; // Flag to show the "Enter Code" button

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];
    
    // Fetch User
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // Verify Password
    if ($user && password_verify($pass, $user['password'])) {
        
        // 1. CHECK VERIFICATION STATUS
        if ($user['is_verified'] == 0) {
            $error = "Account pending verification.";
            $show_verify_link = true; // Trigger the verification button
        } else {
            // 2. LOGIN SUCCESS - SET SESSION
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['first_name'];
            $_SESSION['user_email'] = $user['email']; // Useful for test emails
            
            // 3. CHECK FORCE PASSWORD CHANGE (Coach-created accounts)
            if (isset($user['force_pass_change']) && $user['force_pass_change'] == 1) {
                header("Location: force_change_password.php");
                exit();
            }
            
            // 4. REDIRECT TO DASHBOARD
            header("Location: dashboard.php");
            exit();
        }
    } else {
        $error = "Invalid email address or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Crash Hockey</title>
    
    <link rel="icon" type="image/png" href="https://images.crashmedia.ca/images/2026/01/18/logo.png">
    
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body { 
            margin: 0; 
            height: 100vh; 
            display: flex; 
            background: #06080b; 
            font-family: 'Inter', sans-serif; 
            overflow: hidden; 
        }

        /* LEFT SIDE: HERO / BRANDING */
        .split-left {
            flex: 1.2;
            background: linear-gradient(135deg, rgba(107, 70, 193, 0.1), rgba(6, 8, 11, 0.9)), url('https://images.unsplash.com/photo-1580748141549-71748dbe0bdc?q=80&w=2574&auto=format&fit=crop'); 
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            padding: 40px;
            color: #fff;
        }
        
        .split-left::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }

        .brand-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .brand-content h1 {
            font-size: 3rem;
            font-weight: 900;
            margin: 10px 0;
            letter-spacing: -1px;
        }
        
        .brand-content p {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* RIGHT SIDE: LOGIN FORM */
        .split-right {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #06080b;
            padding: 40px;
            position: relative;
        }

        .login-card {
            width: 100%;
            max-width: 380px;
        }

        .input-box {
            background: #0d1116;
            border: 1px solid #1e293b;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 15px;
            transition: 0.2s;
        }
        
        .input-box:focus-within {
            border-color: var(--neon);
            box-shadow: 0 0 0 2px rgba(107, 70, 193, 0.1);
        }

        .input-box label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 5px;
        }

        .input-box input {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            outline: none;
            font-size: 14px;
        }

        /* MOBILE RESPONSIVENESS */
        @media (max-width: 900px) {
            .split-left { display: none; }
            .split-right { flex: 1; padding: 20px; }
        }
    </style>
</head>
<body>

    <div class="split-left">
        <div class="brand-content">
            <img src="https://images.crashmedia.ca/images/2026/01/18/logo.png" alt="Logo" style="height: 80px; margin-bottom: 20px;">
            <h1>CRASH <span style="color: var(--neon);">HOCKEY</span></h1>
            <p>Elevate your game. Track your progress. Dominate the ice.</p>
        </div>
        
        <div style="position: absolute; bottom: 30px; z-index: 2; font-size: 12px; color: rgba(255,255,255,0.4);">
            &copy; <?php echo date('Y'); ?> Crash Hockey Performance.
        </div>
    </div>

    <div class="split-right">
        <div class="login-card">
            
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="font-size: 24px; color: #fff; margin-bottom: 5px;">Welcome Back</h2>
                <p style="color: #64748b; font-size: 14px; margin: 0;">Please enter your details to sign in.</p>
            </div>

            <?php if($error && !$show_verify_link): ?>
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 12px; border-radius: 6px; font-size: 13px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if($show_verify_link): ?>
                <div style="background: rgba(107, 70, 193, 0.1); border: 1px solid var(--neon); color: var(--neon); padding: 20px; border-radius: 6px; font-size: 13px; margin-bottom: 25px; text-align: center;">
                    <i class="fa-solid fa-lock" style="font-size: 20px; margin-bottom: 10px; display: block;"></i>
                    <strong style="font-size: 14px; display: block; margin-bottom: 5px;">Account Not Verified</strong>
                    <span style="color: rgba(255,255,255,0.7);">We sent a code to your email.</span>
                    
                    <a href="verify.php" style="display: block; margin-top: 15px; background: var(--neon); color: #000; text-decoration: none; padding: 10px; font-weight: bold; border-radius: 4px;">Enter Code Now</a>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['success_msg'])): ?>
                <div style="background: rgba(0, 255, 136, 0.1); border: 1px solid #00ff88; color: #00ff88; padding: 12px; border-radius: 6px; font-size: 13px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-check-circle"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                
                <div class="input-box">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="name@example.com">
                </div>

                <div class="input-box">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <label style="color: #94a3b8; font-size: 13px; display: flex; align-items: center; gap: 5px; cursor: pointer;">
                        <input type="checkbox" style="accent-color: var(--neon);"> Remember me
                    </label>
                    <a href="forgot_password.php" style="color: var(--neon); font-size: 13px; text-decoration: none; font-weight: 600;">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; padding: 14px; font-size: 14px; border: none; cursor: pointer; border-radius: 6px; font-weight: 700; letter-spacing: 0.5px;">SIGN IN</button>
            
            </form>

            <div style="margin-top: 30px; text-align: center; font-size: 13px; color: #64748b;">
                Don't have an account? <a href="register.php" style="color: #fff; text-decoration: none; font-weight: 700;">Join the Team</a>
            </div>

        </div>
    </div>

</body>
</html>