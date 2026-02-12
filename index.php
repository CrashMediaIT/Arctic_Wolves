<?php
// Check if database is configured and connected
require_once __DIR__ . '/db_config.php';

// Detect POS subdomain (pos.arcticwolves.ca)
// Strict validation: must end with arcticwolves.ca
$host = $_SERVER['HTTP_HOST'] ?? '';
$isPosSubdomain = (
    strpos($host, 'pos.') === 0 && 
    (preg_match('/^pos\.arcticwolves\.ca$/i', $host) || preg_match('/^pos\..*\.arcticwolves\.ca$/i', $host))
);

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

// PWA: detect mobile phones and redirect to PWA view
require_once __DIR__ . '/pwa_detect.php';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Logged-in: redirect mobile phones to PWA, others to desktop dashboard
    redirectToPwaIfMobile('pwa.php');
    header("Location: dashboard.php");
    exit();
} else {
    // Not logged in: redirect mobile phones to PWA login
    redirectToPwaIfMobile('pwa_login.php');
    // Show the marketing page for non-logged-in users
    include __DIR__ . '/index_default.php';
    exit();
}
?>