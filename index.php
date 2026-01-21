<?php
// Check if database is configured and connected
require_once __DIR__ . '/db_config.php';

// If database is not connected, show the default marketing page
if (!isset($db_connected) || !$db_connected) {
    include __DIR__ . '/index_default.php';
    exit();
}

// If database is connected, check if system is set up
// You can add additional checks here for setup completion
// For now, redirect to login or dashboard
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