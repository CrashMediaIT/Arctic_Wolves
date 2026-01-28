<?php
// Check if database is configured and connected
require_once __DIR__ . '/db_config.php';

// Detect POS subdomain (pos.arcticwolves.ca)
$host = $_SERVER['HTTP_HOST'] ?? '';
$isPosSubdomain = (strpos($host, 'pos.') === 0);

// If on POS subdomain, redirect to POS kiosk
if ($isPosSubdomain) {
    header("Location: pos_kiosk.php");
    exit();
}

// If database is not connected, check if setup is needed
if (!isset($db_connected) || !$db_connected) {
    // Check if setup has been completed
    $setup_complete_file = __DIR__ . '/.setup_complete';
    if (!file_exists($setup_complete_file)) {
        // Setup not completed, redirect to setup
        header("Location: setup.php");
        exit();
    }
    // Setup completed but DB connection failed - show marketing page with error
    include __DIR__ . '/index_default.php';
    exit();
}

// If database is connected, check if system is set up
session_start();
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: dashboard.php");
    exit();
} else {
    // Show the marketing page for non-logged-in users
    include __DIR__ . '/index_default.php';
    exit();
}
?>