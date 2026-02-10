<?php
/**
 * Process Parent Invitations
 * Handles inviting additional parents/grandparents to manage athletes
 * The inviting parent selects which children the new parent can manage
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/encryption.php';

// Set security headers
setSecurityHeaders();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';
$action = $_POST['action'] ?? '';

// Only parents can manage invitations
if ($user_role !== 'parent') {
    header("Location: dashboard.php?error=permission_denied");
    exit();
}

try {
    switch ($action) {
        case 'send_invitation':
            $email = trim($_POST['invite_email'] ?? '');
            $relationship = trim($_POST['invite_relationship'] ?? 'parent');
            $athlete_ids = isset($_POST['invite_athlete_ids']) ? array_map('intval', $_POST['invite_athlete_ids']) : [];

            // Validate inputs
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                header("Location: dashboard.php?page=parent_home&error=invalid_email");
                exit();
            }

            if (empty($athlete_ids)) {
                header("Location: dashboard.php?page=parent_home&error=no_athletes_selected");
                exit();
            }

            // Validate relationship type
            $allowed_relationships = ['parent', 'grandparent', 'guardian', 'other'];
            if (!in_array($relationship, $allowed_relationships)) {
                $relationship = 'parent';
            }

            // Check that inviter actually manages these athletes
            $placeholders = implode(',', array_fill(0, count($athlete_ids), '?'));
            $params = array_merge([$user_id], $athlete_ids);
            $stmt = $pdo->prepare("SELECT athlete_id FROM managed_athletes WHERE parent_id = ? AND athlete_id IN ($placeholders)");
            $stmt->execute($params);
            $valid_athletes = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($valid_athletes) !== count($athlete_ids)) {
                header("Location: dashboard.php?page=parent_home&error=invalid_athletes");
                exit();
            }

            // Cannot invite yourself
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id = ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                header("Location: dashboard.php?page=parent_home&error=cannot_invite_self");
                exit();
            }

            // Check for existing pending invitation to same email
            $stmt = $pdo->prepare("SELECT id FROM parent_invitations WHERE inviter_id = ? AND email = ? AND status = 'pending' AND expires_at > NOW()");
            $stmt->execute([$user_id, $email]);
            if ($stmt->fetch()) {
                header("Location: dashboard.php?page=parent_home&error=invitation_already_sent");
                exit();
            }

            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));

            $pdo->beginTransaction();

            // Create invitation
            $stmt = $pdo->prepare("INSERT INTO parent_invitations (inviter_id, email, token, relationship, expires_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $email, $token, $relationship, $expires_at]);
            $invitation_id = $pdo->lastInsertId();

            // Link athletes to invitation
            $stmt = $pdo->prepare("INSERT INTO parent_invitation_athletes (invitation_id, athlete_id) VALUES (?, ?)");
            foreach ($athlete_ids as $athlete_id) {
                $stmt->execute([$invitation_id, $athlete_id]);
            }

            $pdo->commit();

            // Send invitation email
            try {
                require_once __DIR__ . '/mailer.php';

                // Get inviter name
                $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $inviter = $stmt->fetch(PDO::FETCH_ASSOC);
                $inviter = decryptUserRow($inviter);
                $inviter_name = $inviter['first_name'] . ' ' . $inviter['last_name'];

                // Get athlete names
                $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id IN ($placeholders)");
                $stmt->execute($athlete_ids);
                $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $athletes = decryptUserRows($athletes);
                $athlete_names = array_map(function($a) { return $a['first_name'] . ' ' . $a['last_name']; }, $athletes);

                // Build invitation URL
                $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
                $base_url = $settings['base_url'] ?? '';
                if (empty($base_url)) {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $base_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
                }
                $base_url = rtrim($base_url, '/');
                $invite_url = $base_url . '/register.php?invitation=' . urlencode($token);

                sendEmail($email, 'notification', [
                    'name' => 'Parent/Guardian',
                    'title' => 'You\'ve Been Invited to Manage Athletes',
                    'message' => "$inviter_name has invited you as a $relationship to manage the following athletes:\n\n" .
                                 implode("\n", array_map(function($n) { return "• $n"; }, $athlete_names)) . "\n\n" .
                                 "Click the link below to create your account and start managing these athletes.\n" .
                                 "This invitation expires in 7 days.",
                    'link' => $invite_url
                ]);
            } catch (Exception $e) {
                error_log("Failed to send parent invitation email: " . $e->getMessage());
            }

            header("Location: dashboard.php?page=parent_home&status=invitation_sent");
            exit();

        case 'revoke_invitation':
            $invitation_id = intval($_POST['invitation_id'] ?? 0);

            if ($invitation_id <= 0) {
                header("Location: dashboard.php?page=parent_home&error=invalid_invitation");
                exit();
            }

            // Only the inviter can revoke
            $stmt = $pdo->prepare("UPDATE parent_invitations SET status = 'revoked' WHERE id = ? AND inviter_id = ? AND status = 'pending'");
            $stmt->execute([$invitation_id, $user_id]);

            header("Location: dashboard.php?page=parent_home&status=invitation_revoked");
            exit();

        case 'accept_invitation':
            $token = trim($_POST['invitation_token'] ?? '');

            if (empty($token)) {
                header("Location: dashboard.php?error=invalid_token");
                exit();
            }

            // Find valid invitation
            $stmt = $pdo->prepare("SELECT * FROM parent_invitations WHERE token = ? AND status = 'pending' AND expires_at > NOW()");
            $stmt->execute([$token]);
            $invitation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invitation) {
                header("Location: dashboard.php?error=invitation_expired");
                exit();
            }

            // Get the athletes from this invitation
            $stmt = $pdo->prepare("SELECT athlete_id FROM parent_invitation_athletes WHERE invitation_id = ?");
            $stmt->execute([$invitation['id']]);
            $athlete_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $pdo->beginTransaction();

            // Add managed athlete relationships for the accepting parent
            $stmt = $pdo->prepare("INSERT IGNORE INTO managed_athletes (parent_id, athlete_id, relationship, can_book, can_view_stats, status) VALUES (?, ?, ?, 1, 1, 'active')");
            foreach ($athlete_ids as $athlete_id) {
                $stmt->execute([$user_id, $athlete_id, $invitation['relationship']]);
            }

            // Also add to parent_athlete_relationships for consistency
            $stmt = $pdo->prepare("INSERT IGNORE INTO parent_athlete_relationships (parent_id, athlete_id, relationship_type) VALUES (?, ?, ?)");
            foreach ($athlete_ids as $athlete_id) {
                $stmt->execute([$user_id, $athlete_id, $invitation['relationship']]);
            }

            // Mark invitation as accepted
            $stmt = $pdo->prepare("UPDATE parent_invitations SET status = 'accepted', accepted_by = ?, accepted_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id, $invitation['id']]);

            $pdo->commit();

            header("Location: dashboard.php?page=parent_home&status=invitation_accepted");
            exit();

        default:
            header("Location: dashboard.php?page=parent_home&error=invalid_action");
            exit();
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Parent invitation error: " . $e->getMessage());
    header("Location: dashboard.php?page=parent_home&error=system_error");
    exit();
}
?>
