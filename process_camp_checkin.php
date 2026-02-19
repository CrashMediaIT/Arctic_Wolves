<?php
/**
 * Process Camp Check-In/Check-Out
 * Handles QR code generation, sharing, and scanning for child check-in/check-out
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/encryption.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Set security headers
setSecurityHeaders();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_id'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit();
    }
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF validation failed']);
            exit();
        }
        http_response_code(403);
        die('CSRF token validation failed');
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        // =========================================================
        // PARENT ACTIONS: Generate and share QR codes
        // =========================================================
        case 'generate_checkin_code':
            if ($user_role !== 'parent') {
                throw new Exception('Only parents can generate check-in codes');
            }

            $booking_id = intval($_POST['booking_id'] ?? 0);
            $session_id = intval($_POST['session_id'] ?? 0);
            $athlete_id = intval($_POST['athlete_id'] ?? 0);
            $items_description = trim($_POST['items_description'] ?? '');
            $code_type = trim($_POST['code_type'] ?? 'checkin');

            if ($booking_id <= 0 || $session_id <= 0 || $athlete_id <= 0) {
                throw new Exception('Invalid booking, session, or athlete');
            }

            if (!in_array($code_type, ['checkin', 'checkout'])) {
                $code_type = 'checkin';
            }

            // Verify the parent manages this athlete
            $stmt = $pdo->prepare("SELECT id FROM managed_athletes WHERE parent_id = ? AND athlete_id = ?");
            $stmt->execute([$user_id, $athlete_id]);
            if (!$stmt->fetch()) {
                throw new Exception('You do not manage this athlete');
            }

            // Verify the booking exists and session has check-in enabled
            $stmt = $pdo->prepare("
                SELECT b.id, s.enable_child_checkin, s.session_date, s.session_time 
                FROM bookings b 
                INNER JOIN sessions s ON b.session_id = s.id 
                WHERE b.id = ? AND b.session_id = ? AND b.user_id = ? AND b.status = 'paid'
            ");
            $stmt->execute([$booking_id, $session_id, $athlete_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking || !$booking['enable_child_checkin']) {
                throw new Exception('Check-in is not enabled for this session');
            }

            // Check for existing unused code of same type
            $stmt = $pdo->prepare("
                SELECT id, code FROM camp_checkin_codes 
                WHERE booking_id = ? AND athlete_id = ? AND session_id = ? AND code_type = ? AND is_used = 0 AND expires_at > NOW()
            ");
            $stmt->execute([$booking_id, $athlete_id, $session_id, $code_type]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update items description if changed
                if (!empty($items_description)) {
                    $stmt = $pdo->prepare("UPDATE camp_checkin_codes SET items_description = ? WHERE id = ?");
                    $stmt->execute([$items_description, $existing['id']]);
                }
                $code = $existing['code'];
                $code_id = $existing['id'];
            } else {
                // Generate unique code
                $code = bin2hex(random_bytes(16));
                // Set expiry: check-in codes expire at end of session day, checkout codes expire 24 hours after session
                $session_date = $booking['session_date'];
                if ($code_type === 'checkin') {
                    $expires_at = date('Y-m-d 23:59:59', strtotime($session_date));
                } else {
                    $expires_at = date('Y-m-d 23:59:59', strtotime($session_date . ' +1 day'));
                }

                $stmt = $pdo->prepare("
                    INSERT INTO camp_checkin_codes (booking_id, athlete_id, session_id, parent_id, code_type, code, items_description, expires_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$booking_id, $athlete_id, $session_id, $user_id, $code_type, $code, $items_description ?: null, $expires_at]);
                $code_id = $pdo->lastInsertId();
                Auditor::log($pdo, $user_id, 'create', 'camp_checkin_codes', $code_id, ['action' => 'Check-in code generated', 'code_type' => $code_type]);
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true, 
                    'code' => $code, 
                    'code_id' => $code_id,
                    'code_type' => $code_type,
                    'message' => ucfirst($code_type) . ' code generated successfully'
                ]);
                exit();
            }

            header("Location: dashboard.php?page=camp_checkin&booking_id=$booking_id&session_id=$session_id&athlete_id=$athlete_id&status=code_generated&type=$code_type");
            exit();

        case 'share_code':
            if ($user_role !== 'parent') {
                throw new Exception('Only parents can share codes');
            }

            $code_id = intval($_POST['code_id'] ?? 0);
            $share_email = trim($_POST['share_email'] ?? '');
            $share_name = trim($_POST['share_name'] ?? '');

            if ($code_id <= 0 || empty($share_email) || !filter_var($share_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid code ID or email address');
            }

            // Verify parent owns this code
            $stmt = $pdo->prepare("SELECT * FROM camp_checkin_codes WHERE id = ? AND parent_id = ?");
            $stmt->execute([$code_id, $user_id]);
            $code_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$code_record) {
                throw new Exception('Code not found');
            }

            // Update code with share info
            $stmt = $pdo->prepare("UPDATE camp_checkin_codes SET shared_email = ?, shared_name = ? WHERE id = ?");
            $stmt->execute([$share_email, $share_name ?: null, $code_id]);
            Auditor::log($pdo, $user_id, 'update', 'camp_checkin_codes', $code_id, ['action' => 'Check-in code shared']);

            // Send email with QR code info
            try {
                require_once __DIR__ . '/mailer.php';

                // Get athlete and session details
                $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                $stmt->execute([$code_record['athlete_id']]);
                $athlete_info = $stmt->fetch(PDO::FETCH_ASSOC);
                $athlete_info = decryptUserRow($athlete_info);

                $stmt = $pdo->prepare("SELECT title, session_date, session_time, arena FROM sessions WHERE id = ?");
                $stmt->execute([$code_record['session_id']]);
                $session_info = $stmt->fetch(PDO::FETCH_ASSOC);

                // Get parent name
                $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $parent_info = $stmt->fetch(PDO::FETCH_ASSOC);
                $parent_info = decryptUserRow($parent_info);

                $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
                $base_url = $settings['base_url'] ?? '';
                if (empty($base_url)) {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $base_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
                }
                $base_url = rtrim($base_url, '/');

                $code_type_label = $code_record['code_type'] === 'checkin' ? 'Check-In (Drop-Off)' : 'Check-Out (Pickup)';
                $scan_url = $base_url . '/camp_checkin_scan.php?code=' . urlencode($code_record['code']);

                sendEmail($share_email, 'notification', [
                    'name' => $share_name ?: 'Parent/Guardian',
                    'title' => "Camp $code_type_label Code for " . $athlete_info['first_name'],
                    'message' => ($parent_info['first_name'] . ' ' . $parent_info['last_name']) . " has shared a $code_type_label code with you.\n\n" .
                                 "Athlete: " . $athlete_info['first_name'] . ' ' . $athlete_info['last_name'] . "\n" .
                                 "Session: " . ($session_info['title'] ?? 'Camp Session') . "\n" .
                                 "Date: " . date('M j, Y', strtotime($session_info['session_date'])) . "\n" .
                                 ($session_info['session_time'] ? "Time: " . date('g:i A', strtotime($session_info['session_time'])) . "\n" : '') .
                                 ($session_info['arena'] ? "Location: " . $session_info['arena'] . "\n" : '') .
                                 ($code_record['items_description'] ? "\nItems: " . $code_record['items_description'] . "\n" : '') .
                                 "\nQR Code Value: " . $code_record['code'] . "\n" .
                                 "Please show this code at the front desk for scanning.\n" .
                                 "You can also use this direct link to display the QR code:",
                    'link' => $scan_url
                ]);
            } catch (Exception $e) {
                ErrorLogger::error("Failed to send check-in code email: " . $e->getMessage());
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Code shared successfully']);
                exit();
            }

            header("Location: dashboard.php?page=camp_checkin&booking_id={$code_record['booking_id']}&session_id={$code_record['session_id']}&athlete_id={$code_record['athlete_id']}&status=code_shared");
            exit();

        // =========================================================
        // STAFF/POS ACTIONS: Scan and process QR codes
        // =========================================================
        case 'scan_code':
            // Staff scanning a QR code at POS terminal
            if (!in_array($user_role, ['admin', 'front_desk_staff'])) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit();
                }
                throw new Exception('Access denied');
            }

            $code = trim($_POST['code'] ?? '');

            if (empty($code)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No code provided']);
                exit();
            }

            // Look up the code
            $stmt = $pdo->prepare("
                SELECT cc.*, 
                       u.first_name as athlete_first_name, u.last_name as athlete_last_name,
                       s.title as session_title, s.session_date, s.session_time, s.arena,
                       p.first_name as parent_first_name, p.last_name as parent_last_name
                FROM camp_checkin_codes cc
                INNER JOIN users u ON cc.athlete_id = u.id
                INNER JOIN sessions s ON cc.session_id = s.id
                INNER JOIN users p ON cc.parent_id = p.id
                WHERE cc.code = ?
            ");
            $stmt->execute([$code]);
            $code_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$code_record) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid code. This QR code is not recognized.', 'status' => 'invalid']);
                exit();
            }

            // Decrypt names
            $code_record = decryptUserRow($code_record);

            // Check if already used
            if ($code_record['is_used']) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false, 
                    'message' => 'This code has already been used on ' . date('M j, Y g:i A', strtotime($code_record['used_at'])),
                    'status' => 'already_used',
                    'athlete_name' => $code_record['athlete_first_name'] . ' ' . $code_record['athlete_last_name'],
                    'code_type' => $code_record['code_type']
                ]);
                exit();
            }

            // Check if expired
            if (strtotime($code_record['expires_at']) < time()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false, 
                    'message' => 'This code has expired.',
                    'status' => 'expired',
                    'athlete_name' => $code_record['athlete_first_name'] . ' ' . $code_record['athlete_last_name']
                ]);
                exit();
            }

            // Mark code as used
            $stmt = $pdo->prepare("UPDATE camp_checkin_codes SET is_used = 1, used_at = NOW(), scanned_by = ? WHERE id = ?");
            $stmt->execute([$user_id, $code_record['id']]);
            Auditor::log($pdo, $user_id, 'update', 'camp_checkin_codes', $code_record['id'], ['action' => 'Code scanned', 'code_type' => $code_record['code_type']]);

            // Update session_attendance
            if ($code_record['code_type'] === 'checkin') {
                $stmt = $pdo->prepare("
                    INSERT INTO session_attendance (session_id, user_id, attendance_status, check_in_time, recorded_by, created_at)
                    VALUES (?, ?, 'present', NOW(), ?, NOW())
                    ON DUPLICATE KEY UPDATE attendance_status = 'present', check_in_time = NOW(), recorded_by = ?
                ");
                $stmt->execute([$code_record['session_id'], $code_record['athlete_id'], $user_id, $user_id]);
            } else {
                // Check-out: update the existing attendance record
                $stmt = $pdo->prepare("
                    UPDATE session_attendance SET check_out_time = NOW(), recorded_by = ? 
                    WHERE session_id = ? AND user_id = ?
                ");
                $stmt->execute([$user_id, $code_record['session_id'], $code_record['athlete_id']]);
            }

            $code_type_label = $code_record['code_type'] === 'checkin' ? 'checked IN' : 'checked OUT';

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => $code_record['athlete_first_name'] . ' ' . $code_record['athlete_last_name'] . ' has been ' . $code_type_label . '!',
                'athlete_name' => $code_record['athlete_first_name'] . ' ' . $code_record['athlete_last_name'],
                'session_title' => $code_record['session_title'],
                'code_type' => $code_record['code_type'],
                'items' => $code_record['items_description'],
                'parent_name' => $code_record['parent_first_name'] . ' ' . $code_record['parent_last_name'],
                'shared_with' => $code_record['shared_name'] ?? null,
                'session_date' => date('M j, Y', strtotime($code_record['session_date'])),
                'session_time' => $code_record['session_time'] ? date('g:i A', strtotime($code_record['session_time'])) : null
            ]);
            exit();

        default:
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
            }
            header("Location: dashboard.php");
            exit();
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ErrorLogger::error("Camp check-in error: " . $e->getMessage());

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }

    header("Location: dashboard.php?error=system_error");
    exit();
}
?>
