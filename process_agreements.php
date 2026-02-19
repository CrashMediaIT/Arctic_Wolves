<?php
/**
 * Process Agreements
 * Handles acceptance of waiver and privacy policy agreements
 * Used for first-sign-in flow when users are created by admin/coach
 */
session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

setSecurityHeaders();

if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    header("Location: dashboard.php?error=csrf_invalid");
    exit();
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

if ($action === 'accept_agreements') {
    $waiver_accepted = isset($_POST['agree_waiver']);
    $privacy_accepted = isset($_POST['agree_privacy_policy']);
    $promotional_opt_in = isset($_POST['promotional_opt_in']) ? 1 : 0;
    $share_evaluations_potential = isset($_POST['share_evaluations_potential_teams']) ? 1 : 0;

    if (!$waiver_accepted || !$privacy_accepted) {
        header("Location: dashboard.php?error=agreements_required");
        exit();
    }

    try {
        $pdo->beginTransaction();

        $client_ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Insert or update agreement records
        $agree_sql = "INSERT INTO user_agreements (user_id, agreement_type, agreement_version, accepted_at, ip_address, user_agent, signature_status, promotional_opt_in, share_evaluations_potential_teams) 
                      VALUES (?, ?, '1.0', NOW(), ?, ?, 'signed', ?, ?)
                      ON DUPLICATE KEY UPDATE accepted_at = NOW(), ip_address = VALUES(ip_address), user_agent = VALUES(user_agent), signature_status = 'signed', promotional_opt_in = VALUES(promotional_opt_in), share_evaluations_potential_teams = VALUES(share_evaluations_potential_teams)";
        
        $agree_stmt = $pdo->prepare($agree_sql);
        $agree_stmt->execute([$user_id, 'waiver', $client_ip, $user_agent, $promotional_opt_in, $share_evaluations_potential]);
        $agree_stmt->execute([$user_id, 'privacy_policy', $client_ip, $user_agent, $promotional_opt_in, $share_evaluations_potential]);

        // Update user record
        $update_sql = "UPDATE users SET agreements_accepted = 1, promotional_opt_in = ? WHERE id = ?";
        $pdo->prepare($update_sql)->execute([$promotional_opt_in, $user_id]);

        $pdo->commit();
        Auditor::log($pdo, $user_id, 'create', 'user_agreements', $user_id, ['action' => 'Agreements accepted']);
        
        header("Location: dashboard.php");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        ErrorLogger::error("Agreement acceptance error: " . $e->getMessage());
        header("Location: dashboard.php?error=database_error");
        exit();
    }
}

if ($action === 'update_template') {
    // Admin only - update agreement template content (save draft without forcing re-sign)
    if ($_SESSION['user_role'] !== 'admin') {
        header("Location: dashboard.php?page=employee_contracts&tab=agreements&error=unauthorized");
        exit();
    }

    $template_id = intval($_POST['template_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $version = trim($_POST['version'] ?? '1.0');
    $content = $_POST['content'] ?? '';
    $docuseal_template_id = !empty($_POST['docuseal_template_id']) ? intval($_POST['docuseal_template_id']) : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($template_id <= 0 || empty($title) || empty($content)) {
        header("Location: dashboard.php?page=employee_contracts&tab=agreements&error=invalid_data");
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE agreement_templates SET title = ?, content = ?, version = ?, docuseal_template_id = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$title, $content, $version, $docuseal_template_id, $is_active, $template_id]);
        Auditor::log($pdo, $user_id, 'update', 'agreement_templates', $template_id, ['action' => 'Agreement template updated', 'title' => $title]);
        
        header("Location: dashboard.php?page=employee_contracts&tab=agreements&status=success");
        exit();
    } catch (PDOException $e) {
        ErrorLogger::error("Agreement template update error: " . $e->getMessage());
        header("Location: dashboard.php?page=employee_contracts&tab=agreements&error=database_error");
        exit();
    }
}

if ($action === 'publish_and_force_resign') {
    // Admin only - update template AND force all users to re-sign
    if ($_SESSION['user_role'] !== 'admin') {
        header("Location: dashboard.php?page=employee_contracts&tab=agreements&error=unauthorized");
        exit();
    }

    $template_id = intval($_POST['template_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $version = trim($_POST['version'] ?? '1.0');
    $content = $_POST['content'] ?? '';
    $docuseal_template_id = !empty($_POST['docuseal_template_id']) ? intval($_POST['docuseal_template_id']) : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($template_id <= 0 || empty($title) || empty($content)) {
        header("Location: dashboard.php?page=employee_contracts&tab=agreements&error=invalid_data");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Update the template with new content/version
        $stmt = $pdo->prepare("UPDATE agreement_templates SET title = ?, content = ?, version = ?, docuseal_template_id = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$title, $content, $version, $docuseal_template_id, $is_active, $template_id]);

        // Get the agreement type for this template
        $type_stmt = $pdo->prepare("SELECT agreement_type FROM agreement_templates WHERE id = ?");
        $type_stmt->execute([$template_id]);
        $agreement_type = $type_stmt->fetchColumn();

        // Reset all non-admin users' agreements_accepted to 0 so they must re-sign
        $pdo->exec("UPDATE users SET agreements_accepted = 0 WHERE role != 'admin'");

        // Update existing user_agreements for this type to expired so they need to re-accept
        if ($agreement_type) {
            $pdo->prepare("UPDATE user_agreements SET signature_status = 'expired' WHERE agreement_type = ? AND signature_status = 'signed'")->execute([$agreement_type]);
        }

        $pdo->commit();
        Auditor::log($pdo, $user_id, 'update', 'agreement_templates', $template_id, ['action' => 'Agreement published and force re-sign triggered', 'title' => $title]);
        
        header("Location: dashboard.php?page=employee_contracts&tab=agreements&status=success&message=" . urlencode('Agreement published. All users will be required to re-sign.'));
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        ErrorLogger::error("Agreement publish & force resign error: " . $e->getMessage());
        header("Location: dashboard.php?page=employee_contracts&tab=agreements&error=database_error");
        exit();
    }
}

// Default redirect
header("Location: dashboard.php");
exit();
?>
