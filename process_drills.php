<?php
/**
 * Process Drill Operations
 * Handles CRUD operations for drills, categories, and drill management
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

// Set security headers
setSecurityHeaders();

// Validate CSRF token
checkCsrfToken();

$action = $_POST['action'] ?? '';

// =========================================================
// CREATE/UPDATE DRILL
// =========================================================
if ($action === 'save_drill' || $action === 'create') {
    requirePermission($pdo, $user_id, $user_role, 'create_drills');
    
    $drill_id = !empty($_POST['drill_id']) ? intval($_POST['drill_id']) : null;
    $title = trim($_POST['title'] ?? $_POST['drill_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_name = trim($_POST['category'] ?? '');
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $diagram_data = trim($_POST['diagram_data'] ?? '');
    $duration = !empty($_POST['duration_minutes']) ? intval($_POST['duration_minutes']) : (!empty($_POST['duration']) ? intval($_POST['duration']) : null);
    $skill_level = $_POST['skill_level'] ?? 'all';
    $age_group = trim($_POST['age_group'] ?? '');
    $num_players = trim($_POST['num_players'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $equipment = isset($_POST['equipment']) && is_array($_POST['equipment']) ? implode(', ', $_POST['equipment']) : trim($_POST['equipment_needed'] ?? '');
    $coaching_points = trim($_POST['coaching_points'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $tags_input = $_POST['tags'] ?? '';
    $tags = is_array($tags_input) ? $tags_input : array_map('trim', explode(',', $tags_input));
    
    // If category name is provided instead of ID, look up or create the category
    if (empty($category_id) && !empty($category_name)) {
        $stmt = $pdo->prepare("SELECT id FROM drill_categories WHERE name = ?");
        $stmt->execute([$category_name]);
        $cat = $stmt->fetch();
        if ($cat) {
            $category_id = $cat['id'];
        } else {
            // Create new category (drill_categories table has: id, name, description, created_at)
            $stmt = $pdo->prepare("INSERT INTO drill_categories (name) VALUES (?)");
            $stmt->execute([$category_name]);
            $category_id = $pdo->lastInsertId();
        }
    }
    
    if (empty($title)) {
        header("Location: dashboard.php?page=drills&error=title_required");
        exit();
    }
    
    try {
        // NOTE: The drills table schema only has title, description, category_id, diagram_data, video_url
        // Additional fields (duration, skill_level, equipment, etc.) are combined into description
        // to avoid schema changes while preserving the information
        $full_description = $description;
        if (!empty($instructions)) {
            $full_description .= "\n\n**Instructions:**\n" . $instructions;
        }
        if (!empty($num_players)) {
            $full_description .= "\n\n**Players:** " . $num_players;
        }
        if (!empty($equipment)) {
            $full_description .= "\n\n**Equipment:** " . $equipment;
        }
        
        if ($drill_id) {
            // Update existing drill
            $stmt = $pdo->prepare("
                UPDATE drills SET 
                    title = ?, description = ?, category_id = ?, diagram_data = ?,
                    video_url = ?, updated_at = NOW()
                WHERE id = ? AND created_by = ?
            ");
            $stmt->execute([
                $title, $full_description, $category_id, $diagram_data,
                $video_url, $drill_id, $user_id
            ]);
            
            // Delete old tags
            $pdo->prepare("DELETE FROM drill_tags WHERE drill_id = ?")->execute([$drill_id]);
        } else {
            // Insert new drill
            $stmt = $pdo->prepare("
                INSERT INTO drills (
                    title, description, category_id, diagram_data, video_url, created_by
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title, $full_description, $category_id, $diagram_data, $video_url, $user_id
            ]);
            $drill_id = $pdo->lastInsertId();
        }
        
        // Insert tags
        if (!empty($tags) && is_array($tags)) {
            $tag_stmt = $pdo->prepare("INSERT INTO drill_tags (drill_id, tag) VALUES (?, ?)");
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $tag_stmt->execute([$drill_id, $tag]);
                }
            }
        }
        
        header("Location: dashboard.php?page=drills&status=drill_saved");
        exit();
        
    } catch (PDOException $e) {
        header("Location: dashboard.php?page=drills&error=save_failed");
        exit();
    }
}

// =========================================================
// DELETE DRILL
// =========================================================
if ($action === 'delete_drill') {
    requirePermission($pdo, $user_id, $user_role, 'delete_drills');
    
    $drill_id = intval($_POST['drill_id']);
    
    try {
        $pdo->prepare("DELETE FROM drills WHERE id = ? AND created_by = ?")->execute([$drill_id, $user_id]);
        header("Location: dashboard.php?page=drills&status=drill_deleted");
        exit();
    } catch (PDOException $e) {
        header("Location: dashboard.php?page=drills&error=delete_failed");
        exit();
    }
}

// =========================================================
// CREATE CATEGORY (Admin Only)
// =========================================================
if ($action === 'create_category') {
    requirePermission($pdo, $user_id, $user_role, 'manage_drill_categories');
    
    $name = trim($_POST['category_name']);
    $description = trim($_POST['category_description'] ?? '');
    
    if (empty($name)) {
        header("Location: dashboard.php?page=drills&error=category_name_required");
        exit();
    }
    
    try {
        // drill_categories table has: id, name, description, created_at (no created_by column)
        $stmt = $pdo->prepare("INSERT INTO drill_categories (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        header("Location: dashboard.php?page=drills&status=category_created");
        exit();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            header("Location: dashboard.php?page=drills&error=category_exists");
        } else {
            header("Location: dashboard.php?page=drills&error=category_failed");
        }
        exit();
    }
}

// =========================================================
// DELETE CATEGORY (Admin Only)
// =========================================================
if ($action === 'delete_category') {
    requirePermission($pdo, $user_id, $user_role, 'manage_drill_categories');
    
    $category_id = intval($_POST['category_id']);
    
    try {
        $pdo->prepare("DELETE FROM drill_categories WHERE id = ?")->execute([$category_id]);
        header("Location: dashboard.php?page=drills&status=category_deleted");
        exit();
    } catch (PDOException $e) {
        header("Location: dashboard.php?page=drills&error=category_delete_failed");
        exit();
    }
}

// =========================================================
// IMPORT FROM IHS (Ice Hockey Systems)
// =========================================================
if ($action === 'import_ihs') {
    requirePermission($pdo, $user_id, $user_role, 'create_drills');
    
    $ihs_id = trim($_POST['ihs_id'] ?? '');
    $title = trim($_POST['drill_name'] ?? '');
    $category_name = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = intval($_POST['duration'] ?? 0);
    $skill_level = trim($_POST['skill_level'] ?? '');
    
    if (empty($title)) {
        header("Location: dashboard.php?page=import_drill&error=title_required");
        exit();
    }
    
    try {
        // Look up or create the category
        $category_id = null;
        if (!empty($category_name)) {
            $stmt = $pdo->prepare("SELECT id FROM drill_categories WHERE name = ?");
            $stmt->execute([$category_name]);
            $cat = $stmt->fetch();
            if ($cat) {
                $category_id = $cat['id'];
            } else {
                // Create new category (drill_categories table has: id, name, description, created_at)
                $stmt = $pdo->prepare("INSERT INTO drill_categories (name) VALUES (?)");
                $stmt->execute([$category_name]);
                $category_id = $pdo->lastInsertId();
            }
        }
        
        // Build full description with additional info
        $full_description = $description;
        if ($duration > 0) {
            $full_description .= "\n\n**Duration:** " . $duration . " minutes";
        }
        if (!empty($skill_level)) {
            $full_description .= "\n\n**Skill Level:** " . $skill_level;
        }
        
        // Create IHS source URL reference
        $ihs_source_url = 'ihs://drill/' . $ihs_id;
        
        // Insert the drill
        $stmt = $pdo->prepare("
            INSERT INTO drills (title, description, category_id, ihs_source_url, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$title, $full_description, $category_id, $ihs_source_url, $user_id]);
        
        header("Location: dashboard.php?page=drill_library&status=drill_imported");
        exit();
        
    } catch (PDOException $e) {
        error_log("IHS Import Error: " . $e->getMessage());
        header("Location: dashboard.php?page=import_drill&error=import_failed");
        exit();
    }
}

// =========================================================
// IMPORT FROM URL (Ice Hockey Systems URL)
// =========================================================
if ($action === 'import_from_url') {
    requirePermission($pdo, $user_id, $user_role, 'create_drills');
    
    $ihs_url = trim($_POST['ihs_url'] ?? '');
    
    if (empty($ihs_url)) {
        header("Location: dashboard.php?page=import_drill&error=url_required");
        exit();
    }
    
    // Validate URL format
    if (!filter_var($ihs_url, FILTER_VALIDATE_URL)) {
        header("Location: dashboard.php?page=import_drill&error=invalid_url");
        exit();
    }
    
    // Validate that URL is from trusted hockey drill sources
    $url_parts = parse_url($ihs_url);
    $host = strtolower($url_parts['host'] ?? '');
    
    // List of allowed domains for drill imports
    $allowed_domains = [
        'icehockeysystems.com',
        'www.icehockeysystems.com',
        'hockeyshare.com',
        'www.hockeyshare.com',
        'hockeycoachingabcs.com',
        'www.hockeycoachingabcs.com'
    ];
    
    if (!in_array($host, $allowed_domains)) {
        header("Location: dashboard.php?page=import_drill&error=untrusted_domain");
        exit();
    }
    
    try {
        $path = $url_parts['path'] ?? '';
        
        // Extract drill ID from URL path (e.g., /drills/drill-name-123 or /drill/123)
        $drill_id = null;
        if (preg_match('/\/drills?\/([a-zA-Z0-9\-_]+)/', $path, $matches)) {
            $drill_id = $matches[1];
        }
        
        if (!$drill_id) {
            $drill_id = 'url-' . md5($ihs_url);
        }
        
        // Generate drill name from URL path
        $drill_name = ucwords(str_replace(['-', '_'], ' ', basename($path)));
        if (empty($drill_name) || $drill_name === '/' || strlen($drill_name) < 3) {
            // Count existing imported drills to create a unique name
            $count_stmt = $pdo->query("SELECT COUNT(*) FROM drills WHERE ihs_source_url IS NOT NULL");
            $import_count = $count_stmt->fetchColumn() + 1;
            $drill_name = 'Imported Drill #' . $import_count;
        }
        
        // Create description with source URL
        $description = "Imported from IHS Hockey.\n\n**Source:** " . $ihs_url;
        
        // Try to determine category from URL path
        $category_name = 'General';
        $category_keywords = [
            'skating' => 'Skating',
            'shooting' => 'Shooting',
            'passing' => 'Passing',
            'stickhandling' => 'Stickhandling',
            'defensive' => 'Defensive',
            'offensive' => 'Offensive',
            'goalie' => 'Goalie',
            'conditioning' => 'Conditioning',
            'team' => 'Team Play'
        ];
        
        $lower_url = strtolower($ihs_url);
        foreach ($category_keywords as $keyword => $cat_name) {
            if (strpos($lower_url, $keyword) !== false) {
                $category_name = $cat_name;
                break;
            }
        }
        
        // Look up or create the category
        $category_id = null;
        $stmt = $pdo->prepare("SELECT id FROM drill_categories WHERE name = ?");
        $stmt->execute([$category_name]);
        $cat = $stmt->fetch();
        if ($cat) {
            $category_id = $cat['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO drill_categories (name) VALUES (?)");
            $stmt->execute([$category_name]);
            $category_id = $pdo->lastInsertId();
        }
        
        // Check if this URL has already been imported
        $stmt = $pdo->prepare("SELECT id FROM drills WHERE ihs_source_url = ?");
        $stmt->execute([$ihs_url]);
        if ($stmt->fetch()) {
            header("Location: dashboard.php?page=import_drill&error=already_imported");
            exit();
        }
        
        // Insert the drill
        $stmt = $pdo->prepare("
            INSERT INTO drills (title, description, category_id, ihs_source_url, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$drill_name, $description, $category_id, $ihs_url, $user_id]);
        
        header("Location: dashboard.php?page=drill_library&status=drill_imported");
        exit();
        
    } catch (PDOException $e) {
        error_log("URL Import Error: " . $e->getMessage());
        header("Location: dashboard.php?page=import_drill&error=import_failed");
        exit();
    }
}

// Fallback
header("Location: dashboard.php?page=drills");
exit();
