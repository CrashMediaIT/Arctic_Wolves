<?php
// ── Catch-all: route /api/ requests to the REST API entry point ────────
// nginx should have a `location /api/` block that routes directly to
// api/index.php.  If that block is missing, inactive, or the request
// otherwise falls through to this file, the main site would serve an
// HTML page instead of a JSON response — breaking companion callbacks
// and any other API caller.  This guard ensures /api/ requests always
// reach the API handler regardless of web-server configuration.
$_api_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
if (strncmp($_api_path, '/api/', 5) === 0) {
    require __DIR__ . '/api/index.php';
    exit;
}
unset($_api_path);

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

// Detect Scoreboard subdomain (scoreboard.arcticwolves.ca)
$isScoreboardSubdomain = (
    strpos($host, 'scoreboard.') === 0 &&
    (preg_match('/^scoreboard\.arcticwolves\.ca$/i', $host) || preg_match('/^scoreboard\..*\.arcticwolves\.ca$/i', $host))
);

// If on Scoreboard subdomain, redirect to scoreboard
if ($isScoreboardSubdomain) {
    header("Location: scoreboard.php");
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
require_once __DIR__ . '/config/session.php';
session_start();

// PWA: detect mobile phones and redirect to PWA view
require_once __DIR__ . '/pwa_detect.php';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Logged-in: redirect phones to PWA, tablets to tablet PWA, desktops to dashboard
    redirectToPwaIfMobile('pwa.php', 'pwa_tablet.php');
    header("Location: dashboard.php");
    exit();
} else {
    // Not logged in: show the marketing/landing page for all devices
    // Users must click "Athlete Login" to access the PWA login
    include __DIR__ . '/index_default.php';
    exit();
}
?>