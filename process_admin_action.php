<?php
// process_admin_action.php
session_start();
require 'db_config.php';

// ENABLE DEBUGGING
ini_set('display_errors', 1); 
error_reporting(E_ALL);

// 1. STRICT SECURITY CHECK: Admins Only
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header("Location: dashboard.php"); 
    exit();
}

$action = $_POST['action'] ?? '';

// =========================================================
// MODULE 1: LOCATION MANAGEMENT
// =========================================================
if ($action == 'add_location') {
    $pdo->prepare("INSERT INTO locations (name, city) VALUES (?, ?)")->execute([trim($_POST['name']), trim($_POST['city'])]);
    header("Location: dashboard.php?page=admin_locations&status=added"); exit();
}
if ($action == 'delete_location') {
    $pdo->prepare("DELETE FROM locations WHERE id = ?")->execute([$_POST['id']]);
    header("Location: dashboard.php?page=admin_locations&status=deleted"); exit();
}

// =========================================================
// MODULE 2: SESSION TYPES
// =========================================================
if ($action == 'add_type') {
    $pdo->prepare("INSERT INTO session_types (name, description) VALUES (?, ?)")->execute([trim($_POST['name']), trim($_POST['desc'])]);
    header("Location: dashboard.php?page=admin_session_types&status=added"); exit();
}
if ($action == 'create_session_type') {
    // Full session type creation with pricing and details
    // Note: max_participants and is_active from form are ignored as they don't exist in session_types schema
    // max_participants is a per-session field (in sessions table), not a session type field
    $stmt = $pdo->prepare("INSERT INTO session_types (name, description, default_price, duration_minutes) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        trim($_POST['name']), 
        trim($_POST['description'] ?? ''),
        floatval($_POST['price'] ?? 0),
        intval($_POST['duration'] ?? 60)
    ]);
    header("Location: dashboard.php?page=accounting_products&status=added"); exit();
}
if ($action == 'delete_type') {
    $pdo->prepare("DELETE FROM session_types WHERE id = ?")->execute([$_POST['id']]);
    header("Location: dashboard.php?page=admin_session_types&status=deleted"); exit();
}

// =========================================================
// MODULE 3: USER ROLES
// =========================================================
if ($action == 'update_role') {
    if ($_POST['user_id'] != $_SESSION['user_id']) {
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$_POST['new_role'], $_POST['user_id']]);
        header("Location: dashboard.php?page=athletes&status=role_updated");
    } else {
        header("Location: dashboard.php?page=athletes&error=cannot_change_self");
    }
    exit();
}

// =========================================================
// MODULE 4: EMAIL SERVER (SMTP)
// =========================================================
if ($action == 'update_smtp') {
    $keys = ['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_pass', 'smtp_from_email', 'smtp_from_name'];
    try {
        $del = $pdo->prepare("DELETE FROM system_settings WHERE setting_key = ?");
        $ins = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($keys as $k) {
            $val = $_POST[$k] ?? '';
            $del->execute([$k]);
            $ins->execute([$k, $val]);
        }
        header("Location: dashboard.php?page=settings&status=settings_updated");
    } catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }
    exit();
}

// =========================================================
// MODULE 5: BILLING SETTINGS (Stripe)
// =========================================================
if ($action == 'update_billing') {
    $keys = ['stripe_publishable_key', 'stripe_secret_key', 'currency'];
    try {
        $del = $pdo->prepare("DELETE FROM system_settings WHERE setting_key = ?");
        $ins = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($keys as $k) {
            $val = $_POST[$k] ?? '';
            $del->execute([$k]);
            $ins->execute([$k, $val]);
        }
        header("Location: dashboard.php?page=settings&status=settings_updated");
    } catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }
    exit();
}

