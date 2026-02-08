<?php
/**
 * Logout Handler
 * Handles normal logout and kiosk mode logout with shift preservation
 * Supports POS subdomain redirect
 */
session_start();

// Check if this is a "keep shift" logout (for POS system restarts)
$keepShift = isset($_GET['keep_shift']) && $_GET['keep_shift'] == '1';
$wasKioskMode = isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'];
$wasPosSubdomain = isset($_SESSION['pos_subdomain']) && $_SESSION['pos_subdomain'];

// Record logout time in login_history
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/db_config.php';
        if ($db_connected && $pdo !== null) {
            $stmt = $pdo->prepare("
                UPDATE login_history 
                SET logout_time = NOW() 
                WHERE user_id = ? AND logout_time IS NULL 
                ORDER BY login_time DESC LIMIT 1
            ");
            $stmt->execute([$_SESSION['user_id']]);
        }
    } catch (Exception $e) {
        error_log("Failed to record logout time: " . $e->getMessage());
    }
}

// Clear session
session_unset();
session_destroy();

// Redirect appropriately
if ($wasPosSubdomain || $wasKioskMode || $keepShift) {
    // If was in kiosk mode or POS subdomain, redirect back to kiosk login
    header("Location: pos_kiosk.php");
} else {
    // Normal logout goes to index/login
    header("Location: login.php");
}
exit();
?>