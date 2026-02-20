<?php
// process_packages.php - Handle package CRUD operations
session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Set security headers
setSecurityHeaders();

// Check admin access
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit();
    }
    http_response_code(403);
    die('Access denied.');
}

// Handle GET request for retrieving package sessions (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_sessions') {
    $package_id = intval($_GET['package_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT session_id FROM package_sessions WHERE package_id = ?");
    $stmt->execute([$package_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    header('Content-Type: application/json');
    echo json_encode($sessions);
    exit();
}

// Handle GET request for retrieving camp schedules (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_camp_schedules') {
    $package_id = intval($_GET['package_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT * FROM camp_daily_schedules WHERE package_id = ? ORDER BY schedule_date");
    $stmt->execute([$package_id]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($schedules);
    exit();
}

// Handle GET request for retrieving camp add-ons (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_camp_addons') {
    $package_id = intval($_GET['package_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT * FROM camp_add_ons WHERE package_id = ? ORDER BY display_order");
    $stmt->execute([$package_id]);
    $addons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($addons);
    exit();
}

// Handle GET request for retrieving multi-week program dates (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_program_dates') {
    $package_id = intval($_GET['package_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT * FROM multiweek_program_dates WHERE package_id = ? ORDER BY session_date");
    $stmt->execute([$package_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($dates);
    exit();
}

// Validate CSRF token for POST requests
checkCsrfToken();

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

try {
    switch ($action) {
        case 'create':
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $price = floatval($_POST['price']);
            $credits = intval($_POST['credits'] ?? intval($_POST['session_count'] ?? 0));
            $valid_days = !empty($_POST['valid_days']) ? intval($_POST['valid_days']) : (!empty($_POST['validity_days']) ? intval($_POST['validity_days']) : null);
            $age_group = trim($_POST['age_group'] ?? '');
            $skill_level = trim($_POST['skill_level'] ?? '');
            $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
            $package_type = trim($_POST['package_type'] ?? 'credits');
            $store_credit = floatval($_POST['store_credit'] ?? 0);
            $enable_child_checkin = isset($_POST['enable_child_checkin']) ? 1 : 0;
            
            // Camp-specific fields
            $camp_start_date = !empty($_POST['camp_start_date']) ? $_POST['camp_start_date'] : null;
            $camp_end_date = !empty($_POST['camp_end_date']) ? $_POST['camp_end_date'] : null;
            $daily_start_time = !empty($_POST['daily_start_time']) ? $_POST['daily_start_time'] : null;
            $daily_end_time = !empty($_POST['daily_end_time']) ? $_POST['daily_end_time'] : null;
            $age_group_id = !empty($_POST['age_group_id']) ? intval($_POST['age_group_id']) : null;
            $skill_level_id = !empty($_POST['skill_level_id']) ? intval($_POST['skill_level_id']) : null;
            $allow_individual_sessions = isset($_POST['allow_individual_sessions']) ? 1 : 0;
            
            if (empty($name) || $price < 0) {
                throw new Exception('Invalid package data: name is required and price must be positive');
            }
            
            // Validate camp dates
            if ($package_type === 'camp' && (!$camp_start_date || !$camp_end_date)) {
                throw new Exception('Camp packages require start and end dates');
            }
            
            $pdo->beginTransaction();
            
            // Insert package with all fields
            $stmt = $pdo->prepare("
                INSERT INTO packages (name, description, price, credits, valid_days, 
                                     age_group, skill_level, is_active, package_type, store_credit, enable_child_checkin,
                                     camp_start_date, camp_end_date, daily_start_time, daily_end_time,
                                     age_group_id, skill_level_id, allow_individual_sessions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $description, $price, $credits, $valid_days,
                $age_group ?: null, $skill_level ?: null, $is_active, $package_type, $store_credit, $enable_child_checkin,
                $camp_start_date, $camp_end_date, $daily_start_time, $daily_end_time,
                $age_group_id, $skill_level_id, $allow_individual_sessions
            ]);
            
            $package_id = $pdo->lastInsertId();
            
            // Save camp daily schedules if provided
            if ($package_type === 'camp' && !empty($_POST['schedule_dates'])) {
                $sched_stmt = $pdo->prepare("
                    INSERT INTO camp_daily_schedules (package_id, schedule_date, start_time, end_time, title, description, location)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($_POST['schedule_dates'] as $i => $date) {
                    if (empty($date)) continue;
                    $s_start = $_POST['schedule_start_times'][$i] ?? ($daily_start_time ?: '09:00');
                    $s_end = $_POST['schedule_end_times'][$i] ?? ($daily_end_time ?: '17:00');
                    $s_title = $_POST['schedule_titles'][$i] ?? '';
                    $s_desc = $_POST['schedule_descriptions'][$i] ?? '';
                    $s_location = $_POST['schedule_locations'][$i] ?? '';
                    $sched_stmt->execute([$package_id, $date, $s_start, $s_end, $s_title ?: null, $s_desc ?: null, $s_location ?: null]);
                }
            }
            
            // Save camp add-ons if provided
            if (($package_type === 'camp' || $package_type === 'multi_week') && !empty($_POST['addon_names'])) {
                $addon_stmt = $pdo->prepare("
                    INSERT INTO camp_add_ons (package_id, name, description, price, is_default, display_order)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                foreach ($_POST['addon_names'] as $i => $addon_name) {
                    if (empty($addon_name)) continue;
                    $addon_desc = $_POST['addon_descriptions'][$i] ?? '';
                    $addon_price = floatval($_POST['addon_prices'][$i] ?? 0);
                    $addon_default = isset($_POST['addon_defaults'][$i]) ? 1 : 0;
                    $addon_stmt->execute([$package_id, $addon_name, $addon_desc ?: null, $addon_price, $addon_default, $i]);
                }
            }
            
            // For multi-week programs, save program dates and auto-create sessions
            if ($package_type === 'multi_week' && !empty($_POST['program_dates'])) {
                $prog_stmt = $pdo->prepare("
                    INSERT INTO multiweek_program_dates (package_id, session_date, start_time, end_time, title, individual_price, auto_session_id, location)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $session_stmt = $pdo->prepare("
                    INSERT INTO sessions (title, description, session_date, session_time, duration_minutes, price, age_group, skill_level, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
                ");
                
                foreach ($_POST['program_dates'] as $i => $pdate) {
                    if (empty($pdate)) continue;
                    $p_start = $_POST['program_start_times'][$i] ?? ($daily_start_time ?: '09:00');
                    $p_end = $_POST['program_end_times'][$i] ?? ($daily_end_time ?: '10:00');
                    $p_title = $_POST['program_titles'][$i] ?? '';
                    $p_ind_price = !empty($_POST['program_individual_prices'][$i]) ? floatval($_POST['program_individual_prices'][$i]) : null;
                    $p_location = $_POST['program_locations'][$i] ?? '';
                    
                    $auto_session_id = null;
                    // Auto-create individual sessions if individual purchase is allowed
                    if ($allow_individual_sessions && $p_ind_price !== null) {
                        $session_title = !empty($p_title) ? $p_title : ($name . ' - ' . date('M j, Y', strtotime($pdate)));
                        $start_dt = new DateTime($pdate . ' ' . $p_start);
                        $end_dt = new DateTime($pdate . ' ' . $p_end);
                        $duration = ($end_dt->getTimestamp() - $start_dt->getTimestamp()) / 60;
                        $session_stmt->execute([
                            $session_title, $description, $pdate, $p_start,
                            max(1, intval($duration)), $p_ind_price,
                            $age_group ?: null, $skill_level ?: null
                        ]);
                        $auto_session_id = $pdo->lastInsertId();
                    }
                    
                    $prog_stmt->execute([$package_id, $pdate, $p_start, $p_end, $p_title ?: null, $p_ind_price, $auto_session_id, $p_location ?: null]);
                }
            }
            
            $pdo->commit();
            Auditor::log($pdo, $user_id, 'CREATE', 'packages', $package_id, ['action' => 'Created package', 'name' => $name]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Package created successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=products&tab=packages&status=success");
            exit();
            
        case 'update':
            $package_id = intval($_POST['package_id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $price = floatval($_POST['price']);
            $credits = intval($_POST['credits'] ?? 0);
            $valid_days = !empty($_POST['valid_days']) ? intval($_POST['valid_days']) : (!empty($_POST['validity_days']) ? intval($_POST['validity_days']) : null);
            $age_group = trim($_POST['age_group'] ?? '');
            $skill_level = trim($_POST['skill_level'] ?? '');
            $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
            $package_type = trim($_POST['package_type'] ?? 'credits');
            $store_credit = floatval($_POST['store_credit'] ?? 0);
            $enable_child_checkin = isset($_POST['enable_child_checkin']) ? 1 : 0;
            
            // Camp-specific fields
            $camp_start_date = !empty($_POST['camp_start_date']) ? $_POST['camp_start_date'] : null;
            $camp_end_date = !empty($_POST['camp_end_date']) ? $_POST['camp_end_date'] : null;
            $daily_start_time = !empty($_POST['daily_start_time']) ? $_POST['daily_start_time'] : null;
            $daily_end_time = !empty($_POST['daily_end_time']) ? $_POST['daily_end_time'] : null;
            $age_group_id = !empty($_POST['age_group_id']) ? intval($_POST['age_group_id']) : null;
            $skill_level_id = !empty($_POST['skill_level_id']) ? intval($_POST['skill_level_id']) : null;
            $allow_individual_sessions = isset($_POST['allow_individual_sessions']) ? 1 : 0;
            
            if (empty($name) || $price < 0 || $package_id <= 0) {
                throw new Exception('Invalid package data');
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE packages 
                SET name = ?, description = ?, price = ?, credits = ?, 
                    valid_days = ?, age_group = ?, skill_level = ?, is_active = ?, package_type = ?, store_credit = ?, enable_child_checkin = ?,
                    camp_start_date = ?, camp_end_date = ?, daily_start_time = ?, daily_end_time = ?,
                    age_group_id = ?, skill_level_id = ?, allow_individual_sessions = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $description, $price, $credits, $valid_days,
                $age_group ?: null, $skill_level ?: null, $is_active, $package_type, $store_credit, $enable_child_checkin,
                $camp_start_date, $camp_end_date, $daily_start_time, $daily_end_time,
                $age_group_id, $skill_level_id, $allow_individual_sessions, $package_id
            ]);
            
            // Update camp daily schedules
            if ($package_type === 'camp') {
                // Remove old schedules and re-insert
                $pdo->prepare("DELETE FROM camp_daily_schedules WHERE package_id = ?")->execute([$package_id]);
                
                if (!empty($_POST['schedule_dates'])) {
                    $sched_stmt = $pdo->prepare("
                        INSERT INTO camp_daily_schedules (package_id, schedule_date, start_time, end_time, title, description, location)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    foreach ($_POST['schedule_dates'] as $i => $date) {
                        if (empty($date)) continue;
                        $s_start = $_POST['schedule_start_times'][$i] ?? ($daily_start_time ?: '09:00');
                        $s_end = $_POST['schedule_end_times'][$i] ?? ($daily_end_time ?: '17:00');
                        $s_title = $_POST['schedule_titles'][$i] ?? '';
                        $s_desc = $_POST['schedule_descriptions'][$i] ?? '';
                        $s_location = $_POST['schedule_locations'][$i] ?? '';
                        $sched_stmt->execute([$package_id, $date, $s_start, $s_end, $s_title ?: null, $s_desc ?: null, $s_location ?: null]);
                    }
                }
            }
            
            // Update add-ons (for camp and multi-week)
            if ($package_type === 'camp' || $package_type === 'multi_week') {
                $pdo->prepare("DELETE FROM camp_add_ons WHERE package_id = ?")->execute([$package_id]);
                
                if (!empty($_POST['addon_names'])) {
                    $addon_stmt = $pdo->prepare("
                        INSERT INTO camp_add_ons (package_id, name, description, price, is_default, display_order)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    foreach ($_POST['addon_names'] as $i => $addon_name) {
                        if (empty($addon_name)) continue;
                        $addon_desc = $_POST['addon_descriptions'][$i] ?? '';
                        $addon_price = floatval($_POST['addon_prices'][$i] ?? 0);
                        $addon_default = isset($_POST['addon_defaults'][$i]) ? 1 : 0;
                        $addon_stmt->execute([$package_id, $addon_name, $addon_desc ?: null, $addon_price, $addon_default, $i]);
                    }
                }
            }
            
            // Update multi-week program dates
            if ($package_type === 'multi_week') {
                // Delete auto-created sessions that are no longer needed
                $old_sessions = $pdo->prepare("SELECT auto_session_id FROM multiweek_program_dates WHERE package_id = ? AND auto_session_id IS NOT NULL");
                $old_sessions->execute([$package_id]);
                $old_session_ids = $old_sessions->fetchAll(PDO::FETCH_COLUMN);
                
                $pdo->prepare("DELETE FROM multiweek_program_dates WHERE package_id = ?")->execute([$package_id]);
                
                // Clean up auto-created sessions (only if no bookings exist)
                if (!empty($old_session_ids)) {
                    $placeholders = str_repeat('?,', count($old_session_ids) - 1) . '?';
                    // Only delete sessions that have no bookings
                    $pdo->prepare("DELETE FROM sessions WHERE id IN ($placeholders) AND id NOT IN (SELECT session_id FROM session_bookings WHERE session_id IN ($placeholders))")->execute(array_merge($old_session_ids, $old_session_ids));
                }
                
                if (!empty($_POST['program_dates'])) {
                    $prog_stmt = $pdo->prepare("
                        INSERT INTO multiweek_program_dates (package_id, session_date, start_time, end_time, title, individual_price, auto_session_id, location)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $session_stmt = $pdo->prepare("
                        INSERT INTO sessions (title, description, session_date, session_time, duration_minutes, price, age_group, skill_level, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
                    ");
                    
                    foreach ($_POST['program_dates'] as $i => $pdate) {
                        if (empty($pdate)) continue;
                        $p_start = $_POST['program_start_times'][$i] ?? ($daily_start_time ?: '09:00');
                        $p_end = $_POST['program_end_times'][$i] ?? ($daily_end_time ?: '10:00');
                        $p_title = $_POST['program_titles'][$i] ?? '';
                        $p_ind_price = !empty($_POST['program_individual_prices'][$i]) ? floatval($_POST['program_individual_prices'][$i]) : null;
                        $p_location = $_POST['program_locations'][$i] ?? '';
                        
                        $auto_session_id = null;
                        if ($allow_individual_sessions && $p_ind_price !== null) {
                            $session_title = !empty($p_title) ? $p_title : ($name . ' - ' . date('M j, Y', strtotime($pdate)));
                            $start_dt = new DateTime($pdate . ' ' . $p_start);
                            $end_dt = new DateTime($pdate . ' ' . $p_end);
                            $duration = ($end_dt->getTimestamp() - $start_dt->getTimestamp()) / 60;
                            $session_stmt->execute([
                                $session_title, $description, $pdate, $p_start,
                                max(1, intval($duration)), $p_ind_price,
                                $age_group ?: null, $skill_level ?: null
                            ]);
                            $auto_session_id = $pdo->lastInsertId();
                        }
                        
                        $prog_stmt->execute([$package_id, $pdate, $p_start, $p_end, $p_title ?: null, $p_ind_price, $auto_session_id, $p_location ?: null]);
                    }
                }
            }
            
            $pdo->commit();
            Auditor::log($pdo, $user_id, 'UPDATE', 'packages', $package_id, ['action' => 'Updated package', 'name' => $name]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Package updated successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=products&tab=packages&status=success");
            exit();
            
        case 'delete':
            $package_id = intval($_POST['package_id']);
            
            // Check if package has been purchased (check both tables for safety)
            $check = $pdo->prepare("SELECT COUNT(*) FROM user_packages WHERE package_id = ?");
            $check->execute([$package_id]);
            
            if ($check->fetchColumn() > 0) {
                throw new Exception('Cannot delete package with existing purchases');
            }
            
            // Delete package
            $stmt = $pdo->prepare("DELETE FROM packages WHERE id = ?");
            $stmt->execute([$package_id]);
            Auditor::log($pdo, $user_id, 'DELETE', 'packages', $package_id, ['action' => 'Deleted package']);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Package deleted successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=products&tab=packages&status=success&action=delete");
            exit();
            
        case 'update_sessions':
            // Save session assignments for a bundled package
            $package_id = intval($_POST['package_id'] ?? 0);
            $session_ids = $_POST['session_ids'] ?? [];
            
            if ($package_id <= 0) {
                throw new Exception('Invalid package ID');
            }
            
            // Verify package exists
            $check = $pdo->prepare("SELECT id FROM packages WHERE id = ?");
            $check->execute([$package_id]);
            if (!$check->fetch()) {
                throw new Exception('Package not found');
            }
            
            $pdo->beginTransaction();
            
            // Remove existing session links
            $delete_stmt = $pdo->prepare("DELETE FROM package_sessions WHERE package_id = ?");
            $delete_stmt->execute([$package_id]);
            
            // Insert new session links
            if (!empty($session_ids) && is_array($session_ids)) {
                $insert_stmt = $pdo->prepare("INSERT INTO package_sessions (package_id, session_id) VALUES (?, ?)");
                foreach ($session_ids as $sid) {
                    $sid = intval($sid);
                    if ($sid > 0) {
                        // Verify session exists before inserting
                        $verify = $pdo->prepare("SELECT id FROM sessions WHERE id = ?");
                        $verify->execute([$sid]);
                        if ($verify->fetch()) {
                            $insert_stmt->execute([$package_id, $sid]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            Auditor::log($pdo, $user_id, 'UPDATE', 'package_sessions', $package_id, ['action' => 'Updated package sessions']);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Package sessions updated successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=products&tab=packages&status=success");
            exit();
            
        case 'toggle_status':
            header('Content-Type: application/json');
            $package_id = intval($_POST['id'] ?? $_POST['package_id'] ?? 0);
            
            if ($package_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid package ID']);
                exit();
            }
            
            // Get current status
            $stmt = $pdo->prepare("SELECT is_active FROM packages WHERE id = ?");
            $stmt->execute([$package_id]);
            $package = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$package) {
                echo json_encode(['success' => false, 'message' => 'Package not found']);
                exit();
            }
            
            $new_status = $package['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE packages SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $package_id]);
            Auditor::log($pdo, $user_id, 'UPDATE', 'packages', $package_id, ['action' => 'Toggled package status', 'new_status' => $new_status]);
            
            echo json_encode(['success' => true, 'message' => 'Package status updated']);
            exit();
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    ErrorLogger::error("Package processing error: " . $e->getMessage());
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
    header("Location: dashboard.php?page=products&status=error");
    exit();
}
?>
