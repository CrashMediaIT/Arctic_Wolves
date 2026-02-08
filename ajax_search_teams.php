<?php
/**
 * AJAX Team Search Endpoint
 * Returns matching teams for typeahead/autocomplete inputs.
 * Each result includes team name and season information.
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
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

try {
    $searchTerm = '%' . $query . '%';

    // Return team-season combinations so each selection includes the year
    $sql = "SELECT t.id as team_id, t.name as team_name, t.division,
                   s.id as season_id, s.name as season_name, s.is_active
            FROM team_seasons ts
            INNER JOIN teams t ON ts.team_id = t.id
            INNER JOIN seasons s ON ts.season_id = s.id
            WHERE t.is_active = 1
              AND (t.name LIKE ? OR s.name LIKE ?)
            ORDER BY s.is_active DESC, s.start_date DESC, t.name
            LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$searchTerm, $searchTerm, $limit]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = array_map(function($row) {
        return [
            'id'        => $row['team_id'] . '|' . $row['season_id'],
            'name'      => $row['team_name'] . ' — ' . $row['season_name'],
            'role'      => $row['is_active'] ? 'Active Season' : '',
            'team_id'   => (int) $row['team_id'],
            'season_id' => (int) $row['season_id']
        ];
    }, $rows);

    echo json_encode(['success' => true, 'results' => $results]);
} catch (PDOException $e) {
    error_log("Team search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
