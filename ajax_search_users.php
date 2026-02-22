<?php
/**
 * AJAX User Search Endpoint
 * Returns matching users for typeahead/autocomplete inputs.
 * Supports filtering by role(s) and limiting results.
 *
 * Note: first_name and last_name are encrypted in the database via FieldEncryption.
 * SQL LIKE cannot match encrypted values, so we fetch candidates, decrypt, then
 * filter in PHP. Email is NOT encrypted and can still be matched in SQL to narrow
 * the candidate set when the search query looks like an email.
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

    // first_name and last_name are encrypted, so we cannot use SQL LIKE on them.
    // Fetch all candidate users (filtered by role/active), decrypt, then filter in PHP.
    // Email is not encrypted, so we can still pre-filter by email in SQL if the query
    // looks like it contains an '@'.
    if (strpos($query, '@') !== false) {
        $where[] = "u.email LIKE ?";
        $params[] = '%' . $query . '%';
    }

    $whereClause = implode(' AND ', $where);
    $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.role
            FROM users u
            WHERE $whereClause
            ORDER BY u.last_name, u.first_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $users = decryptUserRows($users);

    // Filter decrypted users by matching search query words against name and email
    $words = preg_split('/\s+/', mb_strtolower($query));
    $words = array_filter($words, function($w) { return strlen($w) >= 1; });

    $filtered = array_filter($users, function($u) use ($words) {
        $haystack = mb_strtolower(
            ($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '') . ' ' . ($u['email'] ?? '')
        );
        foreach ($words as $word) {
            if (mb_strpos($haystack, $word) === false) {
                return false;
            }
        }
        return true;
    });

    // Apply limit after filtering
    $filtered = array_slice(array_values($filtered), 0, $limit);

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
    }, $filtered);

    echo json_encode(['success' => true, 'results' => $results]);
} catch (PDOException $e) {
    error_log("User search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
