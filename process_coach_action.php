<?php
session_start();
require 'db_config.php';

// Security: Coaches Only
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'coach')) {
    header("Location: dashboard.php"); exit();
}

$coach_id = $_SESSION['user_id'];
$user_id  = $_POST['user_id']; // The athlete
$action   = $_POST['action'];

if ($action == 'add_note') {
    $content = $_POST['note_content'];
    $private = isset($_POST['is_private']) ? 1 : 0;
    
    $stmt = $pdo->prepare("INSERT INTO athlete_notes (user_id, coach_id, note_content, is_private) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $coach_id, $content, $private]);
}

if ($action == 'assign_workout') {
    $title = $_POST['title'];
    $desc  = $_POST['description'];
    $link  = $_POST['link'];
    
    $stmt = $pdo->prepare("INSERT INTO workouts (user_id, coach_id, title, description, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $coach_id, $title, $desc, $link]);
}

if ($action == 'assign_nutrition') {
    $title   = $_POST['title'];
    $content = $_POST['content'];
    
    $stmt = $pdo->prepare("INSERT INTO nutrition_plans (user_id, coach_id, title, content) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $coach_id, $title, $content]);
}

// Redirect back to that specific athlete's page
header("Location: dashboard.php?page=athlete_detail&id=" . $user_id . "&msg=success");
?>