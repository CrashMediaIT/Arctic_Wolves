<?php
/**
 * Process Practice Plans Export
 * Exports all practice plans with their associated drills as JSON
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';

// Security check - must be logged in
if (!isset($_SESSION['logged_in'])) {
    http_response_code(403);
    exit('Access denied');
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Only coaches and admins can export practice plans
if (!in_array($user_role, ['admin', 'coach'])) {
    http_response_code(403);
    exit('Access denied');
}

// Validate CSRF token from GET parameter
if (!isset($_GET['csrf_token']) || !validateCSRFToken($_GET['csrf_token'])) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

try {
    // Get all practice plan categories
    $cat_stmt = $pdo->query("SELECT id, name, description, display_order FROM practice_plan_categories ORDER BY display_order");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all practice plans
    $plan_stmt = $pdo->query("
        SELECT p.id, p.name, p.description, p.focus_area, p.age_group,
               p.duration_minutes, p.difficulty_level, p.created_at, p.updated_at,
               p.version, p.total_duration, p.title
        FROM practice_plans p
        ORDER BY p.id
    ");
    $plans = $plan_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all practice plan drill associations
    $drill_assoc_stmt = $pdo->query("
        SELECT ppd.practice_plan_id, ppd.drill_id, ppd.drill_order, 
               ppd.duration_minutes, ppd.notes,
               d.title as drill_title, d.description as drill_description,
               d.setup, d.coaching_points, d.progression,
               d.diagram_data, d.video_url, d.ihs_source_url,
               dc.name as category_name
        FROM practice_plan_drills ppd
        JOIN drills d ON ppd.drill_id = d.id
        LEFT JOIN drill_categories dc ON d.category_id = dc.id
        ORDER BY ppd.practice_plan_id, ppd.drill_order
    ");
    $all_drill_assocs = $drill_assoc_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group drill associations by plan_id
    $drills_by_plan = [];
    foreach ($all_drill_assocs as $assoc) {
        $drills_by_plan[$assoc['practice_plan_id']][] = $assoc;
    }

    // Attach drills to plans
    foreach ($plans as &$plan) {
        $plan['drills'] = $drills_by_plan[$plan['id']] ?? [];
    }
    unset($plan);

    // Also export all drill categories for reference
    $drill_cat_stmt = $pdo->query("SELECT id, name, description, position_type FROM drill_categories ORDER BY id");
    $drill_categories = $drill_cat_stmt->fetchAll(PDO::FETCH_ASSOC);

    $export_data = [
        'export_type' => 'practice_plans',
        'export_date' => date('Y-m-d H:i:s'),
        'version' => '1.0',
        'plan_categories' => $categories,
        'drill_categories' => $drill_categories,
        'practice_plans' => $plans
    ];

    $json = json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Set headers for file download
    $filename = 'arctic_wolves_practice_plans_' . date('Ymd_His') . '.json';
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json));
    header('Cache-Control: no-cache, no-store, must-revalidate');

    echo $json;
    exit;

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Export failed: ' . $e->getMessage()]);
    exit;
}