// =========================================================
// MODULE 6: DISCOUNT CODES
// =========================================================
if ($action == 'add_discount') {
    $code = strtoupper(trim($_POST['code']));
    $type = $_POST['type']; // percent or fixed
    $val  = $_POST['value'];
    $lim  = $_POST['limit'];
    $exp  = !empty($_POST['expiry']) ? $_POST['expiry'] : NULL;

    try {
        $stmt = $pdo->prepare("INSERT INTO discount_codes (code, type, value, usage_limit, expiry_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$code, $type, $val, $lim, $exp]);
        header("Location: dashboard.php?page=admin_discounts&status=added");
    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    exit();
}

if ($action == 'create_discount') {
    $code = strtoupper(trim($_POST['code']));
    $type = $_POST['type']; // percent or fixed
    $value = floatval($_POST['value']);
    $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : NULL;
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;

    try {
        $stmt = $pdo->prepare("INSERT INTO discount_codes (code, type, value, usage_limit, expiry_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$code, $type, $value, $usage_limit, $expiry_date]);
        header("Location: dashboard.php?page=admin_discounts&status=success");
    } catch (PDOException $e) {
        error_log("Create discount error: " . $e->getMessage());
        header("Location: dashboard.php?page=admin_discounts&status=error");
    }
    exit();
}

if ($action == 'edit_discount') {
    $discount_id = intval($_POST['discount_id']);
    $code = strtoupper(trim($_POST['code']));
    $type = $_POST['type'];
    $value = floatval($_POST['value']);
    $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : NULL;
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;

    try {
        $stmt = $pdo->prepare("UPDATE discount_codes SET code = ?, type = ?, value = ?, usage_limit = ?, expiry_date = ? WHERE id = ?");
        $stmt->execute([$code, $type, $value, $usage_limit, $expiry_date, $discount_id]);
        header("Location: dashboard.php?page=admin_discounts&status=success");
    } catch (PDOException $e) {
        error_log("Edit discount error: " . $e->getMessage());
        header("Location: dashboard.php?page=admin_discounts&status=error");
    }
    exit();
}

if ($action == 'delete_discount') {
    $discount_id = intval($_POST['discount_id']);
    try {
        // Verify discount exists before deletion
        $stmt = $pdo->prepare("SELECT 1 FROM discount_codes WHERE id = ? LIMIT 1");
        $stmt->execute([$discount_id]);
        if (!$stmt->fetch()) {
            error_log("Delete discount error: Discount ID $discount_id not found");
            header("Location: dashboard.php?page=admin_discounts&status=error");
            exit();
        }
        
        $pdo->prepare("DELETE FROM discount_codes WHERE id = ?")->execute([$discount_id]);
        header("Location: dashboard.php?page=admin_discounts&status=success");
    } catch (PDOException $e) {
        error_log("Delete discount error: " . $e->getMessage());
        header("Location: dashboard.php?page=admin_discounts&status=error");
    }
    exit();
}

// =========================================================
// MODULE 7: DIAGNOSTIC & RESEND
// =========================================================
if ($action == 'test_email') {
    require 'mailer.php';
    $res = sendEmail($_POST['test_recipient'], 'test', []);
    header("Location: dashboard.php?page=settings&test_status=" . ($res ? 'success' : 'failed'));
    exit();
}

if ($action == 'resend_email') {
    require 'mailer.php';
    $stmt = $pdo->prepare("SELECT * FROM email_logs WHERE id = ?");
    $stmt->execute([$_POST['log_id']]);
    $log = $stmt->fetch();
    
    if ($log) {
        $data = json_decode($log['log_data'], true) ?? [];
        sendEmail($log['recipient'], $log['template_type'], $data);
        header("Location: dashboard.php?page=admin_email_reports&status=resent");
    } else {
        header("Location: dashboard.php?page=admin_email_reports&error=not_found");
    }
    exit();
}

// =========================================================
// MODULE 8: USER MANAGEMENT
// =========================================================
if ($action == 'create_user') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'];
    $is_verified = intval($_POST['is_verified'] ?? 1);
    $password = $_POST['password'];

    try {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $force_pass_change = 1; // Require password change on first login
        
        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password, first_name, last_name, role, phone, is_verified, force_pass_change, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$email, $hashed_password, $first_name, $last_name, $role, $phone, $is_verified, $force_pass_change]);
        
        header("Location: dashboard.php?page=all_users&status=success");
    } catch (PDOException $e) {
        error_log("Create user error: " . $e->getMessage());
        header("Location: dashboard.php?page=all_users&status=error");
    }
    exit();
}

if ($action == 'export') {
    try {
        // Fetch all users
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.role, 
                   u.is_verified, u.created_at,
                   COUNT(DISTINCT s.id) as session_count
            FROM users u
            LEFT JOIN sessions s ON u.id = s.coach_id
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Set CSV headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        fputcsv($output, ['ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Role', 'Status', 'Sessions', 'Created']);
        
        // Write data
        foreach ($users as $user) {
            fputcsv($output, [
                $user['id'],
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['phone'] ?? '',
                ucfirst($user['role']),
                $user['is_verified'] ? 'Active' : 'Inactive',
                $user['session_count'],
                date('Y-m-d', strtotime($user['created_at']))
            ]);
        }
        
        fclose($output);
        exit();
    } catch (PDOException $e) {
        error_log("Export users error: " . $e->getMessage());
        header("Location: dashboard.php?page=all_users&status=export_error");
        exit();
    }
}

