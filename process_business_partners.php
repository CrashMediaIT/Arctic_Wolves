<?php
/**
 * Process Business Partners
 * Handles CRUD operations for business partners and their contracts
 */
session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once __DIR__ . '/lib/encryption.php';

setSecurityHeaders();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die('Access denied.');
}

checkCsrfToken();

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'create_partner':
            $company_name = trim($_POST['company_name'] ?? '');
            $company_email = trim($_POST['company_email'] ?? '');
            $company_phone = trim($_POST['company_phone'] ?? '');
            $company_website = trim($_POST['company_website'] ?? '');
            $company_address = trim($_POST['company_address'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $contact_name = trim($_POST['contact_name'] ?? '');
            $contact_title = trim($_POST['contact_title'] ?? '');
            $contact_email = trim($_POST['contact_email'] ?? '');
            $contact_phone = trim($_POST['contact_phone'] ?? '');

            if (empty($company_name)) {
                header("Location: dashboard.php?page=business_partners&tab=add&status=error&message=" . urlencode('Company name is required'));
                exit();
            }

            $enc_contact_name = $contact_name ? FieldEncryption::encrypt($contact_name) : null;
            $enc_contact_phone = $contact_phone ? FieldEncryption::encrypt($contact_phone) : null;

            $stmt = $pdo->prepare("INSERT INTO business_partners 
                (company_name, company_email, company_phone, company_website, company_address, description,
                 contact_name, contact_title, contact_email, contact_phone, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
            $stmt->execute([
                $company_name, $company_email ?: null, $company_phone ?: null, $company_website ?: null,
                $company_address ?: null, $description ?: null, $enc_contact_name, $contact_title ?: null,
                $contact_email ?: null, $enc_contact_phone, $user_id
            ]);

            header("Location: dashboard.php?page=business_partners&tab=partners&status=success&message=" . urlencode('Partner created successfully'));
            exit();

        case 'update_partner':
            $partner_id = intval($_POST['partner_id'] ?? 0);
            $company_name = trim($_POST['company_name'] ?? '');
            $company_email = trim($_POST['company_email'] ?? '');
            $company_phone = trim($_POST['company_phone'] ?? '');
            $company_website = trim($_POST['company_website'] ?? '');
            $company_address = trim($_POST['company_address'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $contact_name = trim($_POST['contact_name'] ?? '');
            $contact_title = trim($_POST['contact_title'] ?? '');
            $contact_email = trim($_POST['contact_email'] ?? '');
            $contact_phone = trim($_POST['contact_phone'] ?? '');
            $status = $_POST['status'] ?? 'active';

            if ($partner_id <= 0 || empty($company_name)) {
                header("Location: dashboard.php?page=business_partners&tab=partners&status=error&message=" . urlencode('Invalid data'));
                exit();
            }

            if (!in_array($status, ['active', 'inactive', 'pending'])) {
                $status = 'active';
            }

            $enc_contact_name = $contact_name ? FieldEncryption::encrypt($contact_name) : null;
            $enc_contact_phone = $contact_phone ? FieldEncryption::encrypt($contact_phone) : null;

            $stmt = $pdo->prepare("UPDATE business_partners SET 
                company_name = ?, company_email = ?, company_phone = ?, company_website = ?, company_address = ?,
                description = ?, contact_name = ?, contact_title = ?, contact_email = ?, contact_phone = ?, status = ?
                WHERE id = ?");
            $stmt->execute([
                $company_name, $company_email ?: null, $company_phone ?: null, $company_website ?: null,
                $company_address ?: null, $description ?: null, $enc_contact_name, $contact_title ?: null,
                $contact_email ?: null, $enc_contact_phone, $status, $partner_id
            ]);

            header("Location: dashboard.php?page=business_partners&tab=partners&status=success&message=" . urlencode('Partner updated successfully'));
            exit();

        case 'delete_partner':
            $partner_id = intval($_POST['partner_id'] ?? 0);
            if ($partner_id <= 0) {
                header("Location: dashboard.php?page=business_partners&tab=partners&status=error&message=" . urlencode('Invalid partner'));
                exit();
            }

            // Delete contracts first (cascade should handle this, but be explicit)
            $pdo->prepare("DELETE FROM partner_contracts WHERE partner_id = ?")->execute([$partner_id]);
            $pdo->prepare("DELETE FROM business_partners WHERE id = ?")->execute([$partner_id]);

            header("Location: dashboard.php?page=business_partners&tab=partners&status=success&message=" . urlencode('Partner deleted successfully'));
            exit();

        case 'create_contract':
            $partner_id = intval($_POST['partner_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $partnership_items = trim($_POST['partnership_items'] ?? '');
            $value = !empty($_POST['value']) ? floatval($_POST['value']) : null;
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $status = $_POST['status'] ?? 'active';
            $notes = trim($_POST['notes'] ?? '');

            if ($partner_id <= 0 || empty($title)) {
                header("Location: dashboard.php?page=business_partners&tab=contracts&partner_id=$partner_id&status=error&message=" . urlencode('Partner and title are required'));
                exit();
            }

            if (!in_array($status, ['active', 'pending', 'expired', 'cancelled'])) {
                $status = 'active';
            }

            $stmt = $pdo->prepare("INSERT INTO partner_contracts 
                (partner_id, title, description, partnership_items, value, start_date, end_date, status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $partner_id, $title, $description ?: null, $partnership_items ?: null,
                $value, $start_date, $end_date, $status, $notes ?: null, $user_id
            ]);

            header("Location: dashboard.php?page=business_partners&tab=contracts&partner_id=$partner_id&status=success&message=" . urlencode('Contract created successfully'));
            exit();

        case 'delete_contract':
            $contract_id = intval($_POST['contract_id'] ?? 0);
            $partner_id = intval($_POST['partner_id'] ?? 0);

            if ($contract_id <= 0) {
                header("Location: dashboard.php?page=business_partners&tab=partners&status=error&message=" . urlencode('Invalid contract'));
                exit();
            }

            $pdo->prepare("DELETE FROM partner_contracts WHERE id = ?")->execute([$contract_id]);

            header("Location: dashboard.php?page=business_partners&tab=contracts&partner_id=$partner_id&status=success&message=" . urlencode('Contract deleted successfully'));
            exit();

        default:
            header("Location: dashboard.php?page=business_partners");
            exit();
    }
} catch (PDOException $e) {
    error_log("Business partner error: " . $e->getMessage());
    header("Location: dashboard.php?page=business_partners&tab=partners&status=error&message=" . urlencode('A database error occurred'));
    exit();
}
?>
