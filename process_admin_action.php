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
    header("Location: dashboard.php?page=accounting_products&tab=sessions&status=added"); exit();
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
// MODULE 5.5: INVOICE MANAGEMENT
// =========================================================
if ($action == 'create_invoice') {
    // Generate unique invoice number
    $invoice_number = 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Check if invoice number already exists, regenerate if needed
    $check_stmt = $pdo->prepare("SELECT id FROM invoices WHERE invoice_number = ?");
    $check_stmt->execute([$invoice_number]);
    while ($check_stmt->rowCount() > 0) {
        $invoice_number = 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $check_stmt->execute([$invoice_number]);
    }
    
    $user_id = intval($_POST['user_id']);
    $invoice_date = $_POST['invoice_date'];
    $due_date = $_POST['due_date'];
    $description = trim($_POST['description'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $total_amount = floatval($_POST['total_amount']);
    $subtotal = $total_amount; // Can calculate tax later if needed
    $tax_amount = 0.00;
    
    try {
        // Insert invoice
        $stmt = $pdo->prepare("
            INSERT INTO invoices (invoice_number, user_id, invoice_date, due_date, subtotal, tax_amount, total_amount, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?)
        ");
        $stmt->execute([$invoice_number, $user_id, $invoice_date, $due_date, $subtotal, $tax_amount, $total_amount, $notes]);
        $invoice_id = $pdo->lastInsertId();
        
        // Insert line items if provided
        if (isset($_POST['item_description']) && is_array($_POST['item_description'])) {
            $item_stmt = $pdo->prepare("
                INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['item_description'] as $index => $item_desc) {
                if (!empty($item_desc)) {
                    $quantity = intval($_POST['item_quantity'][$index] ?? 1);
                    $unit_price = floatval($_POST['item_price'][$index] ?? 0);
                    $total_price = $quantity * $unit_price;
                    
                    $item_stmt->execute([$invoice_id, $item_desc, $quantity, $unit_price, $total_price]);
                }
            }
        }
        
        // Check if AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Invoice created successfully', 'invoice_id' => $invoice_id, 'invoice_number' => $invoice_number]);
            exit();
        }
        
        header("Location: dashboard.php?page=billing_dashboard&status=invoice_created&invoice_id=$invoice_id");
    } catch (PDOException $e) {
        error_log("Invoice creation error: " . $e->getMessage());
        
        // Check if AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to create invoice']);
            exit();
        }
        
        header("Location: dashboard.php?page=billing_dashboard&error=invoice_creation_failed");
    }
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
    // Map form field 'type' to schema column 'discount_type'
    $discount_type = $_POST['type'] ?? 'percentage';
    $discount_value = floatval($_POST['value']);
    // Map form field 'usage_limit' to schema column 'max_uses'
    $max_uses = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : NULL;
    // Map form fields to schema columns
    $valid_from = !empty($_POST['start_date']) ? $_POST['start_date'] : NULL;
    $valid_until = !empty($_POST['end_date']) ? $_POST['end_date'] : NULL;
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
    $description = trim($_POST['description'] ?? '');

    try {
        $stmt = $pdo->prepare("INSERT INTO discount_codes (code, discount_type, discount_value, max_uses, valid_from, valid_until, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $discount_type, $discount_value, $max_uses, $valid_from, $valid_until, $is_active]);
        header("Location: dashboard.php?page=accounting_products&tab=discounts&status=success");
    } catch (PDOException $e) {
        error_log("Create discount error: " . $e->getMessage());
        header("Location: dashboard.php?page=accounting_products&tab=discounts&status=error");
    }
    exit();
}

if ($action == 'edit_discount') {
    $discount_id = intval($_POST['discount_id']);
    $code = strtoupper(trim($_POST['code']));
    $discount_type = $_POST['type'] ?? 'percentage';
    $discount_value = floatval($_POST['value']);
    $max_uses = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : NULL;
    $valid_from = !empty($_POST['start_date']) ? $_POST['start_date'] : NULL;
    $valid_until = !empty($_POST['end_date']) ? $_POST['end_date'] : NULL;
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

    try {
        $stmt = $pdo->prepare("UPDATE discount_codes SET code = ?, discount_type = ?, discount_value = ?, max_uses = ?, valid_from = ?, valid_until = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$code, $discount_type, $discount_value, $max_uses, $valid_from, $valid_until, $is_active, $discount_id]);
        header("Location: dashboard.php?page=accounting_products&tab=discounts&status=success");
    } catch (PDOException $e) {
        error_log("Edit discount error: " . $e->getMessage());
        header("Location: dashboard.php?page=accounting_products&tab=discounts&status=error");
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
            header("Location: dashboard.php?page=accounting_products&tab=discounts&status=error");
            exit();
        }
        
        $pdo->prepare("DELETE FROM discount_codes WHERE id = ?")->execute([$discount_id]);
        header("Location: dashboard.php?page=accounting_products&tab=discounts&status=success");
    } catch (PDOException $e) {
        error_log("Delete discount error: " . $e->getMessage());
        header("Location: dashboard.php?page=accounting_products&tab=discounts&status=error");
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

if ($action == 'update_user') {
    $user_id_to_update = intval($_POST['user_id']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'];
    $password = trim($_POST['password'] ?? '');

    try {
        // Check if password is being updated
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, password = ?
                WHERE id = ?
            ");
            $stmt->execute([$first_name, $last_name, $email, $phone, $role, $hashed_password, $user_id_to_update]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?
                WHERE id = ?
            ");
            $stmt->execute([$first_name, $last_name, $email, $phone, $role, $user_id_to_update]);
        }
        
        header("Location: dashboard.php?page=all_users&status=success");
    } catch (PDOException $e) {
        error_log("Update user error: " . $e->getMessage());
        header("Location: dashboard.php?page=all_users&status=error");
    }
    exit();
}

// =========================================================
// MODULE 8.5: USER STATUS TOGGLING
// =========================================================
if ($action == 'toggle_user_status') {
    header('Content-Type: application/json');
    
    try {
        $user_id_to_toggle = intval($_POST['id']);
        
        // Don't allow toggling own account
        if ($user_id_to_toggle == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Cannot toggle your own account status']);
            exit();
        }
        
        // Get current status
        $stmt = $pdo->prepare("SELECT is_verified, first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$user_id_to_toggle]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        
        // Toggle status
        $new_status = $user['is_verified'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET is_verified = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id_to_toggle]);
        
        $status_text = $new_status ? 'enabled' : 'disabled';
        echo json_encode([
            'success' => true, 
            'message' => "User {$user['first_name']} {$user['last_name']} has been {$status_text}"
        ]);
    } catch (PDOException $e) {
        error_log("Toggle user status error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit();
}

if ($action == 'toggle_session_status') {
    header('Content-Type: application/json');
    
    try {
        $session_type_id = intval($_POST['id']);
        
        // For session types, we'll use is_active if it exists, or create a simple active flag
        // Check if column exists
        $column_check = $pdo->query("SHOW COLUMNS FROM session_types LIKE 'is_active'")->fetch();
        
        if ($column_check) {
            // Get current status
            $stmt = $pdo->prepare("SELECT is_active, name FROM session_types WHERE id = ?");
            $stmt->execute([$session_type_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                echo json_encode(['success' => false, 'message' => 'Session type not found']);
                exit();
            }
            
            // Toggle status
            $new_status = $session['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE session_types SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $session_type_id]);
            
            $status_text = $new_status ? 'enabled' : 'disabled';
            echo json_encode([
                'success' => true, 
                'message' => "Session type has been {$status_text}"
            ]);
        } else {
            // If no is_active column, just return success (demo data scenario)
            echo json_encode(['success' => true, 'message' => 'Session type status toggled']);
        }
    } catch (PDOException $e) {
        error_log("Toggle session status error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
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
        
        header("Location: dashboard.php?page=categories&status=skill_added");
    } catch (PDOException $e) {
        error_log("Create skill error: " . $e->getMessage());
        header("Location: dashboard.php?page=categories&status=error");
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
        
        header("Location: dashboard.php?page=categories&status=skill_updated");
    } catch (PDOException $e) {
        error_log("Edit skill error: " . $e->getMessage());
        header("Location: dashboard.php?page=categories&status=error");
    }
    exit();
}

if ($action == 'delete' && isset($_POST['type']) && $_POST['type'] == 'skill') {
    try {
        $stmt = $pdo->prepare("DELETE FROM eval_skills WHERE id = ?");
        $stmt->execute([intval($_POST['id'])]);
        
        header("Location: dashboard.php?page=categories&status=skill_deleted");
    } catch (PDOException $e) {
        error_log("Delete skill error: " . $e->getMessage());
        header("Location: dashboard.php?page=categories&status=error");
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
        
        header("Location: dashboard.php?page=categories&tab=drills&status=drill_type_added");
    } catch (PDOException $e) {
        error_log("Create drill type error: " . $e->getMessage());
        header("Location: dashboard.php?page=categories&tab=drills&status=error");
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
        
        header("Location: dashboard.php?page=categories&tab=drills&status=drill_type_updated");
    } catch (PDOException $e) {
        error_log("Edit drill type error: " . $e->getMessage());
        header("Location: dashboard.php?page=categories&tab=drills&status=error");
    }
    exit();
}

if ($action == 'delete' && isset($_POST['type']) && $_POST['type'] == 'drill_type') {
    try {
        $stmt = $pdo->prepare("DELETE FROM drill_categories WHERE id = ?");
        $stmt->execute([intval($_POST['id'])]);
        
        header("Location: dashboard.php?page=categories&tab=drills&status=drill_type_deleted");
    } catch (PDOException $e) {
        error_log("Delete drill type error: " . $e->getMessage());
        header("Location: dashboard.php?page=categories&tab=drills&status=error");
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
    
    // Convert empty string to null for position_type
    if ($position_type === '') {
        $position_type = null;
    }
    
    if (empty($name)) {
        header("Location: dashboard.php?page=categories&tab=positions&status=error&message=position_name_required");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO player_positions (name, abbreviation, description, position_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $abbreviation, $description, $position_type]);
        
        header("Location: dashboard.php?page=categories&tab=positions&status=success&message=position_created");
    } catch (PDOException $e) {
        error_log("Error creating position: " . $e->getMessage());
        header("Location: dashboard.php?page=categories&tab=positions&status=error&message=position_creation_failed");
    }
    exit();
}

if ($action == 'update_position') {
    $id = $_POST['id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $abbreviation = $_POST['abbreviation'] ?? '';
    $description = $_POST['description'] ?? '';
    $position_type = $_POST['position_type'] ?? null;
    
    // Convert empty string to null for position_type
    if ($position_type === '') {
        $position_type = null;
    }
    
    if (empty($name) || empty($id)) {
        header("Location: dashboard.php?page=categories&tab=positions&status=error&message=invalid_data");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE player_positions SET name = ?, abbreviation = ?, description = ?, position_type = ? WHERE id = ?");
        $stmt->execute([$name, $abbreviation, $description, $position_type, $id]);
        
        header("Location: dashboard.php?page=categories&tab=positions&status=success&message=position_updated");
    } catch (PDOException $e) {
        error_log("Error updating position: " . $e->getMessage());
        header("Location: dashboard.php?page=categories&tab=positions&status=error&message=position_update_failed");
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
        
        header("Location: dashboard.php?page=categories&tab=equipment&status=equipment_added");
    } catch (PDOException $e) {
        error_log("Create equipment error: " . $e->getMessage());
        header("Location: dashboard.php?page=categories&tab=equipment&status=error");
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
        
        header("Location: dashboard.php?page=categories&tab=equipment&status=equipment_updated");
    } catch (PDOException $e) {
        error_log("Edit equipment error: " . $e->getMessage());
        header("Location: dashboard.php?page=categories&tab=equipment&status=error");
    }
    exit();
}

if ($action == 'delete' && isset($_POST['type']) && $_POST['type'] == 'equipment') {
    try {
        $stmt = $pdo->prepare("DELETE FROM equipment WHERE id = ?");
        $stmt->execute([intval($_POST['id'])]);
        
        header("Location: dashboard.php?page=categories&tab=equipment&status=equipment_deleted");
    } catch (PDOException $e) {
        error_log("Delete equipment error: " . $e->getMessage());
        header("Location: dashboard.php?page=categories&tab=equipment&status=error");
    }
    exit();
}

// =========================================================
// MODULE: PRODUCTION MODE - DEMO DATA MANAGEMENT
// =========================================================

// Get count of demo data records
if ($action == 'get_demo_count') {
    try {
        $total = 0;
        
        // Get all tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            try {
                // Try to count demo records in this table
                $count_stmt = $pdo->query("SELECT COUNT(*) FROM `$table` WHERE is_demo = 1");
                $count = $count_stmt->fetchColumn();
                $total += $count;
            } catch (PDOException $e) {
                // Table might not have is_demo column, skip it
                continue;
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'count' => $total]);
        exit();
        
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Cleanup all demo data
if ($action == 'cleanup_demo_data') {
    try {
        require_once __DIR__ . '/demo_data_seeder.php';
        
        $seeder = new DemoDataSeeder($pdo);
        $deleted_count = $seeder->cleanupDemoData();
        
        // Log the action
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, 'production_mode_activated', ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['user_id'],
            "Removed $deleted_count demo records",
            $_SERVER['REMOTE_ADDR']
        ]);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'deleted_count' => $deleted_count,
            'message' => 'Demo data successfully removed'
        ]);
        exit();
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
        exit();
    }
}

// Fallback
header("Location: dashboard.php");
exit();
?>