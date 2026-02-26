<?php
/**
 * Process Stats Goal Creation
 * Handles goal creation from the stats page and redirects back to stats
 */

session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Set security headers
setSecurityHeaders();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
}

$action = $_POST['action'] ?? '';

if ($action === 'create_goal') {
    try {
        $athlete_id = intval($_POST['athlete_id'] ?? $user_id);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $target_date = !empty($_POST['target_date']) ? $_POST['target_date'] : null;
        $return_page = $_POST['return_page'] ?? 'stats';
        
        if (empty($title)) {
            header("Location: dashboard.php?page={$return_page}&error=title_required");
            exit();
        }
        
        // Insert the goal
        // Note: Database has both title/goal_title and description/goal_description columns
        // for backward compatibility. Setting both ensures the goal displays correctly
        // regardless of which column the view queries use (via COALESCE).
        $stmt = $pdo->prepare("
            INSERT INTO goals (
                athlete_id, created_by, title, goal_title, description, goal_description, category,
                target_date, status, completion_percentage, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 0, NOW(), NOW())
        ");
        $stmt->execute([
            $athlete_id, $user_id, $title, $title, $description, $description, $category, $target_date
        ]);
        
        $goal_id = $pdo->lastInsertId();
        
        Auditor::log($pdo, $user_id, 'create', 'goals', $goal_id, ['action' => 'Goal created from stats page']);
        
        // Log the goal creation
        if (function_exists('logSecurityEvent')) {
            logSecurityEvent('goal_created', "Goal ID: $goal_id created from stats page", $user_id);
        }
        
        // Redirect back to stats page with success message
        header("Location: dashboard.php?page={$return_page}&msg=goal_created");
        exit();
        
    } catch (PDOException $e) {
        ErrorLogger::error("Goal creation error: " . $e->getMessage());
        header("Location: dashboard.php?page=stats&error=goal_creation_failed");
        exit();
    }
}

// Fallback redirect
header("Location: dashboard.php?page=stats");
exit();