// =========================================================
// MODULE 9: CATEGORY MANAGEMENT (Skills, Drill Types, Positions, Equipment)
// =========================================================

// Constants for category management
define('DEFAULT_EVAL_CATEGORY', 'General');
define('EQUIPMENT_TYPE_CATEGORY', 'category');
define('CATEGORY_DEFAULT_QUANTITY', 0);

// === SKILLS MANAGEMENT ===
if ($action == 'create_skill') {
    try {
        // Skills are evaluation skills tied to categories
        // For simplicity, we'll use a default category or create general category
        // Check if a general/uncategorized category exists
        $stmt = $pdo->prepare("SELECT id FROM eval_categories WHERE name = ? LIMIT 1");
        $stmt->execute([DEFAULT_EVAL_CATEGORY]);
        $category = $stmt->fetch();
        
        if (!$category) {
            // Create a General category if it doesn't exist
            $stmt = $pdo->prepare("INSERT INTO eval_categories (name, description) VALUES (?, ?)");
            $stmt->execute([DEFAULT_EVAL_CATEGORY, 'General evaluation skills']);
            $category_id = $pdo->lastInsertId();
        } else {
            $category_id = $category['id'];
        }
        
        // Now create the skill
        $stmt = $pdo->prepare("INSERT INTO eval_skills (category_id, name, description) VALUES (?, ?, ?)");
        $stmt->execute([
            $category_id,
            trim($_POST['name']),
            trim($_POST['description'] ?? '')
        ]);
        
        header("Location: dashboard.php?page=admin_categories&status=skill_added");
    } catch (PDOException $e) {
        error_log("Create skill error: " . $e->getMessage());
        header("Location: dashboard.php?page=admin_categories&status=error");
    }
    exit();
}

if ($action == 'edit' && isset($_POST['type']) && $_POST['type'] == 'skill') {
    try {
        $stmt = $pdo->prepare("UPDATE eval_skills SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([
            trim($_POST['name']),
            trim($_POST['description'] ?? ''),
            intval($_POST['id'])
        ]);
        
        header("Location: dashboard.php?page=admin_categories&status=skill_updated");
    } catch (PDOException $e) {
        error_log("Edit skill error: " . $e->getMessage());
        header("Location: dashboard.php?page=admin_categories&status=error");
    }
    exit();
}

if ($action == 'delete' && isset($_POST['type']) && $_POST['type'] == 'skill') {
    try {
        $stmt = $pdo->prepare("DELETE FROM eval_skills WHERE id = ?");
        $stmt->execute([intval($_POST['id'])]);
        
        header("Location: dashboard.php?page=admin_categories&status=skill_deleted");
    } catch (PDOException $e) {
        error_log("Delete skill error: " . $e->getMessage());
        header("Location: dashboard.php?page=admin_categories&status=error");
    }
    exit();
}

// === DRILL TYPES MANAGEMENT ===
if ($action == 'create_drill_type') {
    try {
        $stmt = $pdo->prepare("INSERT INTO drill_categories (name, description) VALUES (?, ?)");
        $stmt->execute([
            trim($_POST['name']),
            trim($_POST['description'] ?? '')
        ]);
        
        header("Location: dashboard.php?page=admin_categories&status=drill_type_added");
    } catch (PDOException $e) {
        error_log("Create drill type error: " . $e->getMessage());
        header("Location: dashboard.php?page=admin_categories&status=error");
    }
    exit();
}

if ($action == 'edit' && isset($_POST['type']) && $_POST['type'] == 'drill_type') {
    try {
        $stmt = $pdo->prepare("UPDATE drill_categories SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([
            trim($_POST['name']),
            trim($_POST['description'] ?? ''),
            intval($_POST['id'])
        ]);
        
        header("Location: dashboard.php?page=admin_categories&status=drill_type_updated");
    } catch (PDOException $e) {
        error_log("Edit drill type error: " . $e->getMessage());
        header("Location: dashboard.php?page=admin_categories&status=error");
    }
    exit();
}

