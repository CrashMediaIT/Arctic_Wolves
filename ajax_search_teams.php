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
    // Split query into individual words for approximate matching
    $words = preg_split('/\s+/', $query);
    $words = array_filter($words, function($w) { return strlen($w) >= 1; });

    // Build LIKE conditions for each word (all words must match somewhere)
    $teamWordConditions = [];
    $params = [];
    foreach ($words as $word) {
        $teamWordConditions[] = "(t.name LIKE ? OR t.division LIKE ?)";
        $params[] = '%' . $word . '%';
        $params[] = '%' . $word . '%';
    }
    $teamWordClause = implode(' AND ', $teamWordConditions);

    $seasonWordConditions = [];
    $seasonParams = [];
    foreach ($words as $word) {
        $seasonWordConditions[] = "(t.name LIKE ? OR s.name LIKE ? OR t.division LIKE ?)";
        $seasonParams[] = '%' . $word . '%';
        $seasonParams[] = '%' . $word . '%';
        $seasonParams[] = '%' . $word . '%';
    }
    $seasonWordClause = implode(' AND ', $seasonWordConditions);

    $results = [];
    $seenIds = [];

    // First: search team-season combinations
    $sql = "SELECT t.id as team_id, t.name as team_name, t.division,
                   s.id as season_id, s.name as season_name, s.is_active
            FROM team_seasons ts
            INNER JOIN teams t ON ts.team_id = t.id
            INNER JOIN seasons s ON ts.season_id = s.id
            WHERE t.is_active = 1
              AND ($seasonWordClause)
            ORDER BY s.is_active DESC, s.start_date DESC, t.name
            LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($seasonParams, [$limit]));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $compositeId = $row['team_id'] . '|' . $row['season_id'];
        if (!isset($seenIds[$compositeId])) {
            $seenIds[$compositeId] = true;
            $results[] = [
                'id'        => $compositeId,
                'name'      => $row['team_name'] . ' — ' . $row['season_name'],
                'role'      => $row['is_active'] ? 'Active Season' : '',
                'team_id'   => (int) $row['team_id'],
                'season_id' => (int) $row['season_id']
            ];
        }
    }

    // Second: search teams without season associations (fallback)
    if (count($results) < $limit) {
        $remaining = $limit - count($results);
        $sql2 = "SELECT t.id as team_id, t.name as team_name, t.division
                 FROM teams t
                 LEFT JOIN team_seasons ts2 ON t.id = ts2.team_id
                 WHERE t.is_active = 1
                   AND ($teamWordClause)
                   AND ts2.team_id IS NULL
                 ORDER BY t.name
                 LIMIT ?";

        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute(array_merge($params, [$remaining]));
        $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows2 as $row) {
            $compositeId = $row['team_id'] . '|';
            if (!isset($seenIds[$compositeId])) {
                $seenIds[$compositeId] = true;
                $results[] = [
                    'id'        => $compositeId,
                    'name'      => $row['team_name'],
                    'role'      => 'No Season',
                    'team_id'   => (int) $row['team_id'],
                    'season_id' => null
                ];
            }
        }
    }

    echo json_encode(['success' => true, 'results' => $results]);
} catch (PDOException $e) {
    error_log("Team search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
