<?php
// ENABLE DEBUGGING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db_config.php';
require 'security.php';

// Generate CSRF token for this session
generateCSRFToken();

echo "<h2>Login Debugger</h2>";

// Warning message
echo "<div style='background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 10px 0;'>";
echo "<strong>⚠️ Warning:</strong> This is a debug tool. Disable in production.";
echo "</div>";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("<span style='color:red'>Error: Invalid CSRF token. Please refresh and try again.</span>");
    }
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    echo "Attempting login for: <strong>$email</strong><br>";

    if (empty($email) || empty($password)) {
        die("Error: Email or Password was empty.");
    }

    try {
        // 1. Verify Database Selection
        // This checks if we are actually connected to 'arctic_wolves'
        $stmt = $pdo->query("SELECT DATABASE()");
        $current_db = $stmt->fetchColumn();
        echo "Connected to database: <strong>$current_db</strong><br>";

        if ($current_db != 'arctic_wolves') {
            die("<span style='color:red'>CRITICAL ERROR: Connected to '$current_db' but expected 'arctic_wolves'. Check your .env file.</span>");
        }

        // 2. Attempt the Query
        echo "Querying 'users' table...<br>";
        $sql = "SELECT id, first_name, last_name, password, role FROM users WHERE email = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            echo "User found! Verifying password hash...<br>";
            if (password_verify($password, $user['password'])) {
                echo "<span style='color:green'>SUCCESS: Password matches. Redirecting...</span>";
                
                // Actual Login Logic
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                header("Location: dashboard.php");
                exit();
            } else {
                echo "<span style='color:red'>FAILURE: Password did not match hash.</span><br>";
                echo "Hash in DB starts with: " . substr($user['password'], 0, 10) . "...";
            }
        } else {
            echo "<span style='color:orange'>FAILURE: No user found with that email.</span>";
        }

    } catch (Exception $e) {
        // THIS IS THE REAL ERROR MESSAGE YOU NEED
        echo "<hr><h3 style='color:red'>FATAL DATABASE ERROR:</h3>";
        echo "<strong>" . $e->getMessage() . "</strong>";
        echo "<hr>";
        exit();
    }
}
?>