if ($action == 'delete' && isset($_POST['type']) && $_POST['type'] == 'drill_type') {
    try {
        $stmt = $pdo->prepare("DELETE FROM drill_categories WHERE id = ?");
        $stmt->execute([intval($_POST['id'])]);
        
        header("Location: dashboard.php?page=admin_categories&status=drill_type_deleted");
    } catch (PDOException $e) {
        error_log("Delete drill type error: " . $e->getMessage());
        header("Location: dashboard.php?page=admin_categories&status=error");
    }
    exit();
}

// === POSITIONS MANAGEMENT ===
// Manages player positions (Forward, Defense, Goalie variations)
if ($action == 'create_position') {
    $name = $_POST['name'] ?? '';
    $abbreviation = $_POST['abbreviation'] ?? '';
    $description = $_POST['description'] ?? '';
    $position_type = $_POST['position_type'] ?? null;
    
    if (empty($name)) {
        header("Location: dashboard.php?page=admin_categories&status=error&message=position_name_required");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO player_positions (name, abbreviation, description, position_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $abbreviation, $description, $position_type]);
        
        header("Location: dashboard.php?page=admin_categories&status=success&message=position_created");
    } catch (PDOException $e) {
        error_log("Error creating position: " . $e->getMessage());
        header("Location: dashboard.php?page=admin_categories&status=error&message=position_creation_failed");
    }
    exit();
}

if ($action == 'update_position') {
    $id = $_POST['id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $abbreviation = $_POST['abbreviation'] ?? '';
    $description = $_POST['description'] ?? '';
    $position_type = $_POST['position_type'] ?? null;
    
    if (empty($name) || empty($id)) {
        header("Location: dashboard.php?page=admin_categories&status=error&message=invalid_data");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE player_positions SET name = ?, abbreviation = ?, description = ?, position_type = ? WHERE id = ?");
        $stmt->execute([$name, $abbreviation, $description, $position_type, $id]);
        
        header("Location: dashboard.php?page=admin_categories&status=success&message=position_updated");
    } catch (PDOException $e) {
        error_log("Error updating position: " . $e->getMessage());
        header("Location: dashboard.php?page=admin_categories&status=error&message=position_update_failed");
    }
    exit();
}

if ($action == 'delete_position') {
    $id = $_POST['id'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid position ID']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM player_positions WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Position deleted successfully']);
    } catch (PDOException $e) {
        error_log("Error deleting position: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to delete position']);
    }
    exit();
}

// === EQUIPMENT MANAGEMENT ===
// Note: The equipment table is designed for inventory tracking, not category management
// This might need clarification on whether we want equipment categories or equipment items
if ($action == 'create_equipment') {
    try {
        // Using the equipment table for basic equipment type storage
        // Setting default values for inventory fields
        $stmt = $pdo->prepare("INSERT INTO equipment (name, equipment_type, quantity, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            trim($_POST['name']),
            EQUIPMENT_TYPE_CATEGORY, // Mark this as a category type
            CATEGORY_DEFAULT_QUANTITY,  // No quantity for category items
            trim($_POST['description'] ?? '')
        ]);
        
        header("Location: dashboard.php?page=admin_categories&status=equipment_added");
    } catch (PDOException $e) {
        error_log("Create equipment error: " . $e->getMessage());
        header("Location: dashboard.php?page=admin_categories&status=error");
    }
    exit();
}

if ($action == 'edit' && isset($_POST['type']) && $_POST['type'] == 'equipment') {
    try {
        $stmt = $pdo->prepare("UPDATE equipment SET name = ?, notes = ? WHERE id = ?");
        $stmt->execute([
            trim($_POST['name']),
            trim($_POST['description'] ?? ''),
            intval($_POST['id'])
        ]);
        
        header("Location: dashboard.php?page=admin_categories&status=equipment_updated");
    } catch (PDOException $e) {
        error_log("Edit equipment error: " . $e->getMessage());
        header("Location: dashboard.php?page=admin_categories&status=error");
    }
    exit();
}

if ($action == 'delete' && isset($_POST['type']) && $_POST['type'] == 'equipment') {
    try {
        $stmt = $pdo->prepare("DELETE FROM equipment WHERE id = ?");
        $stmt->execute([intval($_POST['id'])]);
        
        header("Location: dashboard.php?page=admin_categories&status=equipment_deleted");
    } catch (PDOException $e) {
        error_log("Delete equipment error: " . $e->getMessage());
        header("Location: dashboard.php?page=admin_categories&status=error");
    }
    exit();
}

// Fallback
header("Location: dashboard.php");
exit();
?>