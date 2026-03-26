<?php
session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Security: Coaches Only
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'coach')) {
    header("Location: dashboard.php"); exit();
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
}

$coach_id = $_SESSION['user_id'];
$user_id  = $_POST['user_id']; // The athlete
$action   = $_POST['action'];

if ($action == 'add_note') {
    $content = $_POST['note_content'];
    $private = isset($_POST['is_private']) ? 1 : 0;
    
    $stmt = $pdo->prepare("INSERT INTO athlete_notes (user_id, coach_id, note_content, is_private) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $coach_id, $content, $private]);
    Auditor::log($pdo, $coach_id, 'create', 'athlete_notes', $pdo->lastInsertId(), ['action' => 'Note added for athlete', 'athlete_id' => $user_id]);
}

if ($action == 'assign_workout') {
    $title = $_POST['title'];
    $desc  = $_POST['description'];
    $link  = $_POST['link'];
    
    $stmt = $pdo->prepare("INSERT INTO workouts (user_id, coach_id, title, description, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $coach_id, $title, $desc, $link]);
    Auditor::log($pdo, $coach_id, 'create', 'workouts', $pdo->lastInsertId(), ['action' => 'Workout assigned', 'athlete_id' => $user_id]);
}

if ($action == 'assign_nutrition') {
    $title   = $_POST['title'];
    $content = $_POST['content'];
    
    $stmt = $pdo->prepare("INSERT INTO nutrition_plans (user_id, coach_id, title, content) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $coach_id, $title, $content]);
    Auditor::log($pdo, $coach_id, 'create', 'nutrition_plans', $pdo->lastInsertId(), ['action' => 'Nutrition plan assigned', 'athlete_id' => $user_id]);
}

// Redirect back to that specific athlete's page (or custom redirect)
$redirect_page = 'athlete_detail&id=' . $user_id;
if (isset($_POST['redirect'])) {
    // Only allow known page values to prevent open redirect
    $allowed_redirects = [
        'athlete_detail', 'coaches_reviews', 'manage_athletes',
        'coach_roster', 'athlete_workouts', 'athlete_nutrition',
    ];
    $requested = $_POST['redirect'];
    // Extract the page name (before any & parameters)
    $page_name = explode('&', $requested)[0];
    if (in_array($page_name, $allowed_redirects, true) && preg_match('/^[a-zA-Z0-9_&=]+$/', $requested)) {
        $redirect_page = $requested;
    }
}
header("Location: dashboard.php?page=" . $redirect_page . "&success=note_added&msg=success");
?>