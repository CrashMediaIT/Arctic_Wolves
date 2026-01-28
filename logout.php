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