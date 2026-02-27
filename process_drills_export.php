<?php
/**
 * Process Drill Export
 * Exports all drills and their categories/tags as JSON
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Security check - must be logged in
if (!isset($_SESSION['logged_in'])) {
    http_response_code(403);
    exit('Access denied');
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Only coaches and admins can export drills
if (!in_array($user_role, ['admin', 'coach', 'health_coach'])) {
    http_response_code(403);
    exit('Access denied');
}

// Validate CSRF token from GET parameter
if (!isset($_GET['csrf_token']) || !validateCSRFToken($_GET['csrf_token'])) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

try {
    // Get all drill categories
    $cat_stmt = $pdo->query("SELECT id, name, description, position_type FROM drill_categories ORDER BY id");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all drills
    $drill_stmt = $pdo->query("
        SELECT d.id, d.title, d.description, d.setup, d.coaching_points, d.progression,
               d.category_id, d.diagram_data, d.video_url, d.ihs_source_url,
               d.created_at, d.updated_at, d.version,
               dc.name as category_name
        FROM drills d
        LEFT JOIN drill_categories dc ON d.category_id = dc.id
        ORDER BY d.id
    ");
    $drills = $drill_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all drill tags
    $tags_stmt = $pdo->query("SELECT drill_id, tag_name FROM drill_tags ORDER BY drill_id");
    $all_tags = $tags_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group tags by drill_id
    $tags_by_drill = [];
    foreach ($all_tags as $tag) {
        $tags_by_drill[$tag['drill_id']][] = $tag['tag_name'];
    }

    // Attach tags to drills
    foreach ($drills as &$drill) {
        $drill['tags'] = $tags_by_drill[$drill['id']] ?? [];
    }
    unset($drill);

    $export_data = [
        'export_type' => 'drills',
        'export_date' => date('Y-m-d H:i:s'),
        'version' => '1.0',
        'categories' => $categories,
        'drills' => $drills
    ];

    $json = json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Set headers for file download
    $filename = 'arctic_wolves_drills_' . date('Ymd_His') . '.json';
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
