<?php
/**
 * Process IHS Import Operations
 * Handles import of drills and practice plans from IHS Hockey format
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';

// Security check - must be logged in
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Check permission
requirePermission($pdo, $user_id, $user_role, 'import_from_ihs');

// Set security headers
setSecurityHeaders();

// Validate CSRF token
checkCsrfToken();

$action = $_POST['action'] ?? '';

// =========================================================
// IMPORT DRILLS
// =========================================================
if ($action === 'import_drills') {
    $drill_data = trim($_POST['drill_data'] ?? '');
    $auto_categorize = isset($_POST['auto_categorize']);
    $skip_duplicates = isset($_POST['skip_duplicates']);
    
    if (empty($drill_data)) {
        header("Location: dashboard.php?page=ihs_import&error=no_data");
        exit();
    }
    
    try {
        // Parse JSON
        $data = json_decode($drill_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            header("Location: dashboard.php?page=ihs_import&error=invalid_json");
            exit();
        }
        
        // Get drills array
        $drills = $data['drills'] ?? [$data];
        $imported = 0;
        $skipped = 0;
        
        $pdo->beginTransaction();
        
        foreach ($drills as $drill) {
            $title = trim($drill['title'] ?? '');
            
            if (empty($title)) {
                $skipped++;
                continue;
            }
            
            // Check for duplicates
            if ($skip_duplicates) {
                $stmt = $pdo->prepare("SELECT id FROM drills WHERE title = ?");
                $stmt->execute([$title]);
                if ($stmt->fetch()) {
                    $skipped++;
                    continue;
                }
            }
            
            // Auto-categorize if enabled
            $category_id = null;
            if ($auto_categorize && !empty($drill['category'])) {
                $stmt = $pdo->prepare("SELECT id FROM drill_categories WHERE name = ?");
                $stmt->execute([$drill['category']]);
                $cat = $stmt->fetch();
                if ($cat) {
                    $category_id = $cat['id'];
                }
            }
            
            // Insert drill
            $stmt = $pdo->prepare("
                INSERT INTO drills (
                    title, description, category_id, duration_minutes, skill_level,
                    age_group, equipment_needed, coaching_points, video_url,
                    imported_from_ihs, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
            ");
            $stmt->execute([
                $title,
                $drill['description'] ?? '',
                $category_id,
                $drill['duration'] ?? null,
                $drill['skill_level'] ?? 'all',
                $drill['age_group'] ?? '',
                $drill['equipment'] ?? '',
                $drill['coaching_points'] ?? '',
                $drill['video_url'] ?? '',
                $user_id
            ]);
            
            $imported++;
        }
        
        $pdo->commit();
        
        // Log the import
        logSecurityEvent($pdo, 'ihs_import_drills', "Imported $imported drills, skipped $skipped", $user_id);
        
        header("Location: dashboard.php?page=ihs_import&status=drills_imported&count=$imported&skipped=$skipped");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logSecurityEvent($pdo, 'ihs_import_error', "IHS import error: " . $e->getMessage(), $user_id);
        header("Location: dashboard.php?page=ihs_import&error=import_failed");
        exit();
    }
}

// =========================================================
// IMPORT PRACTICE PLANS
// =========================================================
if ($action === 'import_plans') {
    $plan_data = trim($_POST['plan_data'] ?? '');
    $create_missing = isset($_POST['create_missing_drills']);
    $make_public = isset($_POST['make_public']);
    
    if (empty($plan_data)) {
        header("Location: dashboard.php?page=ihs_import&error=no_data");
        exit();
    }
    
    try {
        // Parse JSON
        $data = json_decode($plan_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            header("Location: dashboard.php?page=ihs_import&error=invalid_json");
            exit();
        }
        
        // Get plan data
        $plan = $data['practice_plan'] ?? $data;
        $title = trim($plan['title'] ?? '');
        
        if (empty($title)) {
            header("Location: dashboard.php?page=ihs_import&error=missing_title");
            exit();
        }
        
        $pdo->beginTransaction();
        
        // Create practice plan
        $stmt = $pdo->prepare("
            INSERT INTO practice_plans (
                title, description, total_duration, age_group, focus_area,
                is_public, imported_from_ihs, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            $title,
            $plan['description'] ?? '',
            $plan['total_duration'] ?? 60,
            $plan['age_group'] ?? '',
            $plan['focus_area'] ?? '',
            $make_public ? 1 : 0,
            $user_id
        ]);
        
        $plan_id = $pdo->lastInsertId();
        
        // Import drills
        $drills = $plan['drills'] ?? [];
        foreach ($drills as $index => $drill_ref) {
            $drill_title = trim($drill_ref['title'] ?? '');
            
            if (empty($drill_title)) {
                continue;
            }
            
            // Find existing drill
            $stmt = $pdo->prepare("SELECT id FROM drills WHERE title = ? LIMIT 1");
            $stmt->execute([$drill_title]);
            $existing_drill = $stmt->fetch();
            
            if ($existing_drill) {
                $drill_id = $existing_drill['id'];
            } elseif ($create_missing) {
                // Create new drill
                $stmt = $pdo->prepare("
                    INSERT INTO drills (
                        title, description, duration_minutes, imported_from_ihs, created_by
                    ) VALUES (?, ?, ?, 1, ?)
                ");
                $stmt->execute([
                    $drill_title,
                    $drill_ref['description'] ?? '',
                    $drill_ref['duration'] ?? null,
                    $user_id
                ]);
                $drill_id = $pdo->lastInsertId();
            } else {
                // Skip if drill doesn't exist and we're not creating missing
                continue;
            }
            
            // Add drill to plan
            $stmt = $pdo->prepare("
                INSERT INTO practice_plan_drills (
                    plan_id, drill_id, order_index, duration_minutes, notes
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $plan_id,
                $drill_id,
                $index,
                $drill_ref['duration'] ?? null,
                $drill_ref['notes'] ?? ''
            ]);
        }
        
        $pdo->commit();
        
        // Log the import
        logSecurityEvent($pdo, 'ihs_import_plan', "Imported practice plan: $title", $user_id);
        
        header("Location: dashboard.php?page=ihs_import&status=plan_imported&id=$plan_id");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logSecurityEvent($pdo, 'ihs_import_error', "IHS import error: " . $e->getMessage(), $user_id);
        header("Location: dashboard.php?page=ihs_import&error=import_failed");
        exit();
    }
}

// Fallback
header("Location: dashboard.php?page=ihs_import");
exit();
