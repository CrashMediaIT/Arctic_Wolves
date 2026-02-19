<?php
session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

$allowed_roles= ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'];
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    header("Location: dashboard.php"); exit();
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
}

$action = $_POST['action'] ?? '';
$id     = $_POST['id'] ?? $_POST['session_id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

// ASSIGN PRACTICE PLAN to a specific session date
if ($action == 'assign_practice_plan') {
    $session_id = intval($_POST['session_id'] ?? 0);
    $practice_plan_id = intval($_POST['practice_plan_id'] ?? 0);

    if ($session_id <= 0 || $practice_plan_id <= 0) {
        header("Location: dashboard.php?page=coach_calendar&status=error&message=" . urlencode('Invalid session or practice plan'));
        exit();
    }

    try {
        // Verify session exists
        $check = $pdo->prepare("SELECT id FROM sessions WHERE id = ?");
        $check->execute([$session_id]);
        if (!$check->fetch()) {
            header("Location: dashboard.php?page=coach_calendar&status=error&message=" . urlencode('Session not found'));
            exit();
        }

        // Verify practice plan exists
        $check = $pdo->prepare("SELECT id FROM practice_plans WHERE id = ?");
        $check->execute([$practice_plan_id]);
        if (!$check->fetch()) {
            header("Location: dashboard.php?page=coach_calendar&status=error&message=" . urlencode('Practice plan not found'));
            exit();
        }

        // Remove existing practice plan assignment for this session
        $pdo->prepare("DELETE FROM session_practice_plans WHERE session_id = ?")->execute([$session_id]);

        // Insert new assignment
        $stmt = $pdo->prepare("INSERT INTO session_practice_plans (session_id, practice_plan_id) VALUES (?, ?)");
        $stmt->execute([$session_id, $practice_plan_id]);

        // Also update the practice_plan_id column on the sessions table for backward compatibility
        $pdo->prepare("UPDATE sessions SET practice_plan_id = ? WHERE id = ?")->execute([$practice_plan_id, $session_id]);

        Auditor::log($pdo, $user_id, 'update', 'sessions', $session_id, ['action' => 'practice_plan_assigned', 'practice_plan_id' => $practice_plan_id]);

        header("Location: dashboard.php?page=coach_calendar&status=success&message=" . urlencode('Practice plan assigned successfully'));
        exit();
    } catch (PDOException $e) {
        ErrorLogger::error("Error assigning practice plan: " . $e->getMessage());
        header("Location: dashboard.php?page=coach_calendar&status=error&message=" . urlencode('Database error occurred'));
        exit();
    }
}

// CANCEL SESSION (set status to cancelled)
if ($action == 'cancel_session') {
    $session_id = intval($id);
    if ($session_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid session ID']);
        exit();
    }
    try {
        $stmt = $pdo->prepare("UPDATE sessions SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$session_id]);
        Auditor::log($pdo, $user_id, 'update', 'sessions', $session_id, ['action' => 'session_cancelled']);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

// UPDATE STATUS (e.g. mark as completed)
if ($action == 'update_status') {
    $session_id = intval($id);
    $newStatus = $_POST['status'] ?? '';
    $allowedStatuses = ['scheduled', 'completed', 'cancelled'];
    if ($session_id <= 0 || !in_array($newStatus, $allowedStatuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit();
    }
    try {
        $stmt = $pdo->prepare("UPDATE sessions SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $session_id]);
        Auditor::log($pdo, $user_id, 'update', 'sessions', $session_id, ['action' => 'status_updated', 'status' => $newStatus]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

// DELETE
if ($action == 'delete') {
    // Note: Foreign keys in 'bookings' should be set to ON DELETE CASCADE
    // If not, you'd delete bookings first: $pdo->prepare("DELETE FROM bookings WHERE session_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM sessions WHERE id = ?")->execute([$id]);
    Auditor::log($pdo, $user_id, 'delete', 'sessions', intval($id), ['action' => 'session_deleted']);
    header("Location: dashboard.php?page=session_history&status=deleted");
    exit();
}

// UPDATE
if ($action == 'update') {
    $type  = $_POST['session_type'];
    $title = $_POST['title'];
    $date  = $_POST['date'];
    $time  = $_POST['time'];
    $plan  = $_POST['session_plan'];
    
    // Find Location City based on Name (Since the form sends name)
    $locName = $_POST['location_name'];
    $stmt = $pdo->prepare("SELECT city FROM locations WHERE name = ?");
    $stmt->execute([$locName]);
    $locData = $stmt->fetch();
    $city = $locData ? $locData['city'] : 'Unknown';

    try {
        $sql = "UPDATE sessions SET session_type=?, title=?, session_date=?, session_time=?, session_plan=?, arena=?, city=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$type, $title, $date, $time, $plan, $locName, $city, $id]);
        
        Auditor::log($pdo, $user_id, 'update', 'sessions', intval($id), ['action' => 'session_updated', 'title' => $title]);

        header("Location: dashboard.php?page=session_history&status=updated");
        exit();
    } catch (PDOException $e) {
        die("Error updating session: " . $e->getMessage());
    }
}
?>