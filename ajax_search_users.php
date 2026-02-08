<?php
/**
 * AJAX User Search Endpoint
 * Returns matching users for typeahead/autocomplete inputs.
 * Supports filtering by role(s) and limiting results.
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';

header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');
$roles = $_GET['roles'] ?? '';       // comma-separated role list, e.g. "coach,admin"
$limit = min(intval($_GET['limit'] ?? 15), 50); // cap at 50

if (strlen($query) < 1) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

try {
    $where = ["u.is_active = 1"];
    $params = [];

    // Split query into individual words for approximate matching
    $words = preg_split('/\s+/', $query);
    $words = array_filter($words, function($w) { return strlen($w) >= 1; });

    // Build LIKE conditions: each word must match somewhere in name or email
    $wordConditions = [];
    foreach ($words as $word) {
        $wordConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $searchTerm = '%' . $word . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    $where[] = '(' . implode(' AND ', $wordConditions) . ')';

    // Filter by roles if specified
    if (!empty($roles)) {
        $roleList = array_map('trim', explode(',', $roles));
        $roleList = array_filter($roleList, function($r) {
            return in_array($r, ['admin','coach','coach_plus','team_coach','health_coach','athlete','parent','front_desk_staff']);
        });
        if (!empty($roleList)) {
            $placeholders = implode(',', array_fill(0, count($roleList), '?'));
            $where[] = "u.role IN ($placeholders)";
            $params = array_merge($params, $roleList);
        }
    }

    $whereClause = implode(' AND ', $where);
    $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.role
            FROM users u
            WHERE $whereClause
            ORDER BY u.last_name, u.first_name
            LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = array_map(function($u) {
        $roleLabel = '';
        switch($u['role']) {
            case 'admin': $roleLabel = 'Admin'; break;
            case 'coach': $roleLabel = 'Coach'; break;
            case 'coach_plus': $roleLabel = 'Coach Plus'; break;
            case 'team_coach': $roleLabel = 'Team Coach'; break;
            case 'health_coach': $roleLabel = 'Health Coach'; break;
            case 'athlete': $roleLabel = 'Athlete'; break;
            case 'parent': $roleLabel = 'Parent'; break;
            case 'front_desk_staff': $roleLabel = 'Front Desk'; break;
        }
        return [
            'id'    => (int) $u['id'],
            'name'  => $u['first_name'] . ' ' . $u['last_name'],
            'email' => $u['email'],
            'role'  => $roleLabel
        ];
    }, $users);

    echo json_encode(['success' => true, 'results' => $results]);
} catch (PDOException $e) {
    error_log("User search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
