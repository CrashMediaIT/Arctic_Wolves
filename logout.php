<?php
/**
 * Logout Handler
 * Handles normal logout and kiosk mode logout with shift preservation
 */
session_start();

// Check if this is a "keep shift" logout (for POS system restarts)
$keepShift = isset($_GET['keep_shift']) && $_GET['keep_shift'] == '1';
$wasKioskMode = isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'];

// Clear session
session_unset();
session_destroy();

// Redirect appropriately
if ($wasKioskMode || $keepShift) {
    // If was in kiosk mode, redirect back to kiosk login
    header("Location: pos_kiosk.php");
} else {
    // Normal logout goes to index/login
    header("Location: login.php");
}
exit();
?>