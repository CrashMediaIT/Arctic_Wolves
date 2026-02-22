<?php
/**
 * AJAX Season Search Endpoint
 * Returns matching seasons for typeahead/autocomplete inputs.
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
$limit = min(intval($_GET['limit'] ?? 15), 50);

if (strlen($query) < 1) {
    // Return all active seasons when no query provided
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, is_active, start_date, end_date
            FROM seasons
            ORDER BY is_active DESC, start_date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id'   => (int) $row['id'],
                'name' => $row['name'],
                'role' => $row['is_active'] ? 'Active' : 'Inactive'
            ];
        }

        echo json_encode(['success' => true, 'results' => $results]);
    } catch (PDOException $e) {
        error_log("Season search error: " . $e->getMessage());
        echo json_encode(['success' => true, 'results' => []]);
    }
    exit;
}

try {
    // Split query into individual words for matching
    $words = preg_split('/\s+/', $query);
    $words = array_filter($words, function($w) { return strlen($w) >= 1; });

    $conditions = [];
    $params = [];
    foreach ($words as $word) {
        $conditions[] = "s.name LIKE ?";
        $params[] = '%' . $word . '%';
    }
    $whereClause = implode(' AND ', $conditions);

    $sql = "SELECT s.id, s.name, s.is_active, s.start_date, s.end_date
            FROM seasons s
            WHERE $whereClause
            ORDER BY s.is_active DESC, s.start_date DESC
            LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [$limit]));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $row) {
        $results[] = [
            'id'   => (int) $row['id'],
            'name' => $row['name'],
            'role' => $row['is_active'] ? 'Active' : 'Inactive'
        ];
    }

    echo json_encode(['success' => true, 'results' => $results]);
} catch (PDOException $e) {
    error_log("Season search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
