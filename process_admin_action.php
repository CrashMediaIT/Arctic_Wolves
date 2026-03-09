<?php
// process_admin_action.php
session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/cloud_config.php';
require_once __DIR__ . '/lib/encryption.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Disable error display for AJAX (errors go to logs only)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. STRICT SECURITY CHECK: Admins Only
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit();
    }
    header("Location: dashboard.php"); 
    exit();
}

// Handle GET requests for fetching data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    // Fetch training session data
    if ($action === 'get_session') {
        header('Content-Type: application/json');
        try {
            $sessionId = intval($_GET['id'] ?? 0);
            if ($sessionId <= 0) {
                throw new Exception('Invalid session ID');
            }
            
            $stmt = $pdo->prepare("
                SELECT tst.*, u.first_name as coach_first_name, u.last_name as coach_last_name,
                       l.name as location_name
                FROM training_session_templates tst
                LEFT JOIN users u ON tst.coach_id = u.id
                LEFT JOIN locations l ON tst.location_id = l.id
                WHERE tst.id = ?
            ");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                throw new Exception('Session not found');
            }
            $session = decryptUserRow($session);
            
            // Fetch session dates
            $datesStmt = $pdo->prepare("
                SELECT tsd.*, t.name as team_name
                FROM training_session_dates tsd
                LEFT JOIN teams t ON tsd.team_id = t.id
                WHERE tsd.template_id = ?
                ORDER BY tsd.session_date ASC
            ");
            $datesStmt->execute([$sessionId]);
            $session['dates'] = $datesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch assigned coaches from session_coaches table
            $coachesStmt = $pdo->prepare("SELECT coach_id FROM session_coaches WHERE session_id = ?");
            $coachesStmt->execute([$sessionId]);
            $coachIdList = $coachesStmt->fetchAll(PDO::FETCH_COLUMN);
            $session['coach_ids'] = implode(',', $coachIdList);
            
            echo json_encode(['success' => true, 'data' => $session]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    // Fetch package data
    if ($action === 'get_package') {
        header('Content-Type: application/json');
        try {
            $packageId = intval($_GET['id'] ?? 0);
            if ($packageId <= 0) {
                throw new Exception('Invalid package ID');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
            $stmt->execute([$packageId]);
            $package = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$package) {
                throw new Exception('Package not found');
            }
            
            // Fetch package sessions
            $sessionsStmt = $pdo->prepare("
                SELECT ps.*, tst.name as session_name
                FROM package_sessions ps
                LEFT JOIN training_session_templates tst ON ps.session_type_id = tst.id
                WHERE ps.package_id = ?
                ORDER BY ps.id ASC
            ");
            $sessionsStmt->execute([$packageId]);
            $package['sessions'] = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch package coaches
            try {
                $coachStmt = $pdo->prepare("SELECT coach_id FROM package_coaches WHERE package_id = ?");
                $coachStmt->execute([$packageId]);
                $package['coach_ids_list'] = implode(',', $coachStmt->fetchAll(PDO::FETCH_COLUMN));
            } catch (PDOException $e) {
                $package['coach_ids_list'] = '';
            }
            
            echo json_encode(['success' => true, 'data' => $package]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    // Fetch discount data
    if ($action === 'get_discount') {
        header('Content-Type: application/json');
        try {
            $discountId = intval($_GET['id'] ?? 0);
            if ($discountId <= 0) {
                throw new Exception('Invalid discount ID');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM discount_codes WHERE id = ?");
            $stmt->execute([$discountId]);
            $discount = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$discount) {
                throw new Exception('Discount not found');
            }
            
            echo json_encode(['success' => true, 'data' => $discount]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    // Fetch merchandise product data
    if ($action === 'get_merchandise_product') {
        header('Content-Type: application/json');
        try {
            $productId = intval($_GET['id'] ?? 0);
            if ($productId <= 0) {
                throw new Exception('Invalid product ID');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM merchandise_products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new Exception('Merchandise product not found');
            }
            
            // Also fetch sizes for this product
            $sizesStmt = $pdo->prepare("SELECT id, size, quantity FROM merchandise_product_sizes WHERE product_id = ? ORDER BY id ASC");
            $sizesStmt->execute([$productId]);
            $product['sizes'] = $sizesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $product]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    // Get user 2FA status (for admin security modal)
    if ($action === 'get_user_2fa_status') {
        header('Content-Type: application/json');
        try {
            $userId = intval($_GET['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new Exception('Invalid user ID');
            }
            
            $tfa_enabled = false;
            $tfa_required = false;
            
            try {
                $stmt = $pdo->prepare("SELECT is_enabled FROM two_factor_auth WHERE user_id = ? AND is_enabled = 1");
                $stmt->execute([$userId]);
                $tfa_enabled = (bool)$stmt->fetchColumn();
            } catch (PDOException $e) {
                // Table may not exist
            }
            
            try {
                $stmt = $pdo->prepare("SELECT two_factor_required FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $tfa_required = (bool)$stmt->fetchColumn();
            } catch (PDOException $e) {
                // Column may not exist
            }
            
            echo json_encode([
                'success' => true,
                'two_fa_enabled' => $tfa_enabled,
                'two_factor_required' => $tfa_required
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    if ($action === 'get_parent_assignments') {
        header('Content-Type: application/json');
        try {
            $userId = intval($_GET['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new Exception('Invalid user ID');
            }
            
            // Get parents who manage this user as a child
            $stmt = $pdo->prepare("
                SELECT ma.id, ma.parent_id, ma.relationship,
                       u.first_name, u.last_name
                FROM managed_athletes ma
                INNER JOIN users u ON ma.parent_id = u.id
                WHERE ma.athlete_id = ?
                ORDER BY u.first_name, u.last_name
            ");
            $stmt->execute([$userId]);
            $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $parents = decryptUserRows($parents);
            
            $assignments = [];
            foreach ($parents as $p) {
                $assignments[] = [
                    'id' => $p['id'],
                    'parent_id' => $p['parent_id'],
                    'parent_name' => ($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''),
                    'relationship' => $p['relationship'] ?? 'Parent'
                ];
            }
            
            echo json_encode([
                'success' => true,
                'assignments' => $assignments
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    // Fetch business card default settings
    if ($action === 'get_business_card_defaults') {
        header('Content-Type: application/json');
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute(['business_card_defaults']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row && $row['setting_value']) {
                echo json_encode(['success' => true, 'data' => json_decode($row['setting_value'], true)]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No saved defaults found.']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not load settings.']);
        }
        exit();
    }
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle JSON bulk_delete before checkCsrfToken (which reads $_POST)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        if ($jsonInput && ($jsonInput['action'] ?? '') === 'bulk_delete') {
            header('Content-Type: application/json');
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
            
            // Verify CSRF token from JSON
            $token = $jsonInput['csrf_token'] ?? '';
            if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
                exit();
            }
            
            $items = $jsonInput['items'] ?? [];
            if (empty($items) || !is_array($items)) {
                echo json_encode(['success' => false, 'message' => 'No items provided']);
                exit();
            }
            
            $user_id = $_SESSION['user_id'] ?? 0;
            $deletedCount = 0;
            $errors = [];
            
            foreach ($items as $item) {
                $itemId = intval($item['id'] ?? 0);
                $itemType = $item['type'] ?? '';
                if ($itemId <= 0 || empty($itemType)) continue;
                
                try {
                    switch ($itemType) {
                        case 'session':
                            $pdo->prepare("DELETE FROM training_session_templates WHERE id = ?")->execute([$itemId]);
                            Auditor::log($pdo, $user_id, 'delete', 'training_session_templates', $itemId, ['action' => 'bulk_delete_session']);
                            $deletedCount++;
                            break;
                        case 'package':
                            $pdo->prepare("DELETE FROM packages WHERE id = ?")->execute([$itemId]);
                            Auditor::log($pdo, $user_id, 'delete', 'packages', $itemId, ['action' => 'bulk_delete_package']);
                            $deletedCount++;
                            break;
                        case 'discount':
                            $pdo->prepare("DELETE FROM discount_codes WHERE id = ?")->execute([$itemId]);
                            Auditor::log($pdo, $user_id, 'delete', 'discount_codes', $itemId, ['action' => 'bulk_delete_discount']);
                            $deletedCount++;
                            break;
                        case 'merch-product':
                            $pdo->prepare("DELETE FROM merchandise_product_sizes WHERE product_id = ?")->execute([$itemId]);
                            $pdo->prepare("DELETE FROM merchandise_product_images WHERE product_id = ?")->execute([$itemId]);
                            $pdo->prepare("DELETE FROM merchandise_products WHERE id = ?")->execute([$itemId]);
                            Auditor::log($pdo, $user_id, 'delete', 'merchandise_products', $itemId, ['action' => 'bulk_delete_merch_product']);
                            $deletedCount++;
                            break;
                        default:
                            $errors[] = "Unknown type: $itemType for ID $itemId";
                            break;
                    }
                } catch (PDOException $e) {
                    ErrorLogger::error("Bulk delete error for $itemType ID $itemId: " . $e->getMessage());
                    $errors[] = "Failed to delete $itemType ID $itemId";
                }
            }
            
            echo json_encode([
                'success' => $deletedCount > 0,
                'deleted_count' => $deletedCount,
                'errors' => $errors,
                'message' => $deletedCount > 0 ? "$deletedCount item(s) deleted" : 'No items were deleted'
            ]);
            exit();
        }
    }
    checkCsrfToken();
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// =========================================================
// MODULE 1: LOCATION MANAGEMENT
// =========================================================
if ($action == 'add_location') {
    $pdo->prepare("INSERT INTO locations (name, city) VALUES (?, ?)")->execute([trim($_POST['name']), trim($_POST['city'])]);
    Auditor::log($pdo, $user_id, 'create', 'locations', $pdo->lastInsertId(), ['action' => 'add_location', 'name' => trim($_POST['name']), 'city' => trim($_POST['city'])]);
    header("Location: dashboard.php?page=admin_locations&status=added"); exit();
}
if ($action == 'create_location') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $name = trim($_POST['name'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $google_place_id = trim($_POST['google_place_id'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        
        if (empty($name) || empty($city)) {
            throw new Exception('Name and city are required');
        }
        
        $stmt = $pdo->prepare("INSERT INTO locations (name, city, google_place_id, image_url) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $city, $google_place_id ?: null, $image_url ?: null]);
        Auditor::log($pdo, $user_id, 'create', 'locations', $pdo->lastInsertId(), ['action' => 'create_location', 'name' => $name, 'city' => $city]);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Location created successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=locations&status=added");
    } catch (Exception $e) {
        ErrorLogger::error("Create location error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=locations&status=error");
    }
    exit();
}
if ($action == 'edit_location') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $location_id = intval($_POST['location_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $google_place_id = trim($_POST['google_place_id'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        
        if ($location_id <= 0 || empty($name) || empty($city)) {
            throw new Exception('Invalid data provided');
        }
        
        $stmt = $pdo->prepare("UPDATE locations SET name = ?, city = ?, google_place_id = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$name, $city, $google_place_id ?: null, $image_url ?: null, $location_id]);
        Auditor::logChange($pdo, $user_id, 'update', 'locations', $location_id, ['action' => 'edit_location', 'name' => $name, 'city' => $city]);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Location updated successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=admin_locations&status=updated");
    } catch (Exception $e) {
        ErrorLogger::error("Edit location error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=admin_locations&status=error");
    }
    exit();
}
if ($action == 'delete_location') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    $location_id = intval($_POST['location_id'] ?? $_POST['id'] ?? 0);
    
    try {
        Auditor::logChange($pdo, $user_id, 'delete', 'locations', $location_id, ['action' => 'delete_location']);
        $pdo->prepare("DELETE FROM locations WHERE id = ?")->execute([$location_id]);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Location deleted successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=admin_locations&status=deleted");
    } catch (PDOException $e) {
        ErrorLogger::error("Delete location error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete location']);
            exit();
        }
        header("Location: dashboard.php?page=admin_locations&status=error");
    }
    exit();
}

// =========================================================
// MODULE 2: SESSION TYPES
// =========================================================
if ($action == 'add_type') {
    $pdo->prepare("INSERT INTO session_types (name, description) VALUES (?, ?)")->execute([trim($_POST['name']), trim($_POST['desc'])]);
    Auditor::log($pdo, $user_id, 'create', 'session_types', $pdo->lastInsertId(), ['action' => 'add_type', 'name' => trim($_POST['name']), 'description' => trim($_POST['desc'])]);
    header("Location: dashboard.php?page=admin_session_types&status=added"); exit();
}
if ($action == 'create_session_type') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        // Validate inputs
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $duration = intval($_POST['duration'] ?? 60);
        
        if (empty($name) || strlen($name) > 100) {
            throw new Exception('Session name is required and must be under 100 characters');
        }
        if ($price < 0) {
            throw new Exception('Price must be a positive value');
        }
        if ($duration < 15 || $duration > 480) {
            throw new Exception('Duration must be between 15 and 480 minutes');
        }
        
        // Full session type creation with pricing and details
        $stmt = $pdo->prepare("INSERT INTO session_types (name, description, default_price, duration_minutes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $description, $price, $duration]);
        Auditor::log($pdo, $user_id, 'create', 'session_types', $pdo->lastInsertId(), ['action' => 'create_session_type', 'name' => $name, 'price' => $price, 'duration' => $duration]);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Session type created successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=accounting_products&tab=sessions&status=added");
    } catch (Exception $e) {
        ErrorLogger::error("Create session type error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=accounting_products&tab=sessions&status=error");
    }
    exit();
}
if ($action == 'edit_session_type') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $type_id = intval($_POST['type_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($type_id <= 0 || empty($name)) {
            throw new Exception('Invalid data provided');
        }
        
        $stmt = $pdo->prepare("UPDATE session_types SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $type_id]);
        Auditor::logChange($pdo, $user_id, 'update', 'session_types', $type_id, ['action' => 'edit_session_type', 'name' => $name, 'description' => $description]);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Session type updated successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=admin_session_types&status=updated");
    } catch (Exception $e) {
        ErrorLogger::error("Edit session type error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=admin_session_types&status=error");
    }
    exit();
}
if ($action == 'delete_session_type') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    $type_id = intval($_POST['type_id'] ?? $_POST['id'] ?? 0);
    
    try {
        Auditor::logChange($pdo, $user_id, 'delete', 'session_types', $type_id, ['action' => 'delete_session_type']);
        $pdo->prepare("DELETE FROM session_types WHERE id = ?")->execute([$type_id]);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Session type deleted successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=admin_session_types&status=deleted");
    } catch (PDOException $e) {
        ErrorLogger::error("Delete session type error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete session type']);
            exit();
        }
        header("Location: dashboard.php?page=admin_session_types&status=error");
    }
    exit();
}
if ($action == 'delete_type') {
    $type_id_del = intval($_POST['id']);
    Auditor::logChange($pdo, $user_id, 'delete', 'session_types', $type_id_del, ['action' => 'delete_type']);
    $pdo->prepare("DELETE FROM session_types WHERE id = ?")->execute([$type_id_del]);
    header("Location: dashboard.php?page=admin_session_types&status=deleted"); exit();
}

// =========================================================
// MODULE 2B: TRAINING SESSION TEMPLATES
// =========================================================
if ($action == 'create_training_session') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        // Validate inputs
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sessionType = $_POST['session_type'] ?? 'on_ice';
        $price = floatval($_POST['price'] ?? 0);
        $duration = intval($_POST['duration'] ?? 60);
        $maxParticipants = !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : null;
        $coachId = !empty($_POST['coach_id']) ? intval($_POST['coach_id']) : null;
        $locationId = !empty($_POST['location_id']) ? intval($_POST['location_id']) : null;
        $practicePlanId = !empty($_POST['practice_plan_id']) ? intval($_POST['practice_plan_id']) : null;
        $sessionTypeId = !empty($_POST['session_type_id']) ? intval($_POST['session_type_id']) : null;
        $isActive = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
        $showOnLanding = isset($_POST['show_on_landing']) ? 1 : 0;
        $isTemplate = isset($_POST['is_template']) ? 1 : 0;
        $skillIds = $_POST['skill_ids'] ?? [];
        $sessionDates = $_POST['session_dates'] ?? [];
        
        if (empty($name) || strlen($name) > 255) {
            throw new Exception('Session name is required (maximum 255 characters)');
        }
        if ($price < 0) {
            throw new Exception('Price must be a positive value');
        }
        if ($duration < 15 || $duration > 480) {
            throw new Exception('Duration must be between 15 and 480 minutes');
        }
        
        $pdo->beginTransaction();
        
        // Insert training session template
        $stmt = $pdo->prepare("
            INSERT INTO training_session_templates 
            (name, description, session_type_id, duration_minutes, price, max_participants, 
             coach_id, location_id, practice_plan_id, session_type, is_active, show_on_landing, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name, $description, $sessionTypeId, $duration, $price, $maxParticipants,
            $coachId, $locationId, $practicePlanId, $sessionType, $isActive, $showOnLanding, $_SESSION['user_id']
        ]);
        
        $templateId = $pdo->lastInsertId();
        
        // Insert skill associations
        if (!empty($skillIds) && is_array($skillIds)) {
            $skillStmt = $pdo->prepare("INSERT INTO template_skill_types (template_id, skill_id) VALUES (?, ?)");
            foreach ($skillIds as $skillId) {
                $skillStmt->execute([$templateId, intval($skillId)]);
            }
        }
        
        // Insert session dates
        if (!empty($sessionDates) && is_array($sessionDates)) {
            $dateStmt = $pdo->prepare("
                INSERT INTO training_session_dates (template_id, session_date, team_id, is_active) 
                VALUES (?, ?, ?, 1)
            ");
            foreach ($sessionDates as $dateData) {
                // Support both new format (date + start_time) and legacy format (datetime)
                $sessionDatetime = null;
                if (!empty($dateData['datetime'])) {
                    $sessionDatetime = $dateData['datetime'];
                } elseif (!empty($dateData['date'])) {
                    $dateStr = $dateData['date'];
                    $timeStr = !empty($dateData['start_time']) ? $dateData['start_time'] : '00:00';
                    $sessionDatetime = $dateStr . ' ' . $timeStr . ':00';
                }
                if ($sessionDatetime) {
                    $teamId = !empty($dateData['team_id']) ? intval($dateData['team_id']) : null;
                    $dateStmt->execute([$templateId, $sessionDatetime, $teamId]);
                }
            }
        }
        
        $pdo->commit();
        Auditor::log($pdo, $user_id, 'create', 'training_session_templates', $templateId, ['action' => 'create_training_session', 'name' => $name, 'session_type' => $sessionType, 'price' => $price, 'duration' => $duration, 'max_participants' => $maxParticipants]);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Training session created successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=sessions&status=added");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        ErrorLogger::error("Create training session error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=sessions&status=error&message=" . urlencode($e->getMessage()));
    }
    exit();
}

if ($action == 'toggle_session_status') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    $sessionId = intval($_POST['id'] ?? 0);
    
    try {
        // Try training_session_templates first
        $stmt = $pdo->prepare("SELECT id, is_active FROM training_session_templates WHERE id = ?");
        $stmt->execute([$sessionId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($template) {
            $newStatus = $template['is_active'] ? 0 : 1;
            $pdo->prepare("UPDATE training_session_templates SET is_active = ? WHERE id = ?")->execute([$newStatus, $sessionId]);
            Auditor::log($pdo, $user_id, 'update', 'training_session_templates', $sessionId, ['action' => 'toggle_session_status']);
        } else {
            // Try session_types
            $stmt = $pdo->prepare("SELECT id, is_active FROM session_types WHERE id = ?");
            $stmt->execute([$sessionId]);
            $sessionType = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sessionType) {
                $newStatus = $sessionType['is_active'] ? 0 : 1;
                $pdo->prepare("UPDATE session_types SET is_active = ? WHERE id = ?")->execute([$newStatus, $sessionId]);
                Auditor::log($pdo, $user_id, 'update', 'session_types', $sessionId, ['action' => 'toggle_session_status']);
            } else {
                throw new Exception('Session not found');
            }
        }
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Session status updated successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=sessions&status=success");
    } catch (Exception $e) {
        ErrorLogger::error("Toggle session status error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=sessions&status=error");
    }
    exit();
}

// Update training session
if ($action == 'update_training_session') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $sessionId = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $duration = intval($_POST['duration'] ?? 60);
        $maxParticipants = !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : null;
        $isActive = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
        $sessionTypeId = !empty($_POST['session_type_id']) ? intval($_POST['session_type_id']) : null;
        $locationId = !empty($_POST['location_id']) ? intval($_POST['location_id']) : null;
        $practicePlanId = !empty($_POST['practice_plan_id']) ? intval($_POST['practice_plan_id']) : null;
        $coachIds = isset($_POST['coach_ids']) ? array_map('intval', $_POST['coach_ids']) : [];
        
        if ($sessionId <= 0) {
            throw new Exception('Invalid session ID');
        }
        if (empty($name)) {
            throw new Exception('Session name is required');
        }
        
        $pdo->beginTransaction();
        
        // Set primary coach_id to first selected coach (for backward compatibility)
        $primaryCoachId = !empty($coachIds) ? $coachIds[0] : null;
        
        $stmt = $pdo->prepare("
            UPDATE training_session_templates 
            SET name = ?, description = ?, price = ?, duration_minutes = ?, max_participants = ?, is_active = ?,
                session_type_id = ?, location_id = ?, practice_plan_id = ?, coach_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $price, $duration, $maxParticipants, $isActive,
                        $sessionTypeId, $locationId, $practicePlanId, $primaryCoachId, $sessionId]);
        
        $pdo->commit();
        Auditor::log($pdo, $user_id, 'update', 'training_session_templates', $sessionId, ['action' => 'update_training_session']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Session updated successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=sessions&status=updated");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        ErrorLogger::error("Update training session error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=sessions&status=error");
    }
    exit();
}

// =========================================================
// LONG TERM DEVELOPMENT PROGRAM CRUD
// =========================================================

if ($action == 'create_dev_program') {
    header('Content-Type: application/json');
    try {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        $name = trim($jsonInput['name'] ?? '');
        $description = trim($jsonInput['description'] ?? '');
        $price = floatval($jsonInput['price'] ?? 0);
        $durationWeeks = intval($jsonInput['duration_weeks'] ?? 4);
        $isActive = intval($jsonInput['is_active'] ?? 1);
        $showOnLanding = intval($jsonInput['show_on_landing'] ?? 1);
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Program name is required']);
            exit();
        }
        if ($durationWeeks < 1 || $durationWeeks > 52) {
            echo json_encode(['success' => false, 'message' => 'Duration must be between 1 and 52 weeks']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO training_session_templates 
            (name, description, price, duration_minutes, max_participants, session_type, is_active, show_on_landing, is_dev_program, duration_weeks, created_by) 
            VALUES (?, ?, ?, 60, 1, 'on_ice', ?, ?, 1, ?, ?)
        ");
        $stmt->execute([$name, $description, $price, $isActive, $showOnLanding, $durationWeeks, $user_id]);
        $newId = $pdo->lastInsertId();
        
        Auditor::log($pdo, $user_id, 'create', 'training_session_templates', $newId, 
            ['action' => 'create_dev_program', 'name' => $name, 'duration_weeks' => $durationWeeks]);
        
        echo json_encode(['success' => true, 'message' => 'Development program created!', 'id' => $newId]);
    } catch (Exception $e) {
        ErrorLogger::error("Create dev program error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

if ($action == 'update_dev_program') {
    header('Content-Type: application/json');
    try {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        $id = intval($jsonInput['id'] ?? 0);
        $name = trim($jsonInput['name'] ?? '');
        $description = trim($jsonInput['description'] ?? '');
        $price = floatval($jsonInput['price'] ?? 0);
        $durationWeeks = intval($jsonInput['duration_weeks'] ?? 4);
        $isActive = intval($jsonInput['is_active'] ?? 1);
        $showOnLanding = intval($jsonInput['show_on_landing'] ?? 1);
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid program ID']);
            exit();
        }
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Program name is required']);
            exit();
        }
        if ($durationWeeks < 1 || $durationWeeks > 52) {
            echo json_encode(['success' => false, 'message' => 'Duration must be between 1 and 52 weeks']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            UPDATE training_session_templates 
            SET name = ?, description = ?, price = ?, duration_weeks = ?, is_active = ?, show_on_landing = ?
            WHERE id = ? AND is_dev_program = 1
        ");
        $stmt->execute([$name, $description, $price, $durationWeeks, $isActive, $showOnLanding, $id]);
        
        Auditor::log($pdo, $user_id, 'update', 'training_session_templates', $id, 
            ['action' => 'update_dev_program', 'name' => $name, 'duration_weeks' => $durationWeeks]);
        
        echo json_encode(['success' => true, 'message' => 'Development program updated!']);
    } catch (Exception $e) {
        ErrorLogger::error("Update dev program error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Add session date to existing session template
if ($action == 'add_session_date') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $templateId = intval($_POST['template_id'] ?? 0);
        $sessionDate = trim($_POST['session_date'] ?? '');
        $teamId = !empty($_POST['team_id']) ? intval($_POST['team_id']) : null;
        
        if ($templateId <= 0) {
            throw new Exception('Invalid session template ID');
        }
        if (empty($sessionDate)) {
            throw new Exception('Session date is required');
        }
        
        // Verify template exists
        $stmt = $pdo->prepare("SELECT id FROM training_session_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        if (!$stmt->fetch()) {
            throw new Exception('Session template not found');
        }
        
        // Insert the new date
        $stmt = $pdo->prepare("
            INSERT INTO training_session_dates (template_id, session_date, team_id, is_active)
            VALUES (?, ?, ?, 1)
        ");
        $stmt->execute([$templateId, $sessionDate, $teamId]);
        $newDateId = $pdo->lastInsertId();
        Auditor::log($pdo, $user_id, 'create', 'training_session_dates', $newDateId, ['action' => 'add_session_date']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Session date added successfully!', 'date_id' => $newDateId]);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=sessions&status=date_added");
    } catch (Exception $e) {
        ErrorLogger::error("Add session date error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=sessions&status=error");
    }
    exit();
}

// Remove session date from session template
if ($action == 'remove_session_date') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $dateId = intval($_POST['date_id'] ?? 0);
        
        if ($dateId <= 0) {
            throw new Exception('Invalid session date ID');
        }
        
        // Delete the date (CASCADE will handle related records)
        $stmt = $pdo->prepare("DELETE FROM training_session_dates WHERE id = ?");
        $stmt->execute([$dateId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Session date not found');
        }
        Auditor::log($pdo, $user_id, 'delete', 'training_session_dates', $dateId, ['action' => 'remove_session_date']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Session date removed successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=sessions&status=date_removed");
    } catch (Exception $e) {
        ErrorLogger::error("Remove session date error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=sessions&status=error");
    }
    exit();
}

// Update package
if ($action == 'update_package') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $packageId = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $credits = intval($_POST['credits'] ?? 0);
        $validDays = !empty($_POST['valid_days']) ? intval($_POST['valid_days']) : null;
        $isActive = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
        $ageGroup = trim($_POST['age_group'] ?? '');
        $skillLevel = trim($_POST['skill_level'] ?? '');
        $packageType = trim($_POST['package_type'] ?? 'credits');
        $storeCredit = floatval($_POST['store_credit'] ?? 0);
        $showOnLanding = isset($_POST['show_on_landing']) ? 1 : 0;
        $enableChildCheckin = isset($_POST['enable_child_checkin']) ? 1 : 0;
        
        if ($packageId <= 0) {
            throw new Exception('Invalid package ID');
        }
        if (empty($name)) {
            throw new Exception('Package name is required');
        }
        
        $stmt = $pdo->prepare("
            UPDATE packages 
            SET name = ?, description = ?, price = ?, credits = ?, valid_days = ?, is_active = ?,
                age_group = ?, skill_level = ?, package_type = ?, store_credit = ?, show_on_landing = ?, enable_child_checkin = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $price, $credits, $validDays, $isActive,
                        $ageGroup ?: null, $skillLevel ?: null, $packageType, $storeCredit, $showOnLanding, $enableChildCheckin, $packageId]);
        
        // Update package coaches
        try {
            $pdo->prepare("DELETE FROM package_coaches WHERE package_id = ?")->execute([$packageId]);
            if (!empty($_POST['coach_ids']) && is_array($_POST['coach_ids'])) {
                $coachStmt = $pdo->prepare("INSERT INTO package_coaches (package_id, coach_id) VALUES (?, ?)");
                foreach ($_POST['coach_ids'] as $cid) {
                    $cid = intval($cid);
                    if ($cid > 0) {
                        $coachStmt->execute([$packageId, $cid]);
                    }
                }
            }
        } catch (PDOException $e) {
            // Silently handle if table doesn't exist yet
            error_log("Package coaches update: " . $e->getMessage());
        }
        
        Auditor::logChange($pdo, $user_id, 'update', 'packages', $packageId, ['action' => 'update_package', 'name' => $name, 'price' => $price, 'credits' => $credits, 'is_active' => $isActive]);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Package updated successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=packages&status=updated");
    } catch (Exception $e) {
        ErrorLogger::error("Update package error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=packages&status=error");
    }
    exit();
}

// Update discount code
if ($action == 'update_discount') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $discountId = intval($_POST['id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $discountType = $_POST['discount_type'] ?? 'percentage';
        $discountValue = floatval($_POST['discount_value'] ?? 0);
        $maxUses = intval($_POST['max_uses'] ?? 0);
        $isActive = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
        
        if ($discountId <= 0) {
            throw new Exception('Invalid discount ID');
        }
        if (empty($code)) {
            throw new Exception('Discount code is required');
        }
        
        $stmt = $pdo->prepare("
            UPDATE discount_codes 
            SET code = ?, description = ?, discount_type = ?, discount_value = ?, max_uses = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([$code, $description, $discountType, $discountValue, $maxUses, $isActive, $discountId]);
        Auditor::logChange($pdo, $user_id, 'update', 'discount_codes', $discountId, ['action' => 'update_discount', 'code' => $code, 'discount_type' => $discountType, 'discount_value' => $discountValue, 'is_active' => $isActive]);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Discount updated successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=discounts&status=updated");
    } catch (Exception $e) {
        ErrorLogger::error("Update discount error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=discounts&status=error");
    }
    exit();
}

// =========================================================
// MODULE 3: USER ROLES
// =========================================================
if ($action == 'update_role') {
    if ($_POST['user_id'] != $_SESSION['user_id']) {
        $target_user_id = intval($_POST['user_id']);
        $new_role = $_POST['new_role'];
        Auditor::logChange($pdo, $user_id, 'update', 'users', $target_user_id, ['action' => 'update_role', 'new_role' => $new_role]);
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $target_user_id]);
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
        Auditor::log($pdo, $user_id, 'update', 'system_settings', 0, ['action' => 'update_smtp', 'settings' => ['smtp_host' => $_POST['smtp_host'] ?? '', 'smtp_port' => $_POST['smtp_port'] ?? '', 'smtp_encryption' => $_POST['smtp_encryption'] ?? '', 'smtp_from_email' => $_POST['smtp_from_email'] ?? '']]);
        header("Location: dashboard.php?page=settings&status=settings_updated");
    } catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }
    exit();
}

// =========================================================
// MODULE 5: BILLING SETTINGS (Stripe)
// =========================================================
if ($action == 'update_billing') {
    $keys = ['stripe_publishable_key', 'stripe_secret_key', 'currency'];
    $encrypted_keys = ['stripe_publishable_key', 'stripe_secret_key'];
    try {
        $del = $pdo->prepare("DELETE FROM system_settings WHERE setting_key = ?");
        $ins = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($keys as $k) {
            $val = $_POST[$k] ?? '';
            if (empty($val)) continue;
            $del->execute([$k]);
            if (in_array($k, $encrypted_keys) && function_exists('encryptPassword')) {
                $val = encryptPassword($val);
            }
            $ins->execute([$k, $val]);
        }
        Auditor::log($pdo, $user_id, 'update', 'system_settings', 0, ['action' => 'update_billing', 'settings' => ['currency' => $_POST['currency'] ?? '', 'stripe_key_updated' => !empty($_POST['stripe_secret_key'])]]);
        header("Location: dashboard.php?page=settings&status=settings_updated");
    } catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }
    exit();
}

// =========================================================
// MODULE 5.1: BUSINESS CARD DEFAULTS
// =========================================================
if ($action == 'save_business_card_defaults') {
    header('Content-Type: application/json');
    try {
        $settings = $_POST['settings'] ?? '';
        if (!is_string($settings) || empty($settings)) {
            throw new Exception('No settings provided.');
        }
        // Validate JSON
        $decoded = json_decode($settings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid settings format.');
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_type, description)
            VALUES ('business_card_defaults', ?, 'json', 'Default business card design settings')
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$settings, $settings]);
        
        Auditor::log($pdo, $user_id, 'update', 'system_settings', 0, ['action' => 'save_business_card_defaults']);
        echo json_encode(['success' => true, 'message' => 'Settings saved as default!']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not save settings: ' . $e->getMessage()]);
    }
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
        Auditor::log($pdo, $_SESSION['user_id'] ?? 0, 'create', 'invoices', $invoice_id, ['action' => 'create_invoice']);
        
        // Check if AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Invoice created successfully', 'invoice_id' => $invoice_id, 'invoice_number' => $invoice_number]);
            exit();
        }
        
        header("Location: dashboard.php?page=finance_dashboard&tab=billing&status=invoice_created&invoice_id=$invoice_id");
    } catch (PDOException $e) {
        ErrorLogger::error("Invoice creation error: " . $e->getMessage());
        
        // Check if AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to create invoice']);
            exit();
        }
        
        header("Location: dashboard.php?page=finance_dashboard&tab=billing&error=invoice_creation_failed");
    }
    exit();
}

// =========================================================
// MODULE 5.6: DOWNLOAD INVOICE
// =========================================================
if ($action == 'download_invoice' || (isset($_GET['action']) && $_GET['action'] == 'download_invoice')) {
    $invoice_id = intval($_POST['invoice_id'] ?? $_GET['invoice_id'] ?? 0);
    
    if ($invoice_id <= 0) {
        header("Location: dashboard.php?page=finance_dashboard&tab=billing&error=invalid_invoice");
        exit();
    }
    
    try {
        // Get invoice details
        $stmt = $pdo->prepare("
            SELECT i.*, u.first_name, u.last_name, u.email
            FROM invoices i
            LEFT JOIN users u ON i.user_id = u.id
            WHERE i.id = ?
        ");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            header("Location: dashboard.php?page=finance_dashboard&tab=billing&error=invoice_not_found");
            exit();
        }
        $invoice = decryptUserRow($invoice);
        
        // Get line items
        $items_stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
        $items_stmt->execute([$invoice_id]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate HTML invoice
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice ' . htmlspecialchars($invoice['invoice_number']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
        .header { border-bottom: 2px solid #6B46C1; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #6B46C1; margin: 0; font-size: 28px; }
        .invoice-info { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .invoice-info div { flex: 1; }
        .invoice-info h3 { margin: 0 0 10px 0; font-size: 14px; color: #666; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 700; color: #6B46C1; }
        .total-row { font-weight: bold; font-size: 18px; background: #6B46C1; color: white; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>INVOICE</h1>
        <p>Invoice #: ' . htmlspecialchars($invoice['invoice_number']) . '</p>
    </div>
    
    <div class="invoice-info">
        <div>
            <h3>Bill To</h3>
            <p><strong>' . htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) . '</strong><br>
            ' . htmlspecialchars($invoice['email']) . '</p>
        </div>
        <div style="text-align: right;">
            <h3>Invoice Details</h3>
            <p>Date: ' . date('F j, Y', strtotime($invoice['invoice_date'])) . '<br>
            Due: ' . date('F j, Y', strtotime($invoice['due_date'])) . '<br>
            Status: ' . ucfirst($invoice['status']) . '</p>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>';
        
        if (!empty($items)) {
            foreach ($items as $item) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($item['description']) . '</td>
                    <td>' . $item['quantity'] . '</td>
                    <td>$' . number_format($item['unit_price'], 2) . '</td>
                    <td>$' . number_format($item['total_price'], 2) . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr>
                <td>' . htmlspecialchars($invoice['notes'] ?? 'Invoice services') . '</td>
                <td>1</td>
                <td>$' . number_format($invoice['total_amount'], 2) . '</td>
                <td>$' . number_format($invoice['total_amount'], 2) . '</td>
            </tr>';
        }
        
        $html .= '
            <tr class="total-row">
                <td colspan="3" style="text-align: right;">TOTAL</td>
                <td>$' . number_format($invoice['total_amount'], 2) . '</td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Thank you for your business!</p>
        <p>Arctic Wolves Hockey Training</p>
    </div>
</body>
</html>';
        
        // Sanitize invoice number for filename (remove special characters)
        $safe_invoice_number = preg_replace('/[^A-Za-z0-9\-]/', '_', $invoice['invoice_number']);
        
        // Output as downloadable HTML file (can be converted to PDF if library is available)
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="Invoice_' . $safe_invoice_number . '.html"');
        echo $html;
        exit();
        
    } catch (PDOException $e) {
        ErrorLogger::error("Invoice download error: " . $e->getMessage());
        header("Location: dashboard.php?page=finance_dashboard&tab=billing&error=download_failed");
        exit();
    }
}

// =========================================================
// MODULE 5.7: VIEW INVOICE
// =========================================================
if ($action == 'view_invoice' || (isset($_GET['action']) && $_GET['action'] == 'view_invoice')) {
    $invoice_id = intval($_POST['invoice_id'] ?? $_GET['invoice_id'] ?? 0);
    
    if ($invoice_id <= 0) {
        header("Location: dashboard.php?page=finance_dashboard&tab=billing&error=invalid_invoice");
        exit();
    }
    
    try {
        // Get invoice details
        $stmt = $pdo->prepare("
            SELECT i.*, u.first_name, u.last_name, u.email
            FROM invoices i
            LEFT JOIN users u ON i.user_id = u.id
            WHERE i.id = ?
        ");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            header("Location: dashboard.php?page=finance_dashboard&tab=billing&error=invoice_not_found");
            exit();
        }
        $invoice = decryptUserRow($invoice);
        
        // Get line items
        $items_stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
        $items_stmt->execute([$invoice_id]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return JSON for AJAX requests
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'invoice' => $invoice,
                'items' => $items
            ]);
            exit();
        }
        
        // Generate inline view HTML
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice ' . htmlspecialchars($invoice['invoice_number']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; background: #f5f5f5; }
        .invoice-container { background: white; max-width: 800px; margin: 0 auto; padding: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { border-bottom: 2px solid #6B46C1; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #6B46C1; margin: 0; font-size: 28px; }
        .invoice-info { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .invoice-info div { flex: 1; }
        .invoice-info h3 { margin: 0 0 10px 0; font-size: 14px; color: #666; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 700; color: #6B46C1; }
        .total-row { font-weight: bold; font-size: 18px; background: #6B46C1; color: white; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 12px; }
        .actions { margin-top: 20px; text-align: center; }
        .btn { padding: 10px 20px; background: #6B46C1; color: white; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; margin: 0 5px; }
        .btn:hover { background: #5a0080; }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <h1>INVOICE</h1>
            <p>Invoice #: ' . htmlspecialchars($invoice['invoice_number']) . '</p>
        </div>
        
        <div class="invoice-info">
            <div>
                <h3>Bill To</h3>
                <p><strong>' . htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) . '</strong><br>
                ' . htmlspecialchars($invoice['email']) . '</p>
            </div>
            <div style="text-align: right;">
                <h3>Invoice Details</h3>
                <p>Date: ' . date('F j, Y', strtotime($invoice['invoice_date'])) . '<br>
                Due: ' . date('F j, Y', strtotime($invoice['due_date'])) . '<br>
                Status: ' . ucfirst($invoice['status']) . '</p>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';
        
        if (!empty($items)) {
            foreach ($items as $item) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($item['description']) . '</td>
                    <td>' . $item['quantity'] . '</td>
                    <td>$' . number_format($item['unit_price'], 2) . '</td>
                    <td>$' . number_format($item['total_price'], 2) . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr>
                <td>' . htmlspecialchars($invoice['notes'] ?? 'Invoice services') . '</td>
                <td>1</td>
                <td>$' . number_format($invoice['total_amount'], 2) . '</td>
                <td>$' . number_format($invoice['total_amount'], 2) . '</td>
            </tr>';
        }
        
        $html .= '
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;">TOTAL</td>
                    <td>$' . number_format($invoice['total_amount'], 2) . '</td>
                </tr>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Thank you for your business!</p>
            <p>Arctic Wolves Hockey Training</p>
        </div>
        
        <div class="actions">
            <a href="process_admin_action.php?action=download_invoice&invoice_id=' . $invoice_id . '" class="btn">Download</a>
            <a href="javascript:window.print()" class="btn">Print</a>
            <a href="dashboard.php?page=finance_dashboard&tab=billing" class="btn" style="background: #666;">Back</a>
        </div>
    </div>
</body>
</html>';
        
        echo $html;
        exit();
        
    } catch (PDOException $e) {
        ErrorLogger::error("Invoice view error: " . $e->getMessage());
        header("Location: dashboard.php?page=finance_dashboard&tab=billing&error=view_failed");
        exit();
    }
}

// =========================================================
// MODULE 5.8: RECORD PAYMENT (for cash and manual payments)
// =========================================================
if ($action == 'record_payment') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $payment_method = trim($_POST['payment_method'] ?? 'cash');
        $reference_number = trim($_POST['reference_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if ($invoice_id <= 0 || $amount <= 0) {
            throw new Exception('Invalid invoice ID or payment amount');
        }
        
        // Verify invoice exists and get user_id
        $invoice_stmt = $pdo->prepare("SELECT user_id, total_amount, status FROM invoices WHERE id = ?");
        $invoice_stmt->execute([$invoice_id]);
        $invoice = $invoice_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            throw new Exception('Invoice not found');
        }
        
        // Generate transaction ID
        $transaction_id = 'TXN-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        // Insert payment record
        $stmt = $pdo->prepare("
            INSERT INTO payments (user_id, invoice_id, amount, payment_method, payment_date, transaction_id, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, 'completed', ?)
        ");
        $stmt->execute([
            $invoice['user_id'],
            $invoice_id,
            $amount,
            $payment_method,
            $payment_date,
            $transaction_id,
            $notes . ($reference_number ? ' Ref: ' . $reference_number : '')
        ]);
        
        // Calculate total paid for this invoice
        $paid_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = ? AND status = 'completed'");
        $paid_stmt->execute([$invoice_id]);
        $total_paid = $paid_stmt->fetchColumn();
        
        // Update invoice status if fully paid
        if ($total_paid >= $invoice['total_amount']) {
            $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?")->execute([$invoice_id]);
        }
        Auditor::log($pdo, $user_id, 'create', 'payments', $pdo->lastInsertId(), ['action' => 'record_payment']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Payment recorded successfully!',
                'transaction_id' => $transaction_id
            ]);
            exit();
        }
        
        header("Location: dashboard.php?page=finance_dashboard&tab=billing&status=payment_recorded");
    } catch (Exception $e) {
        ErrorLogger::error("Record payment error: " . $e->getMessage());
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        
        header("Location: dashboard.php?page=finance_dashboard&tab=billing&error=payment_failed");
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
        Auditor::log($pdo, $user_id, 'create', 'discount_codes', $pdo->lastInsertId(), ['action' => 'add_discount']);
        header("Location: dashboard.php?page=admin_discounts&status=added");
    } catch (PDOException $e) { die("Error: " . $e->getMessage()); }
    exit();
}

if ($action == 'create_discount') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    $code = strtoupper(trim($_POST['code']));
    // Map form field 'type' to schema column 'discount_type'
    // Note: Schema ENUM only allows 'percentage' or 'fixed', so map 'store_credit' to 'fixed'
    $discount_type = $_POST['type'] ?? 'percentage';
    if ($discount_type === 'store_credit') {
        $discount_type = 'fixed'; // Store credit uses fixed amount
    }
    $discount_value = floatval($_POST['value']);
    // Map form field 'usage_limit' to schema column 'max_uses'
    $max_uses = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : NULL;
    // Map form fields to schema columns
    $valid_from = !empty($_POST['start_date']) ? $_POST['start_date'] : NULL;
    $valid_until = !empty($_POST['end_date']) ? $_POST['end_date'] : NULL;
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

    try {
        // Only insert columns that exist in the discount_codes table schema
        $stmt = $pdo->prepare("INSERT INTO discount_codes (code, discount_type, discount_value, max_uses, valid_from, valid_until, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $discount_type, $discount_value, $max_uses, $valid_from, $valid_until, $is_active]);
        Auditor::log($pdo, $user_id, 'create', 'discount_codes', $pdo->lastInsertId(), ['action' => 'create_discount']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Discount code created successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=discounts&status=success");
    } catch (PDOException $e) {
        ErrorLogger::error("Create discount error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to create discount code: ' . $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=discounts&status=error");
    }
    exit();
}

if ($action == 'edit_discount') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    $discount_id = intval($_POST['discount_id']);
    $code = strtoupper(trim($_POST['code']));
    // Note: Schema ENUM only allows 'percentage' or 'fixed', so map 'store_credit' to 'fixed'
    $discount_type = $_POST['type'] ?? 'percentage';
    if ($discount_type === 'store_credit') {
        $discount_type = 'fixed'; // Store credit uses fixed amount
    }
    $discount_value = floatval($_POST['value']);
    $max_uses = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : NULL;
    $valid_from = !empty($_POST['start_date']) ? $_POST['start_date'] : NULL;
    $valid_until = !empty($_POST['end_date']) ? $_POST['end_date'] : NULL;
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

    try {
        $stmt = $pdo->prepare("UPDATE discount_codes SET code = ?, discount_type = ?, discount_value = ?, max_uses = ?, valid_from = ?, valid_until = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$code, $discount_type, $discount_value, $max_uses, $valid_from, $valid_until, $is_active, $discount_id]);
        Auditor::log($pdo, $user_id, 'update', 'discount_codes', $discount_id, ['action' => 'edit_discount']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Discount code updated successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=discounts&status=success");
    } catch (PDOException $e) {
        ErrorLogger::error("Edit discount error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update discount code: ' . $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=products&tab=discounts&status=error");
    }
    exit();
}

if ($action == 'delete_discount') {
    $discount_id = intval($_POST['discount_id']);
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        // Verify discount exists before deletion
        $stmt = $pdo->prepare("SELECT 1 FROM discount_codes WHERE id = ? LIMIT 1");
        $stmt->execute([$discount_id]);
        if (!$stmt->fetch()) {
            ErrorLogger::error("Delete discount error: Discount ID $discount_id not found");
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Discount not found']);
                exit();
            }
            header("Location: dashboard.php?page=accounting_products&tab=discounts&status=error");
            exit();
        }
        
        $pdo->prepare("DELETE FROM discount_codes WHERE id = ?")->execute([$discount_id]);
        Auditor::log($pdo, $user_id, 'delete', 'discount_codes', $discount_id, ['action' => 'delete_discount']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Discount deleted successfully']);
            exit();
        }
        header("Location: dashboard.php?page=accounting_products&tab=discounts&status=success");
    } catch (PDOException $e) {
        ErrorLogger::error("Delete discount error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete discount']);
            exit();
        }
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
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    // Support multiple coaches - get array of coach IDs
    $assigned_coach_ids = isset($_POST['assigned_coach_ids']) && is_array($_POST['assigned_coach_ids']) ? array_map('intval', array_filter($_POST['assigned_coach_ids'])) : [];
    // For backward compatibility, also check for single assigned_coach_id
    if (empty($assigned_coach_ids) && !empty($_POST['assigned_coach_id'])) {
        $assigned_coach_ids = [intval($_POST['assigned_coach_id'])];
    }
    // Use first coach as primary assigned_coach_id for backward compatibility
    $primary_coach_id = !empty($assigned_coach_ids) ? $assigned_coach_ids[0] : null;
    // Support multiple team assignments with seasons
    $team_season_ids_raw = isset($_POST['team_season_ids']) && is_array($_POST['team_season_ids']) ? $_POST['team_season_ids'] : [];
    // Backward compatibility: also check for single team_id
    if (empty($team_season_ids_raw) && !empty($_POST['team_id'])) {
        $team_season_ids_raw = [$_POST['team_id'] . '|'];
    }
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        header("Location: dashboard.php?page=all_users&status=error&msg=required_fields");
        exit();
    }
    
    // Validate role against allowlist
    $valid_roles = ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach', 'athlete', 'parent', 'front_desk_staff', 'hr', 'accounting'];
    if (!in_array($role, $valid_roles)) {
        header("Location: dashboard.php?page=all_users&status=error&msg=invalid_role");
        exit();
    }

    try {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $force_pass_change = 1; // Require password change on first login

        // Encrypt PII fields before storing (email kept as-is for login lookups)
        $enc_first_name = FieldEncryption::encrypt($first_name);
        $enc_last_name = FieldEncryption::encrypt($last_name);
        $enc_phone = $phone ? FieldEncryption::encrypt($phone) : null;
        $enc_birth_date = $birth_date ? FieldEncryption::encrypt($birth_date) : null;
        
        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password, first_name, last_name, role, phone, is_verified, force_pass_change, birth_date, assigned_coach_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$email, $hashed_password, $enc_first_name, $enc_last_name, $role, $enc_phone, $is_verified, $force_pass_change, $enc_birth_date, $primary_coach_id]);
        
        $new_user_id = $pdo->lastInsertId();
        
        // Secondary operations - wrapped individually so failures don't mask successful user creation
        
        // Assign multiple coaches to user
        if (!empty($assigned_coach_ids)) {
            try {
                $insert_coach_stmt = $pdo->prepare("
                    INSERT INTO athlete_coaches (athlete_id, coach_id, role_type, assigned_by, status) 
                    VALUES (?, ?, 'primary', ?, 'active')
                    ON DUPLICATE KEY UPDATE status = 'active', assigned_by = ?
                ");
                foreach ($assigned_coach_ids as $coach_id) {
                    $insert_coach_stmt->execute([$new_user_id, $coach_id, $_SESSION['user_id'], $_SESSION['user_id']]);
                }
            } catch (Exception $e) {
                ErrorLogger::warning("Coach assignment failed for new user $new_user_id (coaches: " . implode(',', $assigned_coach_ids) . "): " . $e->getMessage());
            }
        }
        
        // Assign teams if provided (multiple teams with seasons)
        if (!empty($team_season_ids_raw) && $role === 'athlete') {
            try {
                $insert_team_stmt = $pdo->prepare("
                    INSERT INTO team_roster (team_id, athlete_id, season_id) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE athlete_id = VALUES(athlete_id)
                ");
                foreach ($team_season_ids_raw as $combo) {
                    $parts = explode('|', $combo);
                    $tid = intval($parts[0] ?? 0);
                    $sid = !empty($parts[1]) ? intval($parts[1]) : null;
                    if ($tid > 0) {
                        $insert_team_stmt->execute([$tid, $new_user_id, $sid]);
                    }
                }
            } catch (Exception $e) {
                ErrorLogger::warning("Team assignment failed for new user $new_user_id (teams: " . implode(',', $team_season_ids_raw) . "): " . $e->getMessage());
            }
        }
        
        // Send welcome email with credentials
        require_once 'mailer.php';
        sendEmail($email, 'manual_welcome', [
            'name' => $first_name . ' ' . $last_name,
            'email' => $email,
            'password' => $password
        ]);
        Auditor::log($pdo, $user_id, 'create', 'users', $new_user_id, ['action' => 'create_user']);
        
        header("Location: dashboard.php?page=all_users&status=success");
    } catch (Exception $e) {
        ErrorLogger::error("Create user error: " . $e->getMessage());
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
    $job_title = trim($_POST['job_title'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    // Support multiple coaches - get array of coach IDs
    $assigned_coach_ids = isset($_POST['assigned_coach_ids']) && is_array($_POST['assigned_coach_ids']) ? array_map('intval', array_filter($_POST['assigned_coach_ids'])) : [];
    // For backward compatibility, also check for single assigned_coach_id
    if (empty($assigned_coach_ids) && !empty($_POST['assigned_coach_id'])) {
        $assigned_coach_ids = [intval($_POST['assigned_coach_id'])];
    }
    // Use first coach as primary assigned_coach_id for backward compatibility
    $primary_coach_id = !empty($assigned_coach_ids) ? $assigned_coach_ids[0] : null;
    // Support multiple team assignments with seasons
    $team_season_ids_update = isset($_POST['team_season_ids']) && is_array($_POST['team_season_ids']) ? $_POST['team_season_ids'] : [];
    // Backward compatibility: also check for single team_id
    if (empty($team_season_ids_update) && !empty($_POST['team_id'])) {
        $team_season_ids_update = [$_POST['team_id'] . '|'];
    }
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        header("Location: dashboard.php?page=all_users&status=error&msg=required_fields");
        exit();
    }
    
    // Validate role against allowlist
    $valid_roles = ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach', 'athlete', 'parent', 'front_desk_staff', 'hr', 'accounting'];
    if (!in_array($role, $valid_roles)) {
        header("Location: dashboard.php?page=all_users&status=error&msg=invalid_role");
        exit();
    }

    try {
        // Encrypt PII fields before storing (email kept as-is for login lookups)
        $enc_first_name = FieldEncryption::encrypt($first_name);
        $enc_last_name = FieldEncryption::encrypt($last_name);
        $enc_phone = $phone ? FieldEncryption::encrypt($phone) : null;
        $enc_birth_date = $birth_date ? FieldEncryption::encrypt($birth_date) : null;

        // Check if password is being updated
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, password = ?, birth_date = ?, job_title = ?, assigned_coach_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$enc_first_name, $enc_last_name, $email, $enc_phone, $role, $hashed_password, $enc_birth_date, $job_title ?: null, $primary_coach_id, $user_id_to_update]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, birth_date = ?, job_title = ?, assigned_coach_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$enc_first_name, $enc_last_name, $email, $enc_phone, $role, $enc_birth_date, $job_title ?: null, $primary_coach_id, $user_id_to_update]);
        }
        
        // Update multiple coach assignments in athlete_coaches table
        if (!empty($assigned_coach_ids)) {
            // First, deactivate all existing coach assignments for this athlete
            $pdo->prepare("UPDATE athlete_coaches SET status = 'inactive' WHERE athlete_id = ?")->execute([$user_id_to_update]);
            
            // Then add/reactivate the new coach assignments
            $insert_coach_stmt = $pdo->prepare("
                INSERT INTO athlete_coaches (athlete_id, coach_id, role_type, assigned_by, status) 
                VALUES (?, ?, 'primary', ?, 'active')
                ON DUPLICATE KEY UPDATE status = 'active', assigned_by = ?
            ");
            foreach ($assigned_coach_ids as $coach_id) {
                $insert_coach_stmt->execute([$user_id_to_update, $coach_id, $_SESSION['user_id'], $_SESSION['user_id']]);
            }
        }
        
        // Handle team assignments (multiple teams with seasons)
        if ($role === 'athlete') {
            // Remove all existing team roster entries
            $pdo->prepare("DELETE FROM team_roster WHERE athlete_id = ?")->execute([$user_id_to_update]);
            
            // Insert new team assignments
            if (!empty($team_season_ids_update)) {
                $insert_team_stmt = $pdo->prepare("
                    INSERT INTO team_roster (team_id, athlete_id, season_id) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE athlete_id = VALUES(athlete_id)
                ");
                foreach ($team_season_ids_update as $combo) {
                    $parts = explode('|', $combo);
                    $tid = intval($parts[0] ?? 0);
                    $sid = !empty($parts[1]) ? intval($parts[1]) : null;
                    if ($tid > 0) {
                        $insert_team_stmt->execute([$tid, $user_id_to_update, $sid]);
                    }
                }
            }
        }
        Auditor::log($pdo, $user_id, 'update', 'users', $user_id_to_update, ['action' => 'update_user']);
        
        header("Location: dashboard.php?page=all_users&status=success");
    } catch (PDOException $e) {
        ErrorLogger::error("Update user error:" . $e->getMessage());
        header("Location: dashboard.php?page=all_users&status=error");
    }
    exit();
}

// =========================================================
// MODULE 8.4: ADMIN UPDATE SIP PROFILE
// =========================================================
if ($action == 'admin_update_sip') {
    $user_id_to_update = intval($_POST['user_id']);
    $sip_username = trim($_POST['sip_username'] ?? '');
    $sip_domain = trim($_POST['sip_domain'] ?? '');
    $sip_extension = trim($_POST['sip_extension'] ?? '');
    $sip_did = trim($_POST['sip_did'] ?? '');
    $sip_password = $_POST['sip_password'] ?? '';

    // Only encrypt and update password if admin entered a new one
    $update_password = !empty($sip_password);
    $encrypted_password = $update_password ? FieldEncryption::encrypt($sip_password) : null;

    try {
        if ($update_password) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET sip_username = ?, sip_domain = ?, sip_extension = ?, sip_did = ?, sip_password = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $sip_username ?: null,
                $sip_domain ?: null,
                $sip_extension ?: null,
                $sip_did ?: null,
                $encrypted_password,
                $user_id_to_update
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET sip_username = ?, sip_domain = ?, sip_extension = ?, sip_did = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $sip_username ?: null,
                $sip_domain ?: null,
                $sip_extension ?: null,
                $sip_did ?: null,
                $user_id_to_update
            ]);
        }
        Auditor::log($pdo, $user_id, 'update', 'users', $user_id_to_update, ['action' => 'admin_update_sip']);

        header("Location: dashboard.php?page=all_users&status=success&msg=sip_updated");
    } catch (PDOException $e) {
        ErrorLogger::error("Admin update SIP error:" . $e->getMessage());
        header("Location: dashboard.php?page=all_users&status=error&msg=sip_update_failed");
    }
    exit();
}

// =========================================================
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
        $user = decryptUserRow($user);
        
        // Toggle status
        $new_status = $user['is_verified'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET is_verified = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id_to_toggle]);
        Auditor::log($pdo, $user_id, 'update', 'users', $user_id_to_toggle, ['action' => 'toggle_user_status']);
        
        $status_text = $new_status ? 'enabled' : 'disabled';
        echo json_encode([
            'success' => true, 
            'message' => "User {$user['first_name']} {$user['last_name']} has been {$status_text}"
        ]);
    } catch (PDOException $e) {
        ErrorLogger::error("Toggle user status error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit();
}

// =========================================================
// MODULE 8.5: RESET USER PASSWORD
// =========================================================
if ($action == 'reset_user_password') {
    header('Content-Type: application/json');
    
    try {
        $user_id_to_reset = intval($_POST['user_id']);
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $force_change = isset($_POST['force_change']) ? 1 : 0;
        
        // Validate password length
        if (empty($new_password) || strlen($new_password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
            exit();
        }
        
        // Validate password complexity - require at least one uppercase, one lowercase, one number
        if (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
            echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number']);
            exit();
        }
        
        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
            exit();
        }
        
        // Check user exists
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$user_id_to_reset]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        $user = decryptUserRow($user);
        
        // Hash and update password
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, force_pass_change = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $force_change, $user_id_to_reset]);
        Auditor::log($pdo, $user_id, 'update', 'users', $user_id_to_reset, ['action' => 'reset_user_password']);
        
        echo json_encode([
            'success' => true, 
            'message' => "Password reset successfully for {$user['first_name']} {$user['last_name']}"
        ]);
    } catch (PDOException $e) {
        ErrorLogger::error("Reset user password error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit();
}

// =========================================================
// MODULE 8.6: ADMIN RESET USER PIN
// =========================================================
if ($action == 'admin_reset_pin') {
    header('Content-Type: application/json');
    
    try {
        $user_id_to_reset = intval($_POST['user_id']);
        $new_pin = $_POST['new_pin'] ?? '';
        $confirm_pin = $_POST['confirm_pin'] ?? '';
        
        // Validate PIN format (4 digits)
        if (!preg_match('/^\d{4}$/', $new_pin)) {
            echo json_encode(['success' => false, 'message' => 'PIN must be exactly 4 digits']);
            exit();
        }
        
        if ($new_pin !== $confirm_pin) {
            echo json_encode(['success' => false, 'message' => 'PINs do not match']);
            exit();
        }
        
        // Check user exists and has a valid role for PINs
        $stmt = $pdo->prepare("SELECT first_name, last_name, role FROM users WHERE id = ?");
        $stmt->execute([$user_id_to_reset]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        $user = decryptUserRow($user);
        
        // Verify user has a role that supports PIN login
        $allowed_roles = ['admin', 'coach', 'health_coach', 'front_desk_staff', 'hr', 'accounting'];
        if (!in_array($user['role'], $allowed_roles)) {
            echo json_encode(['success' => false, 'message' => 'User role does not support PIN login']);
            exit();
        }
        
        // Hash the PIN
        $pin_hash = password_hash($new_pin, PASSWORD_DEFAULT);
        
        // Insert or update PIN
        $stmt = $pdo->prepare("
            INSERT INTO staff_pins (user_id, pin_hash, is_active) 
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE pin_hash = ?, is_active = 1
        ");
        $stmt->execute([$user_id_to_reset, $pin_hash, $pin_hash]);
        Auditor::log($pdo, $user_id, 'update', 'staff_pins', $user_id_to_reset, ['action' => 'admin_reset_pin']);
        
        echo json_encode([
            'success' => true, 
            'message' => "PIN set successfully for {$user['first_name']} {$user['last_name']}"
        ]);
    } catch (PDOException $e) {
        ErrorLogger::error("Admin reset PIN error:" . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit();
}

if ($action == 'force_2fa') {
    header('Content-Type: application/json');
    
    try {
        $user_id_to_update = intval($_POST['user_id']);
        $two_factor_required = intval($_POST['two_factor_required'] ?? 0) ? 1 : 0;
        
        if ($user_id_to_update <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit();
        }
        
        // Check if column exists
        try {
            $stmt = $pdo->prepare("UPDATE users SET two_factor_required = ? WHERE id = ?");
            $stmt->execute([$two_factor_required, $user_id_to_update]);
        } catch (PDOException $e) {
            // Column might not exist yet, try adding it
            $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_required TINYINT(1) DEFAULT 0");
            $stmt = $pdo->prepare("UPDATE users SET two_factor_required = ? WHERE id = ?");
            $stmt->execute([$two_factor_required, $user_id_to_update]);
        }
        
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$user_id_to_update]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user = decryptUserRow($user);
        
        $action_text = $two_factor_required ? 'required' : 'not required';
        Auditor::log($pdo, $user_id, 'update', 'users', $user_id_to_update, ['action' => 'force_2fa']);
        echo json_encode([
            'success' => true,
            'message' => "2FA is now {$action_text} for " . ($user ? $user['first_name'] . ' ' . $user['last_name'] : 'this user')
        ]);
    } catch (PDOException $e) {
        ErrorLogger::error("Admin force 2FA error: " . $e->getMessage());
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
            Auditor::log($pdo, $user_id, 'update', 'session_types', $session_type_id, ['action' => 'toggle_session_type_status']);
            
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
        ErrorLogger::error("Toggle session status error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit();
}

// =========================================================
// MODULE 8.6: ADMIN PROFILE IMAGE MANAGEMENT
// =========================================================
if ($action == 'admin_update_profile_image') {
    header('Content-Type: application/json');
    
    try {
        $user_id_to_update = intval($_POST['user_id'] ?? 0);
        
        if ($user_id_to_update <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit();
        }
        
        if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== 0) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
            exit();
        }
        
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['profile_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP']);
            exit();
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['profile_image']['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed_mimes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file content type']);
            exit();
        }
        
        // Check file size (100MB max)
        if ($_FILES['profile_image']['size'] > 100 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 100MB']);
            exit();
        }
        
        // Generate secure random filename
        $random_suffix = bin2hex(random_bytes(8));
        $nc_filename = "profile_" . $user_id_to_update . "_" . $random_suffix . "." . $ext;
        
        $persist = persistUploadedFile($pdo, $_FILES['profile_image']['tmp_name'], 'profiles', $nc_filename);
        $db_path = $persist['rustfs_url'] ?? null;
        
        if ($persist['success']) {
            // Delete old profile image if exists
            $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
            $stmt->execute([$user_id_to_update]);
            $old_image = $stmt->fetchColumn();
            // Clean up old image from RustFS
            if ($old_image && preg_match('#^https?://#', $old_image)) {
                try {
                    $rustfs = getRustFSSettings($pdo);
                    if (isRustFSConfigured($rustfs)) {
                        $base_url = getRustFSBaseUrl($rustfs);
                        if (strpos($old_image, $base_url) === 0) {
                            $object_key = substr($old_image, strlen($base_url) + 1);
                            deleteFromRustFS($rustfs, $object_key);
                        }
                    }
                } catch (Exception $delErr) {
                    error_log("RustFS delete old profile image: " . $delErr->getMessage());
                }
            }
            
            // Update database with RustFS URL
            $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute([$db_path, $user_id_to_update]);
            
            // Store persistent path for recovery
            if (!empty($persist['nextcloud_path'])) {
                $pdo->prepare("UPDATE users SET nextcloud_image_path = ? WHERE id = ?")->execute([$persist['nextcloud_path'], $user_id_to_update]);
            }
            
            Auditor::log($pdo, $user_id, 'update', 'users', $user_id_to_update, ['action' => 'admin_update_profile_image']);
            
            echo json_encode(['success' => true, 'message' => 'Profile image updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
        }
    } catch (PDOException $e) {
        ErrorLogger::error("Admin profile image update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit();
}

if ($action == 'admin_remove_profile_image') {
    header('Content-Type: application/json');
    
    try {
        $user_id_to_update = intval($_POST['user_id'] ?? 0);
        
        if ($user_id_to_update <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit();
        }
        
        // Get and delete old profile image
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$user_id_to_update]);
        $old_image = $stmt->fetchColumn();
        
        if ($old_image && !preg_match('#^https?://#', $old_image) && file_exists($old_image)) {
            unlink($old_image);
        }
        
        // Update database
        $pdo->prepare("UPDATE users SET profile_image = NULL WHERE id = ?")->execute([$user_id_to_update]);
        Auditor::log($pdo, $user_id, 'update', 'users', $user_id_to_update, ['action' => 'admin_remove_profile_image']);
        
        echo json_encode(['success' => true, 'message' => 'Profile image removed successfully']);
    } catch (PDOException $e) {
        ErrorLogger::error("Admin profile image remove error:" . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit();
}

// =========================================================
// MODULE 8.7: ADMIN USER ASSIGNMENTS (Coach, Team)
// =========================================================
if ($action == 'admin_update_assignments') {
    header('Content-Type: application/json');
    
    try {
        $user_id_to_update = intval($_POST['user_id'] ?? 0);
        // Support multiple coaches - get array of coach IDs
        $assigned_coach_ids = isset($_POST['assigned_coach_ids']) && is_array($_POST['assigned_coach_ids']) ? array_map('intval', array_filter($_POST['assigned_coach_ids'])) : [];
        // For backward compatibility, also check for single assigned_coach_id
        if (empty($assigned_coach_ids) && !empty($_POST['assigned_coach_id'])) {
            $assigned_coach_ids = [intval($_POST['assigned_coach_id'])];
        }
        // Use first coach as primary assigned_coach_id for backward compatibility
        $primary_coach_id = !empty($assigned_coach_ids) ? $assigned_coach_ids[0] : null;
        $jersey_number = !empty($_POST['jersey_number']) ? intval($_POST['jersey_number']) : null;
        $position = trim($_POST['position'] ?? '');
        
        if ($user_id_to_update <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit();
        }
        
        // Update primary assigned coach for backward compatibility
        $pdo->prepare("UPDATE users SET assigned_coach_id = ? WHERE id = ?")->execute([$primary_coach_id, $user_id_to_update]);
        
        // Update multiple coach assignments
        // First, deactivate all existing coach assignments for this athlete
        $pdo->prepare("UPDATE athlete_coaches SET status = 'inactive' WHERE athlete_id = ?")->execute([$user_id_to_update]);
        
        // Then add/reactivate the new coach assignments
        if (!empty($assigned_coach_ids)) {
            $insert_coach_stmt = $pdo->prepare("
                INSERT INTO athlete_coaches (athlete_id, coach_id, role_type, assigned_by, status) 
                VALUES (?, ?, 'primary', ?, 'active')
                ON DUPLICATE KEY UPDATE status = 'active', assigned_by = ?
            ");
            foreach ($assigned_coach_ids as $coach_id) {
                $insert_coach_stmt->execute([$user_id_to_update, $coach_id, $_SESSION['user_id'], $_SESSION['user_id']]);
            }
        }
        
        // Handle team assignments (multiple teams with seasons)
        // Parse team_season_ids[] format: "team_id|season_id"
        $team_season_ids = isset($_POST['team_season_ids']) && is_array($_POST['team_season_ids']) ? $_POST['team_season_ids'] : [];
        // Backward compatibility: also check for single team_id
        if (empty($team_season_ids) && !empty($_POST['team_id'])) {
            $team_season_ids = [$_POST['team_id'] . '|'];
        }
        
        $team_assignments = [];
        foreach ($team_season_ids as $combo) {
            $parts = explode('|', $combo);
            $tid = intval($parts[0] ?? 0);
            $sid = !empty($parts[1]) ? intval($parts[1]) : null;
            if ($tid > 0) {
                $team_assignments[] = ['team_id' => $tid, 'season_id' => $sid];
            }
        }
        
        // Remove all existing team roster entries for this athlete
        $pdo->prepare("DELETE FROM team_roster WHERE athlete_id = ?")->execute([$user_id_to_update]);
        
        // Insert new team assignments
        if (!empty($team_assignments)) {
            $insert_team_stmt = $pdo->prepare("
                INSERT INTO team_roster (team_id, athlete_id, season_id, jersey_number, position) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE jersey_number = VALUES(jersey_number), position = VALUES(position)
            ");
            foreach ($team_assignments as $ta) {
                $insert_team_stmt->execute([$ta['team_id'], $user_id_to_update, $ta['season_id'], $jersey_number, $position]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Assignments updated successfully']);
        Auditor::log($pdo, $user_id, 'update', 'users', $user_id_to_update, ['action' => 'admin_update_assignments']);
    } catch (PDOException $e) {
        ErrorLogger::error("Admin assignments update error:" . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit();
}

// =========================================================
// MODULE 8.8: ADMIN NOTIFICATION PREFERENCES
// =========================================================
if ($action == 'admin_update_notifications') {
    header('Content-Type: application/json');
    
    try {
        $user_id_to_update = intval($_POST['user_id'] ?? 0);
        
        if ($user_id_to_update <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit();
        }
        
        $preferences = [
            'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
            'session_reminders' => isset($_POST['session_reminders']) ? '1' : '0',
            'goal_updates' => isset($_POST['goal_updates']) ? '1' : '0',
            'marketing_emails' => isset($_POST['marketing_emails']) ? '1' : '0'
        ];
        
        foreach ($preferences as $key => $value) {
            // Try to update, if no rows affected then insert
            $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, preference_key, preference_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE preference_value = ?");
            $stmt->execute([$user_id_to_update, $key, $value, $value]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Notification settings updated successfully']);
        Auditor::log($pdo, $user_id, 'update', 'users', $user_id_to_update, ['action' => 'admin_update_notifications']);
    } catch (PDOException $e) {
        ErrorLogger::error("Admin notifications update error:" . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit();
}

// =========================================================
// MODULE 8.7: ADMIN ROLE MANAGEMENT
// =========================================================
if ($action == 'admin_update_roles') {
    header('Content-Type: application/json');
    
    try {
        $user_id_to_update = intval($_POST['user_id'] ?? 0);
        
        if ($user_id_to_update <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit();
        }
        
        $extra_roles = $_POST['extra_roles'] ?? [];
        $valid_roles = ['admin', 'coach', 'health_coach', 'team_coach', 'athlete', 'parent', 'front_desk_staff', 'hr', 'accounting'];
        $admin_id = $_SESSION['user_id'];
        
        // Ensure user_roles table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `user_roles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `role` ENUM('athlete', 'coach', 'admin', 'parent', 'health_coach', 'team_coach', 'front_desk_staff', 'hr', 'accounting') NOT NULL,
            `assigned_by` INT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_user_role` (`user_id`, `role`),
            INDEX `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $pdo->beginTransaction();
        
        // Remove all existing extra roles
        $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$user_id_to_update]);
        
        // Insert new extra roles
        if (is_array($extra_roles) && !empty($extra_roles)) {
            $insert_stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role, assigned_by) VALUES (?, ?, ?)");
            foreach ($extra_roles as $role) {
                $role = trim($role);
                if (in_array($role, $valid_roles)) {
                    $insert_stmt->execute([$user_id_to_update, $role, $admin_id]);
                }
            }
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'User roles updated successfully']);
        Auditor::log($pdo, $user_id, 'update', 'users', $user_id_to_update, ['action' => 'admin_update_roles']);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        ErrorLogger::error("Admin roles update error:" . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit();
}

// =========================================================
// MODULE 8.8: ADMIN PARENT ASSIGNMENT
// =========================================================
if ($action == 'admin_assign_parent') {
    header('Content-Type: application/json');
    
    try {
        $athlete_id = intval($_POST['user_id'] ?? 0);
        $parent_id = intval($_POST['parent_id'] ?? 0);
        $relationship = trim($_POST['relationship'] ?? 'Parent');
        
        if ($athlete_id <= 0 || $parent_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user or parent ID']);
            exit();
        }
        
        if ($athlete_id === $parent_id) {
            echo json_encode(['success' => false, 'message' => 'Cannot assign a user as their own parent']);
            exit();
        }
        
        // Check if already assigned
        $check = $pdo->prepare("SELECT id FROM managed_athletes WHERE parent_id = ? AND athlete_id = ?");
        $check->execute([$parent_id, $athlete_id]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This parent is already assigned to this user']);
            exit();
        }
        
        // Create the assignment
        $stmt = $pdo->prepare("INSERT INTO managed_athletes (parent_id, athlete_id, relationship, can_book, can_view_stats) VALUES (?, ?, ?, 1, 1)");
        $stmt->execute([$parent_id, $athlete_id, $relationship]);
        Auditor::log($pdo, $user_id, 'create', 'parent_athlete', $pdo->lastInsertId(), ['action' => 'admin_assign_parent']);
        
        echo json_encode(['success' => true, 'message' => 'Parent assigned successfully']);
    } catch (PDOException $e) {
        ErrorLogger::error("Admin parent assignment error:" . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit();
}

if ($action == 'admin_remove_parent') {
    header('Content-Type: application/json');
    
    try {
        $managed_id = intval($_POST['managed_id'] ?? 0);
        
        if ($managed_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
            exit();
        }
        
        $pdo->prepare("DELETE FROM managed_athletes WHERE id = ?")->execute([$managed_id]);
        Auditor::log($pdo, $user_id, 'delete', 'parent_athlete', 0, ['action' => 'admin_remove_parent']);
        
        echo json_encode(['success' => true, 'message' => 'Parent assignment removed']);
    } catch (PDOException $e) {
        ErrorLogger::error("Admin remove parent error:" . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit();
}

// =========================================================
// MODULE 8.9: FILTERED USER EXPORT
// =========================================================
if ($action == 'export_users') {
    try {
        // Get filter values
        $role_filter = $_POST['filter_role'] ?? '';
        $status_filter = $_POST['filter_status'] ?? '';
        $team_filter = $_POST['filter_team'] ?? '';
        $age_filter = $_POST['filter_age'] ?? '';
        $search = $_POST['filter_search'] ?? '';
        
        // Build query with filters
        $where = [];
        $params = [];
        $join_clauses = "";
        
        if (!empty($role_filter)) {
            $where[] = "u.role = ?";
            $params[] = $role_filter;
        }
        
        if (!empty($status_filter)) {
            if ($status_filter === 'active') {
                $where[] = "u.is_verified = 1";
            } elseif ($status_filter === 'inactive') {
                $where[] = "u.is_verified = 0";
            }
        }
        
        if (!empty($search)) {
            $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if (!empty($team_filter)) {
            $join_clauses .= " LEFT JOIN team_roster tr ON u.id = tr.athlete_id";
            $where[] = "tr.team_id = ?";
            $params[] = $team_filter;
        }
        
        if (!empty($age_filter)) {
            switch ($age_filter) {
                case 'u10':
                    $where[] = "TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) < 10";
                    break;
                case 'u12':
                    $where[] = "TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) >= 10 AND TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) < 12";
                    break;
                case 'u14':
                    $where[] = "TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) >= 12 AND TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) < 14";
                    break;
                case 'u16':
                    $where[] = "TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) >= 14 AND TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) < 16";
                    break;
                case 'u18':
                    $where[] = "TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) >= 16 AND TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) < 18";
                    break;
                case '18plus':
                    $where[] = "TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) >= 18";
                    break;
            }
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.role, 
                   u.is_verified, u.created_at, u.birth_date,
                   coach.first_name as coach_first_name, coach.last_name as coach_last_name,
                   t.name as team_name,
                   COUNT(DISTINCT s.id) as session_count
            FROM users u
            LEFT JOIN sessions s ON u.id = s.coach_id
            LEFT JOIN users coach ON u.assigned_coach_id = coach.id
            LEFT JOIN team_roster tr2 ON u.id = tr2.athlete_id
            LEFT JOIN teams t ON tr2.team_id = t.id
            $join_clauses
            $where_clause
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $users = decryptUserRows($users);
        
        // Set CSV headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        fputcsv($output, ['ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Role', 'Status', 'Birth Date', 'Coach', 'Team', 'Sessions', 'Created']);
        
        // Write data
        foreach ($users as $user) {
            $coach_name = '';
            if (!empty($user['coach_first_name'])) {
                $coach_name = $user['coach_first_name'] . ' ' . $user['coach_last_name'];
            }
            
            fputcsv($output, [
                $user['id'],
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['phone'] ?? '',
                ucfirst(str_replace('_', ' ', $user['role'])),
                $user['is_verified'] ? 'Active' : 'Inactive',
                $user['birth_date'] ?? '',
                $coach_name,
                $user['team_name'] ?? '',
                $user['session_count'],
                date('Y-m-d', strtotime($user['created_at']))
            ]);
        }
        
        fclose($output);
        exit();
    } catch (PDOException $e) {
        ErrorLogger::error("Export users error: " . $e->getMessage());
        header("Location: dashboard.php?page=all_users&status=export_error");
        exit();
    }
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
        $users = decryptUserRows($users);
        
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
        ErrorLogger::error("Export users error: " . $e->getMessage());
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
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        // Skills are evaluation skills tied to categories
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
        $newSkillId = $pdo->lastInsertId();
        
        // Also insert into junction table for multi-category support
        $pdo->prepare("
            INSERT IGNORE INTO eval_skill_categories (skill_id, category_id, created_at)
            VALUES (?, ?, NOW())
        ")->execute([$newSkillId, $category_id]);
        
        Auditor::log($pdo, $user_id, 'create', 'skills', $newSkillId, ['action' => 'create_skill']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Skill created successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&status=skill_added");
    } catch (PDOException $e) {
        ErrorLogger::error("Create skill error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to create skill']);
            exit();
        }
        header("Location: dashboard.php?page=categories&status=error");
    }
    exit();
}

if ($action == 'edit' && isset($_POST['type']) && $_POST['type'] == 'skill') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($id <= 0 || empty($name)) {
            throw new Exception('Skill ID and name are required');
        }
        
        $stmt = $pdo->prepare("UPDATE eval_skills SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $id]);
        Auditor::log($pdo, $user_id, 'update', 'skills', intval($_POST['id']), ['action' => 'edit_skill']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Skill updated successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&status=skill_updated");
    } catch (PDOException $e) {
        ErrorLogger::error("Edit skill database error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update skill. Please try again.']);
            exit();
        }
        header("Location: dashboard.php?page=categories&status=error");
    } catch (Exception $e) {
        ErrorLogger::error("Edit skill error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=categories&status=error");
    }
    exit();
}

if ($action == 'delete' && isset($_POST['type']) && $_POST['type'] == 'skill') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception('Invalid skill ID');
        }
        
        $stmt = $pdo->prepare("DELETE FROM eval_skills WHERE id = ?");
        $stmt->execute([$id]);
        Auditor::log($pdo, $user_id, 'delete', 'skills', intval($_POST['id']), ['action' => 'delete_skill']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Skill deleted successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&status=skill_deleted");
    } catch (PDOException $e) {
        ErrorLogger::error("Delete skill database error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete skill. Please try again.']);
            exit();
        }
        header("Location: dashboard.php?page=categories&status=error");
    } catch (Exception $e) {
        ErrorLogger::error("Delete skill error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=categories&status=error");
    }
    exit();
}

// === DRILL TYPES MANAGEMENT ===
if ($action == 'create_drill_type') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        // Validate name field
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            throw new Exception('Drill type name is required');
        }
        
        // Validate position_type
        $validPositions = ['player', 'goalie', 'both'];
        $positionType = isset($_POST['position_type']) && in_array($_POST['position_type'], $validPositions) 
            ? $_POST['position_type'] 
            : 'both';
        
        $stmt = $pdo->prepare("INSERT INTO drill_categories (name, description, position_type) VALUES (?, ?, ?)");
        $stmt->execute([
            $name,
            trim($_POST['description'] ?? ''),
            $positionType
        ]);
        Auditor::log($pdo, $user_id, 'create', 'drill_types', $pdo->lastInsertId(), ['action' => 'create_drill_type']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Drill type created successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=drills&status=drill_type_added");
    } catch (Exception $e) {
        ErrorLogger::error("Create drill type error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage() ?: 'Failed to create drill type']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=drills&status=error");
    }
    exit();
}

if ($action == 'edit' && isset($_POST['type']) && $_POST['type'] == 'drill_type') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        // Validate id and name
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id <= 0 || empty($name)) {
            throw new Exception('Invalid drill type data');
        }
        
        // Validate position_type
        $validPositions = ['player', 'goalie', 'both'];
        $positionType = isset($_POST['position_type']) && in_array($_POST['position_type'], $validPositions) 
            ? $_POST['position_type'] 
            : 'both';
        
        $stmt = $pdo->prepare("UPDATE drill_categories SET name = ?, description = ?, position_type = ? WHERE id = ?");
        $stmt->execute([
            $name,
            trim($_POST['description'] ?? ''),
            $positionType,
            $id
        ]);
        Auditor::log($pdo, $user_id, 'update', 'drill_types', intval($_POST['id']), ['action' => 'edit_drill_type']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Drill type updated successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=drills&status=drill_type_updated");
    } catch (PDOException $e) {
        ErrorLogger::error("Edit drill type database error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update drill type. Please try again.']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=drills&status=error");
    } catch (Exception $e) {
        ErrorLogger::error("Edit drill type error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=drills&status=error");
    }
    exit();
}

if ($action == 'delete' && isset($_POST['type']) && $_POST['type'] == 'drill_type') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception('Invalid drill type ID');
        }
        
        $stmt = $pdo->prepare("DELETE FROM drill_categories WHERE id = ?");
        $stmt->execute([$id]);
        Auditor::log($pdo, $user_id, 'delete', 'drill_types', intval($_POST['id']), ['action' => 'delete_drill_type']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Drill type deleted successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=drills&status=drill_type_deleted");
    } catch (PDOException $e) {
        ErrorLogger::error("Delete drill type database error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete drill type. Please try again.']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=drills&status=error");
    } catch (Exception $e) {
        ErrorLogger::error("Delete drill type error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=drills&status=error");
    }
    exit();
}

// === MERCHANDISE CATEGORIES MANAGEMENT ===
// Manages merchandise categories for shop organization
if ($action == 'create_merchandise_category') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        
        if (empty($name)) {
            throw new Exception('Category name is required');
        }
        
        $stmt = $pdo->prepare("INSERT INTO merchandise_categories (name, description, parent_id, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$name, $description, $parentId]);
        Auditor::log($pdo, $user_id, 'create', 'merchandise_categories', $pdo->lastInsertId(), ['action' => 'create_merchandise_category']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Merchandise category created successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=merchandise&status=category_added");
    } catch (Exception $e) {
        ErrorLogger::error("Create merchandise category error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=merchandise&status=error");
    }
    exit();
}

if ($action == 'edit' && isset($_POST['type']) && $_POST['type'] == 'merchandise') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        
        if ($id <= 0 || empty($name)) {
            throw new Exception('Invalid category data');
        }
        
        // Prevent setting self as parent
        if ($parentId === $id) {
            throw new Exception('Category cannot be its own parent');
        }
        
        // Prevent circular references
        if ($parentId !== null) {
            $checkId = $parentId;
            $visited = [$id];
            while ($checkId !== null) {
                if (in_array($checkId, $visited)) {
                    throw new Exception('Cannot set parent: would create a circular reference');
                }
                $visited[] = $checkId;
                $ancestorStmt = $pdo->prepare("SELECT parent_id FROM merchandise_categories WHERE id = ?");
                $ancestorStmt->execute([$checkId]);
                $ancestor = $ancestorStmt->fetch(PDO::FETCH_ASSOC);
                $checkId = ($ancestor && !empty($ancestor['parent_id'])) ? intval($ancestor['parent_id']) : null;
            }
        }
        
        $stmt = $pdo->prepare("UPDATE merchandise_categories SET name = ?, description = ?, parent_id = ? WHERE id = ?");
        $stmt->execute([$name, $description, $parentId, $id]);
        Auditor::log($pdo, $user_id, 'update', 'merchandise_categories', intval($_POST['id']), ['action' => 'edit_merchandise_category']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Merchandise category updated successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=merchandise&status=category_updated");
    } catch (Exception $e) {
        ErrorLogger::error("Edit merchandise category error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=merchandise&status=error");
    }
    exit();
}

if ($action == 'delete' && isset($_POST['type']) && $_POST['type'] == 'merchandise') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $id = intval($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            throw new Exception('Invalid category ID');
        }
        
        $stmt = $pdo->prepare("DELETE FROM merchandise_categories WHERE id = ?");
        $stmt->execute([$id]);
        Auditor::log($pdo, $user_id, 'delete', 'merchandise_categories', intval($_POST['id']), ['action' => 'delete_merchandise_category']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Merchandise category deleted successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=merchandise&status=category_deleted");
    } catch (Exception $e) {
        ErrorLogger::error("Delete merchandise category error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=merchandise&status=error");
    }
    exit();
}

// === TEAMS MANAGEMENT ===
// Manages teams for session assignments and athlete rosters
if ($action == 'create_team') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    $name = trim($_POST['name'] ?? '');
    $age_group = trim($_POST['age_group'] ?? '');
    $skill_level = trim($_POST['skill_level'] ?? '');
    $division = trim($_POST['division'] ?? '');
    $season = trim($_POST['season'] ?? '');
    $season_ids = $_POST['season_ids'] ?? [];
    $coach_id = !empty($_POST['coach_id']) ? intval($_POST['coach_id']) : null;
    $assistant_coach_id = !empty($_POST['assistant_coach_id']) ? intval($_POST['assistant_coach_id']) : null;
    
    // Handle team logo upload
    $logo_url = null;
    if (!empty($_FILES['team_logo']) && $_FILES['team_logo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $max_size = 100 * 1024 * 1024;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['team_logo']['tmp_name']);
        finfo_close($finfo);
        
        $ext = strtolower(pathinfo($_FILES['team_logo']['name'], PATHINFO_EXTENSION));
        if (in_array($mime, $allowed_types) && in_array($ext, $allowed_extensions) && $_FILES['team_logo']['size'] <= $max_size) {
            $filename = 'team_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $persist = persistUploadedFile($pdo, $_FILES['team_logo']['tmp_name'], 'team_logos', $filename);
            $logo_url = $persist['rustfs_url'] ?? null;
        }
    }
    
    if (empty($name)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Team name is required']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=teams&status=error&message=team_name_required");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO teams (name, age_group, skill_level, division, season, coach_id, assistant_coach_id, logo_url, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$name, $age_group ?: null, $skill_level ?: null, $division ?: null, $season ?: null, $coach_id, $assistant_coach_id, $logo_url]);
        
        $new_team_id = $pdo->lastInsertId();
        
        // Create team_seasons entries for selected seasons
        if (!empty($season_ids) && $new_team_id) {
            $ts_stmt = $pdo->prepare("INSERT IGNORE INTO team_seasons (team_id, season_id) VALUES (?, ?)");
            foreach ($season_ids as $sid) {
                $sid = intval($sid);
                if ($sid > 0) {
                    $ts_stmt->execute([$new_team_id, $sid]);
                }
            }
        }
        Auditor::log($pdo, $user_id, 'create', 'teams', $new_team_id, ['action' => 'create_team']);
        
        // Store persistent path for recovery
        if (!empty($logo_url) && isset($persist) && !empty($persist['nextcloud_path'])) {
            try {
                $pdo->prepare("UPDATE teams SET nextcloud_logo_path = ? WHERE id = ?")->execute([$persist['nextcloud_path'], $new_team_id]);
            } catch (Exception $e) {
                error_log("Team logo persistent path save failed: " . $e->getMessage());
            }
        }
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Team created successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=teams&status=success&message=team_created");
    } catch (PDOException $e) {
        ErrorLogger::error("Error creating team:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to create team']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=teams&status=error");
    }
    exit();
}

if ($action == 'edit' && isset($_POST['type']) && $_POST['type'] == 'team') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $age_group = trim($_POST['age_group'] ?? '');
        $skill_level = trim($_POST['skill_level'] ?? '');
        $division = trim($_POST['division'] ?? '');
        $season = trim($_POST['season'] ?? '');
        $season_ids = $_POST['season_ids'] ?? [];
        $coach_id = !empty($_POST['coach_id']) ? intval($_POST['coach_id']) : null;
        $assistant_coach_id = !empty($_POST['assistant_coach_id']) ? intval($_POST['assistant_coach_id']) : null;
        $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
        
        // Handle team logo upload
        $logo_url = trim($_POST['existing_logo_url'] ?? '');
        if (!empty($_FILES['team_logo']) && $_FILES['team_logo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            $max_size = 100 * 1024 * 1024;
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['team_logo']['tmp_name']);
            finfo_close($finfo);
            
            $ext = strtolower(pathinfo($_FILES['team_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($mime, $allowed_types) && in_array($ext, $allowed_extensions) && $_FILES['team_logo']['size'] <= $max_size) {
                $filename = 'team_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $persist = persistUploadedFile($pdo, $_FILES['team_logo']['tmp_name'], 'team_logos', $filename);
                $logo_url = $persist['rustfs_url'] ?? null;
            }
        }
        
        if ($id <= 0 || empty($name)) {
            throw new Exception('Team ID and name are required');
        }
        
        $stmt = $pdo->prepare("UPDATE teams SET name = ?, age_group = ?, skill_level = ?, division = ?, season = ?, coach_id = ?, assistant_coach_id = ?, logo_url = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $age_group ?: null, $skill_level ?: null, $division ?: null, $season ?: null, $coach_id, $assistant_coach_id, $logo_url ?: null, $is_active, $id]);
        
        // Sync team_seasons: remove old, add new
        $pdo->prepare("DELETE FROM team_seasons WHERE team_id = ?")->execute([$id]);
        if (!empty($season_ids)) {
            $ts_stmt = $pdo->prepare("INSERT IGNORE INTO team_seasons (team_id, season_id) VALUES (?, ?)");
            foreach ($season_ids as $sid) {
                $sid = intval($sid);
                if ($sid > 0) {
                    $ts_stmt->execute([$id, $sid]);
                }
            }
        }
        Auditor::log($pdo, $user_id, 'update', 'teams', intval($_POST['id']), ['action' => 'edit_team']);
        
        // Store persistent path for recovery
        if (!empty($logo_url) && isset($persist) && !empty($persist['nextcloud_path'])) {
            try {
                $pdo->prepare("UPDATE teams SET nextcloud_logo_path = ? WHERE id = ?")->execute([$persist['nextcloud_path'], $id]);
            } catch (Exception $e) {
                error_log("Team logo persistent path save failed: " . $e->getMessage());
            }
        }
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Team updated successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=teams&status=team_updated");
    } catch (Exception $e) {
        ErrorLogger::error("Edit team error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=teams&status=error");
    }
    exit();
}

if ($action == 'delete' && isset($_POST['type']) && $_POST['type'] == 'team') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $id = intval($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            throw new Exception('Invalid team ID');
        }
        
        // Check if team has sessions
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE team_id = ?");
        $check_stmt->execute([$id]);
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception('Cannot delete team with assigned sessions');
        }
        
        $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
        $stmt->execute([$id]);
        Auditor::log($pdo, $user_id, 'delete', 'teams', intval($_POST['id']), ['action' => 'delete_team']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Team deleted successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=teams&status=team_deleted");
    } catch (Exception $e) {
        ErrorLogger::error("Delete team error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=teams&status=error");
    }
    exit();
}

// === LOCATIONS EDIT/DELETE VIA GENERIC TYPE ===
if ($action == 'edit' && isset($_POST['type']) && $_POST['type'] == 'location') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
        $google_place_id = trim($_POST['google_place_id'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        
        if ($id <= 0 || empty($name) || empty($city)) {
            throw new Exception('Location ID, name, and city are required');
        }
        
        $stmt = $pdo->prepare("UPDATE locations SET name = ?, address = ?, city = ?, province = ?, postal_code = ?, phone = ?, is_active = ?, google_place_id = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$name, $address ?: null, $city, $province ?: null, $postal_code ?: null, $phone ?: null, $is_active, $google_place_id ?: null, $image_url ?: null, $id]);
        Auditor::log($pdo, $user_id, 'update', 'locations', intval($_POST['id']), ['action' => 'edit_location']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Location updated successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=locations&status=location_updated");
    } catch (Exception $e) {
        ErrorLogger::error("Edit location error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=locations&status=error");
    }
    exit();
}

if ($action == 'delete' && isset($_POST['type']) && $_POST['type'] == 'location') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $id = intval($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            throw new Exception('Invalid location ID');
        }
        
        // Check if location has sessions
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions s INNER JOIN locations l ON s.arena = l.name WHERE l.id = ?");
        $check_stmt->execute([$id]);
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception('Cannot delete location with assigned sessions');
        }
        
        $stmt = $pdo->prepare("DELETE FROM locations WHERE id = ?");
        $stmt->execute([$id]);
        Auditor::log($pdo, $user_id, 'delete', 'locations', intval($_POST['id']), ['action' => 'delete_location']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Location deleted successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=locations&status=location_deleted");
    } catch (Exception $e) {
        ErrorLogger::error("Delete location error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=locations&status=error");
    }
    exit();
}

// === POSITIONS MANAGEMENT ===
// Manages player positions (Forward, Defense, Goalie variations)
if ($action == 'create_position') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    $name = $_POST['name'] ?? '';
    $abbreviation = $_POST['abbreviation'] ?? '';
    $description = $_POST['description'] ?? '';
    $position_type = $_POST['position_type'] ?? null;
    
    // Convert empty string to null for position_type
    if ($position_type === '') {
        $position_type = null;
    }
    
    if (empty($name)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Position name is required']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=positions&status=error&message=position_name_required");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO player_positions (name, abbreviation, description, position_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $abbreviation, $description, $position_type]);
        Auditor::log($pdo, $user_id, 'create', 'positions', $pdo->lastInsertId(), ['action' => 'create_position']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Position created successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=positions&status=success&message=position_created");
    } catch (PDOException $e) {
        ErrorLogger::error("Error creating position:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to create position']);
            exit();
        }
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
        Auditor::log($pdo, $user_id, 'update', 'positions', $id, ['action' => 'update_position']);
        
        header("Location: dashboard.php?page=categories&tab=positions&status=success&message=position_updated");
    } catch (PDOException $e) {
        ErrorLogger::error("Error updating position:" . $e->getMessage());
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
        Auditor::log($pdo, $user_id, 'delete', 'positions', $id, ['action' => 'delete_position']);
        
        echo json_encode(['success' => true, 'message' => 'Position deleted successfully']);
    } catch (PDOException $e) {
        ErrorLogger::error("Error deleting position:" . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to delete position']);
    }
    exit();
}

// === EQUIPMENT MANAGEMENT ===
// Note: The equipment table is designed for inventory tracking, not category management
// This might need clarification on whether we want equipment categories or equipment items
if ($action == 'create_equipment') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
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
        Auditor::log($pdo, $user_id, 'create', 'equipment', $pdo->lastInsertId(), ['action' => 'create_equipment']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Equipment created successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=equipment&status=equipment_added");
    } catch (PDOException $e) {
        ErrorLogger::error("Create equipment error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to create equipment']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=equipment&status=error");
    }
    exit();
}

if ($action == 'edit' && isset($_POST['type']) && $_POST['type'] == 'equipment') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($id <= 0 || empty($name)) {
            throw new Exception('Equipment ID and name are required');
        }
        
        $stmt = $pdo->prepare("UPDATE equipment SET name = ?, notes = ? WHERE id = ?");
        $stmt->execute([$name, $description, $id]);
        Auditor::log($pdo, $user_id, 'update', 'equipment', intval($_POST['id']), ['action' => 'edit_equipment']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Equipment updated successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=equipment&status=equipment_updated");
    } catch (PDOException $e) {
        ErrorLogger::error("Edit equipment database error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update equipment. Please try again.']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=equipment&status=error");
    } catch (Exception $e) {
        ErrorLogger::error("Edit equipment error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=equipment&status=error");
    }
    exit();
}

if ($action == 'delete' && isset($_POST['type']) && $_POST['type'] == 'equipment') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception('Invalid equipment ID');
        }
        
        $stmt = $pdo->prepare("DELETE FROM equipment WHERE id = ?");
        $stmt->execute([$id]);
        Auditor::log($pdo, $user_id, 'delete', 'equipment', intval($_POST['id']), ['action' => 'delete_equipment']);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Equipment deleted successfully!']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=equipment&status=equipment_deleted");
    } catch (PDOException $e) {
        ErrorLogger::error("Delete equipment database error:" . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete equipment. Please try again.']);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=equipment&status=error");
    } catch (Exception $e) {
        ErrorLogger::error("Delete equipment error: " . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        header("Location: dashboard.php?page=categories&tab=equipment&status=error");
    }
    exit();
}

// Fallback
header("Location: dashboard.php");
exit();
?>