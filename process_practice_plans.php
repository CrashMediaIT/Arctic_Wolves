<?php
/**
 * Process Practice Plan Operations
 * Handles CRUD operations for practice plans
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
// CREATE/UPDATE PRACTICE PLAN
// =========================================================
if ($action === 'save_plan' || $action === 'create' || $action === 'update' || $action === 'update_plan') {
    requirePermission($pdo, $user_id, $user_role, 'create_practice_plans');
    
    $plan_id = !empty($_POST['plan_id']) ? intval($_POST['plan_id']) : null;
    $name = trim($_POST['title'] ?? $_POST['practice_title'] ?? $_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? $_POST['practice_goals'] ?? '');
    $drills = isset($_POST['drills']) ? json_decode($_POST['drills'], true) : [];
    
    if (empty($name)) {
        header("Location: dashboard.php?page=practice_plans&error=title_required");
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        if ($plan_id) {
            // Update existing plan - use columns from schema (name, description, version)
            $stmt = $pdo->prepare("
                UPDATE practice_plans SET 
                    name = ?, description = ?, version = version + 1,
                    updated_at = NOW()
                WHERE id = ? AND created_by = ?
            ");
            $stmt->execute([
                $name, $description, $plan_id, $user_id
            ]);
            
            // Delete old drills
            $pdo->prepare("DELETE FROM practice_plan_drills WHERE practice_plan_id = ?")->execute([$plan_id]);
        } else {
            // Insert new plan - use columns from schema (name, description, created_by)
            $stmt = $pdo->prepare("
                INSERT INTO practice_plans (name, description, created_by)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $description, $user_id]);
            $plan_id = $pdo->lastInsertId();
        }
        
        // Insert drills
        if (!empty($drills) && is_array($drills)) {
            $drill_stmt = $pdo->prepare("
                INSERT INTO practice_plan_drills (practice_plan_id, drill_id, drill_order, duration_minutes, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($drills as $index => $drill) {
                $drill_id = is_numeric($drill['id'] ?? $drill['drill_id'] ?? 0) ? intval($drill['id'] ?? $drill['drill_id']) : null;
                if ($drill_id) {
                    $drill_stmt->execute([
                        $plan_id,
                        $drill_id,
                        $index,
                        $drill['duration'] ?? null,
                        $drill['notes'] ?? null
                    ]);
                }
            }
        }
        
        $pdo->commit();
        
        // AJAX response for update_plan
        if ($action === 'update_plan' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Practice plan updated successfully', 'plan_id' => $plan_id]);
            exit();
        }
        
        // Redirect based on action - create goes back to practice_create, update/save_plan goes to practice_library
        if ($action === 'create') {
            header("Location: dashboard.php?page=practice_create&status=plan_created&plan_id=$plan_id");
        } else {
            header("Location: dashboard.php?page=practice_library&status=plan_saved");
        }
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        
        if ($action === 'update_plan' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update practice plan']);
            exit();
        }
        
        header("Location: dashboard.php?page=practice_library&error=save_failed");
        exit();
    }
}

// =========================================================
// GET PRACTICE PLAN (for viewing)
// =========================================================
if ($action === 'get_plan') {
    $plan_id = intval($_POST['plan_id']);
    
    try {
        // Get the practice plan
        $stmt = $pdo->prepare("
            SELECT pp.*, 
                   COALESCE(pp.title, pp.name) as title,
                   COALESCE(pp.total_duration, pp.duration_minutes, 60) as total_duration,
                   u.first_name as creator_first_name, u.last_name as creator_last_name
            FROM practice_plans pp
            LEFT JOIN users u ON pp.created_by = u.id
            WHERE pp.id = ?
        ");
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        $plan = decryptUserRow($plan);
        if ($plan) {
            $plan['creator_name'] = trim(($plan['creator_first_name'] ?? '') . ' ' . ($plan['creator_last_name'] ?? ''));
        }
        
        if (!$plan) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Practice plan not found']);
            exit();
        }
        
        // Get the drills for this plan
        $drill_stmt = $pdo->prepare("
            SELECT ppd.*, d.title, d.description, d.setup, d.coaching_points, d.progression,
                   d.diagram_data, d.custom_image, d.video_url, d.ihs_source_url,
                   dc.name as category_name
            FROM practice_plan_drills ppd
            LEFT JOIN drills d ON ppd.drill_id = d.id
            LEFT JOIN drill_categories dc ON d.category_id = dc.id
            WHERE ppd.practice_plan_id = ?
            ORDER BY ppd.drill_order ASC
        ");
        $drill_stmt->execute([$plan_id]);
        $drills = $drill_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'plan' => $plan,
            'drills' => $drills
        ]);
        exit();
        
    } catch (PDOException $e) {
        error_log("Get practice plan error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to load practice plan']);
        exit();
    }
}

// =========================================================
// DELETE PRACTICE PLAN
// =========================================================
if ($action === 'delete_plan' || $action === 'delete') {
    requirePermission($pdo, $user_id, $user_role, 'delete_practice_plans');
    
    $plan_id = intval($_POST['plan_id']);
    
    try {
        $pdo->prepare("DELETE FROM practice_plans WHERE id = ? AND created_by = ?")->execute([$plan_id, $user_id]);
        
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Practice plan deleted successfully']);
            exit();
        }
        
        header("Location: dashboard.php?page=practice_library&status=plan_deleted");
        exit();
    } catch (PDOException $e) {
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete practice plan']);
            exit();
        }
        
        header("Location: dashboard.php?page=practice_library&error=delete_failed");
        exit();
    }
}

// =========================================================
// GENERATE/REGENERATE SHARE TOKEN
// =========================================================
if ($action === 'generate_share_token') {
    requirePermission($pdo, $user_id, $user_role, 'share_practice_plans');
    
    $plan_id = intval($_POST['plan_id']);
    $share_token = generateShareToken();
    
    try {
        $stmt = $pdo->prepare("UPDATE practice_plans SET share_token = ? WHERE id = ? AND created_by = ?");
        $stmt->execute([$share_token, $plan_id, $user_id]);
        header("Location: dashboard.php?page=practice_plans&status=token_generated&plan_id=$plan_id");
        exit();
    } catch (PDOException $e) {
        header("Location: dashboard.php?page=practice_plans&error=token_failed");
        exit();
    }
}

// =========================================================
// REMOVE SHARE TOKEN
// =========================================================
if ($action === 'remove_share_token') {
    requirePermission($pdo, $user_id, $user_role, 'share_practice_plans');
    
    $plan_id = intval($_POST['plan_id']);
    
    try {
        $stmt = $pdo->prepare("UPDATE practice_plans SET share_token = NULL WHERE id = ? AND created_by = ?");
        $stmt->execute([$plan_id, $user_id]);
        header("Location: dashboard.php?page=practice_plans&status=token_removed");
        exit();
    } catch (PDOException $e) {
        header("Location: dashboard.php?page=practice_plans&error=token_failed");
        exit();
    }
}

// =========================================================
// FETCH IHS PRACTICE PLAN DATA (AJAX - returns JSON)
// =========================================================
if ($action === 'fetch_ihs_practice_plan') {
    requirePermission($pdo, $user_id, $user_role, 'create_practice_plans');
    
    header('Content-Type: application/json');
    
    $ihs_url = trim($_POST['ihs_url'] ?? '');
    
    if (empty($ihs_url)) {
        echo json_encode(['success' => false, 'message' => 'URL is required']);
        exit();
    }
    
    // Validate URL format
    if (!filter_var($ihs_url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
        exit();
    }
    
    // Validate that URL is from icehockeysystems.com
    $url_parts = parse_url($ihs_url);
    $host = strtolower($url_parts['host'] ?? '');
    
    $allowed_domains = ['icehockeysystems.com', 'www.icehockeysystems.com'];
    
    if (!in_array($host, $allowed_domains)) {
        echo json_encode(['success' => false, 'message' => 'Only URLs from icehockeysystems.com are supported']);
        exit();
    }
    
    try {
        // Fetch the page content using cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $ihs_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $html = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code !== 200 || empty($html)) {
            error_log("IHS Practice Plan Fetch Error: HTTP $http_code - $curl_error");
            echo json_encode(['success' => false, 'message' => 'Failed to fetch page content. Please try manual entry.']);
            exit();
        }
        
        // Parse the HTML to extract practice plan information
        $plan_data = parseIHSPracticePlanPage($html, $ihs_url);
        
        if (!$plan_data) {
            echo json_encode(['success' => false, 'message' => 'Could not parse practice plan data from the page. Please try manual entry.']);
            exit();
        }
        
        echo json_encode(['success' => true, 'plan' => $plan_data]);
        exit();
        
    } catch (Exception $e) {
        error_log("IHS Practice Plan Fetch Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while fetching the practice plan']);
        exit();
    }
}

// =========================================================
// IMPORT IHS PRACTICE PLAN
// =========================================================
if ($action === 'import_ihs_practice_plan') {
    requirePermission($pdo, $user_id, $user_role, 'create_practice_plans');
    
    $ihs_url = trim($_POST['ihs_url'] ?? '');
    $title = trim($_POST['plan_title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $age_group = trim($_POST['age_group'] ?? '');
    $focus_area = trim($_POST['focus_area'] ?? '');
    $drills_json = trim($_POST['drills_json'] ?? '[]');
    
    if (empty($title)) {
        header("Location: dashboard.php?page=practice_import&error=title_required");
        exit();
    }
    
    try {
        $drills = json_decode($drills_json, true) ?: [];
        
        $pdo->beginTransaction();
        
        // Build description with import source
        $full_description = $description;
        if (!empty($ihs_url)) {
            $full_description .= "\n\n---\nImported from IHS: " . $ihs_url;
        }
        
        // Parse duration to minutes
        $duration_minutes = 60;
        if (preg_match('/(\d+)/', $duration, $matches)) {
            $duration_minutes = intval($matches[1]);
        }
        
        // Create the practice plan
        $stmt = $pdo->prepare("
            INSERT INTO practice_plans (name, description, focus_area, age_group, duration_minutes, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$title, $full_description, $focus_area, $age_group, $duration_minutes, $user_id]);
        $plan_id = $pdo->lastInsertId();
        
        // Create each drill and add to the practice plan
        // First, check if drill with same name already exists in library
        $drill_order = 0;
        foreach ($drills as $drill) {
            $drill_title = trim($drill['title'] ?? '');
            if (empty($drill_title)) {
                continue;
            }
            
            // Check if a drill with this name already exists in the drill library
            // Prioritize drills owned by the same user, then fall back to any drill with the same title
            $existing_drill_stmt = $pdo->prepare("
                SELECT id FROM drills 
                WHERE title = ? 
                ORDER BY (created_by = ?) DESC, created_at DESC 
                LIMIT 1
            ");
            $existing_drill_stmt->execute([$drill_title, $user_id]);
            $existing_drill = $existing_drill_stmt->fetch();
            
            if ($existing_drill) {
                // Use the existing drill from the library
                $drill_id = $existing_drill['id'];
            } else {
                // Create a new drill since it doesn't exist in the library
                
                // Download and save the rink image if available
                $custom_image = '';
                $rink_image_url = trim($drill['rink_image'] ?? '');
                if (!empty($rink_image_url) && filter_var($rink_image_url, FILTER_VALIDATE_URL)) {
                    $custom_image = downloadAndSaveDrillImage($rink_image_url, $user_id);
                }
                
                // Determine category from drill title or use General
                $category_id = null;
                $category_keywords = [
                    'skating' => 'Skating',
                    'shooting' => 'Shooting',
                    'passing' => 'Passing',
                    'stickhandling' => 'Stickhandling',
                    'puck' => 'Puck Control',
                    'defensive' => 'Defensive',
                    'offensive' => 'Offensive',
                    'goalie' => 'Goalie',
                    'conditioning' => 'Conditioning',
                    'battle' => 'Battle Drills',
                    'warm' => 'Warm Up'
                ];
                
                $category_name = 'General';
                $lower_title = strtolower($drill_title);
                foreach ($category_keywords as $keyword => $cat_name) {
                    if (strpos($lower_title, $keyword) !== false) {
                        $category_name = $cat_name;
                        break;
                    }
                }
                
                // Look up or create category
                $cat_stmt = $pdo->prepare("SELECT id FROM drill_categories WHERE name = ?");
                $cat_stmt->execute([$category_name]);
                $cat = $cat_stmt->fetch();
                if ($cat) {
                    $category_id = $cat['id'];
                } else {
                    $cat_stmt = $pdo->prepare("INSERT INTO drill_categories (name) VALUES (?)");
                    $cat_stmt->execute([$category_name]);
                    $category_id = $pdo->lastInsertId();
                }
                
                // Get section data
                $drill_description = trim($drill['description'] ?? '');
                $drill_setup = trim($drill['setup'] ?? '');
                $drill_coaching_points = trim($drill['coaching_points'] ?? '');
                $drill_progression = trim($drill['progression'] ?? '');
                
                // Create the drill with sections in their own columns
                $drill_stmt = $pdo->prepare("
                    INSERT INTO drills (title, description, setup, coaching_points, progression, category_id, custom_image, ihs_source_url, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $drill_stmt->execute([
                    $drill_title,
                    $drill_description,
                    $drill_setup,
                    $drill_coaching_points,
                    $drill_progression,
                    $category_id,
                    $custom_image,
                    $ihs_url,
                    $user_id
                ]);
                $drill_id = $pdo->lastInsertId();
            }
            
            // Parse drill duration
            $drill_duration = null;
            if (!empty($drill['duration'])) {
                if (preg_match('/(\d+)/', $drill['duration'], $matches)) {
                    $drill_duration = intval($matches[1]);
                }
            }
            
            // Add drill to practice plan
            $plan_drill_stmt = $pdo->prepare("
                INSERT INTO practice_plan_drills (practice_plan_id, drill_id, drill_order, duration_minutes, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            $plan_drill_stmt->execute([
                $plan_id,
                $drill_id,
                $drill_order,
                $drill_duration,
                $drill['notes'] ?? ''
            ]);
            
            $drill_order++;
        }
        
        $pdo->commit();
        
        header("Location: dashboard.php?page=practice_library&status=plan_imported");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("IHS Practice Plan Import Error: " . $e->getMessage());
        header("Location: dashboard.php?page=practice_import&error=import_failed");
        exit();
    }
}

/**
 * Parse IHS practice plan page HTML to extract practice plan information
 */
function parseIHSPracticePlanPage($html, $url) {
    $plan = [
        'title' => '',
        'description' => '',
        'duration' => '',
        'age_group' => '',
        'focus_area' => '',
        'drills' => []
    ];
    
    // Suppress HTML parsing warnings
    libxml_use_internal_errors(true);
    
    $dom = new DOMDocument();
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);
    
    // Try to extract title from various possible locations
    $titleNodes = $xpath->query('//h1');
    if ($titleNodes->length > 0) {
        $plan['title'] = trim($titleNodes->item(0)->textContent);
    }
    
    // Try og:title as fallback
    if (empty($plan['title'])) {
        $ogTitle = $xpath->query('//meta[@property="og:title"]/@content');
        if ($ogTitle->length > 0) {
            $plan['title'] = trim($ogTitle->item(0)->textContent);
        }
    }
    
    // Extract meta description
    $metaDesc = $xpath->query('//meta[@name="description"]/@content');
    if ($metaDesc->length > 0) {
        $plan['description'] = trim($metaDesc->item(0)->textContent);
    }
    
    // Look for drill elements in the page
    // Common patterns: .drill, .drill-item, .practice-drill, article, .card
    $drillPatterns = [
        '//div[contains(@class, "drill")]',
        '//article[contains(@class, "drill")]',
        '//div[contains(@class, "practice-item")]',
        '//div[contains(@class, "plan-item")]'
    ];
    
    foreach ($drillPatterns as $pattern) {
        $drillNodes = $xpath->query($pattern);
        if ($drillNodes->length > 0) {
            for ($i = 0; $i < $drillNodes->length; $i++) {
                $drillNode = $drillNodes->item($i);
                
                $drill = extractDrillFromNode($drillNode, $xpath, $url);
                if ($drill && !empty($drill['title'])) {
                    $plan['drills'][] = $drill;
                }
            }
            break;
        }
    }
    
    // If no drills found with class patterns, try to find images with titles
    if (empty($plan['drills'])) {
        $images = $xpath->query('//img[contains(@src, "drill") or contains(@alt, "drill")]');
        $seenImageUrls = []; // Track seen image URLs to prevent duplicates
        $drillNumber = 1; // Counter for default drill names
        
        for ($i = 0; $i < min($images->length, 20); $i++) {
            $img = $images->item($i);
            $imgSrc = $img->getAttribute('src');
            $imgAlt = $img->getAttribute('alt');
            
            // Make absolute URL
            if (!empty($imgSrc) && strpos($imgSrc, 'http') !== 0) {
                $url_parts = parse_url($url);
                $base = $url_parts['scheme'] . '://' . $url_parts['host'];
                $imgSrc = $base . (strpos($imgSrc, '/') === 0 ? '' : '/') . $imgSrc;
            }
            
            // Skip duplicate images (same URL)
            if (isset($seenImageUrls[$imgSrc])) {
                continue;
            }
            $seenImageUrls[$imgSrc] = true;
            
            $plan['drills'][] = [
                'title' => !empty($imgAlt) ? $imgAlt : 'Drill ' . $drillNumber,
                'description' => '',
                'setup' => '',
                'coaching_points' => '',
                'progression' => '',
                'rink_image' => $imgSrc,
                'duration' => ''
            ];
            $drillNumber++;
        }
    }
    
    libxml_clear_errors();
    
    // Deduplicate drills by title, merging text fields from duplicates
    $plan['drills'] = deduplicateDrillsByTitle($plan['drills']);
    
    // Return plan if we have at least a title
    if (!empty($plan['title']) || !empty($plan['drills'])) {
        if (empty($plan['title'])) {
            $plan['title'] = 'Imported Practice Plan';
        }
        return $plan;
    }
    
    return null;
}

/**
 * Extract drill information from a DOM node
 * Extracts section data (setup, coaching_points, progression) similar to parseIHSDrillPage
 */
function extractDrillFromNode($node, $xpath, $baseUrl) {
    $drill = [
        'title' => '',
        'description' => '',
        'setup' => '',
        'coaching_points' => '',
        'progression' => '',
        'rink_image' => '',
        'duration' => ''
    ];
    
    // Find title in node
    $titles = $xpath->query('.//h1|.//h2|.//h3|.//h4|.//strong', $node);
    if ($titles->length > 0) {
        $drill['title'] = trim($titles->item(0)->textContent);
    }
    
    // Find image in node - prioritize IHS-specific images from files.icehockeysystems.com
    // IHS CDN base URL for drill images
    $ihsCdnBase = 'https://www.files.icehockeysystems.com';
    
    $images = $xpath->query('.//img', $node);
    if ($images->length > 0) {
        // First pass: look for IHS-specific images
        // Priority 1: Images hosted on files.icehockeysystems.com (the official IHS CDN)
        // Priority 2: Images with img-responsive class (common IHS drill image class)
        foreach ($images as $img) {
            $imgSrc = $img->getAttribute('src');
            $imgClass = $img->getAttribute('class') ?: '';
            // Priority 1: IHS CDN images (already contains the domain)
            if (!empty($imgSrc) && strpos($imgSrc, 'files.icehockeysystems.com') !== false) {
                // Ensure proper https:// prefix for protocol-relative URLs
                if (strpos($imgSrc, '//') === 0) {
                    $imgSrc = 'https:' . $imgSrc;
                }
                $drill['rink_image'] = $imgSrc;
                break;
            }
            // Priority 2: Relative paths starting with /files/drills/ (IHS CDN)
            if (!empty($imgSrc) && strpos($imgSrc, '/files/drills/') === 0) {
                $drill['rink_image'] = $ihsCdnBase . $imgSrc;
                break;
            }
            // Priority 3: Images with img-responsive class
            if (!empty($imgSrc) && !empty($imgClass) && strpos($imgClass, 'img-responsive') !== false) {
                if (strpos($imgSrc, '/files/drills/') === 0) {
                    $imgSrc = $ihsCdnBase . $imgSrc;
                } elseif (strpos($imgSrc, 'http') !== 0) {
                    if (strpos($imgSrc, '//') === 0) {
                        $imgSrc = 'https:' . $imgSrc;
                    } else {
                        $url_parts = parse_url($baseUrl);
                        $base = $url_parts['scheme'] . '://' . $url_parts['host'];
                        $imgSrc = $base . (strpos($imgSrc, '/') === 0 ? '' : '/') . $imgSrc;
                    }
                }
                $drill['rink_image'] = $imgSrc;
                break;
            }
        }
        // Fallback to first image if no IHS-specific image found
        if (empty($drill['rink_image'])) {
            $imgSrc = $images->item(0)->getAttribute('src');
            if (!empty($imgSrc)) {
                if (strpos($imgSrc, '/files/drills/') === 0) {
                    $imgSrc = $ihsCdnBase . $imgSrc;
                } elseif (strpos($imgSrc, 'http') !== 0) {
                    if (strpos($imgSrc, '//') === 0) {
                        $imgSrc = 'https:' . $imgSrc;
                    } else {
                        $url_parts = parse_url($baseUrl);
                        $base = $url_parts['scheme'] . '://' . $url_parts['host'];
                        $imgSrc = $base . (strpos($imgSrc, '/') === 0 ? '' : '/') . $imgSrc;
                    }
                }
                $drill['rink_image'] = $imgSrc;
            }
        }
    }
    
    // Extract content sections (Setup, Coaching Points, Progression) - similar to parseIHSDrillPage
    // These are often in divs with specific headers or classes
    // Note: Keywords are hardcoded and controlled - not from external input - so XPath injection is not a risk
    $sectionPatterns = [
        'setup' => ['setup', 'set-up', 'organization', 'drill setup'],
        'coaching_points' => ['coaching points', 'coaching-points', 'key points', 'teaching points', 'focus points'],
        'progression' => ['progression', 'progressions', 'variations', 'drill progression']
    ];
    
    // Track nodes that have been processed as part of sections to avoid duplication
    $processedNodePaths = [];
    
    foreach ($sectionPatterns as $field => $keywords) {
        foreach ($keywords as $keyword) {
            // Look for headers containing the keyword within this node
            $headers = $xpath->query('.//*[self::h2 or self::h3 or self::h4 or self::h5 or self::strong or self::b][contains(translate(., "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "' . $keyword . '")]', $node);
            if ($headers->length > 0) {
                $header = $headers->item(0);
                // Get the next sibling or parent's next content
                $nextSibling = $header->nextSibling;
                $content = '';
                while ($nextSibling) {
                    if ($nextSibling->nodeType === XML_ELEMENT_NODE) {
                        $tagName = strtolower($nextSibling->nodeName);
                        // Stop if we hit another header
                        if (in_array($tagName, ['h2', 'h3', 'h4', 'h5', 'strong', 'b'])) {
                            // Check if this header is also a section keyword - if so, stop
                            $headerText = strtolower($nextSibling->textContent);
                            $isNewSection = false;
                            foreach ($sectionPatterns as $checkKeywords) {
                                foreach ($checkKeywords as $checkKeyword) {
                                    if (strpos($headerText, $checkKeyword) !== false) {
                                        $isNewSection = true;
                                        break 2;
                                    }
                                }
                            }
                            if ($isNewSection) {
                                break;
                            }
                        }
                        $content .= trim($nextSibling->textContent) . "\n";
                        // Track this node as processed
                        $processedNodePaths[$nextSibling->getNodePath()] = true;
                    } elseif ($nextSibling->nodeType === XML_TEXT_NODE) {
                        $textContent = trim($nextSibling->textContent);
                        if (!empty($textContent)) {
                            $content .= $textContent . "\n";
                        }
                    }
                    $nextSibling = $nextSibling->nextSibling;
                }
                if (!empty($content)) {
                    $drill[$field] = trim($content);
                    break;
                }
            }
        }
    }
    
    // Find description/text in node - collect paragraphs that aren't part of section content
    $paragraphs = $xpath->query('.//p', $node);
    $descriptionParts = [];
    
    for ($i = 0; $i < $paragraphs->length; $i++) {
        $pNode = $paragraphs->item($i);
        $pText = trim($pNode->textContent);
        // Skip if this node was processed as part of a section
        if (!empty($pText) && !isset($processedNodePaths[$pNode->getNodePath()])) {
            $descriptionParts[] = $pText;
        }
    }
    
    // If we have distinct description parts, use them; otherwise combine all paragraphs
    if (!empty($descriptionParts)) {
        $drill['description'] = implode("\n", $descriptionParts);
    } elseif (empty($drill['setup']) && empty($drill['coaching_points']) && empty($drill['progression'])) {
        // If no sections were found, use all paragraph text as description
        $text = '';
        for ($i = 0; $i < $paragraphs->length; $i++) {
            $text .= trim($paragraphs->item($i)->textContent) . "\n";
        }
        $drill['description'] = trim($text);
    }
    
    return $drill;
}

/**
 * Deduplicate drills by title, merging text fields from duplicates
 * Each drill should have unique title with all information consolidated
 * Text fields (description, setup, coaching_points, progression) are merged if duplicate has non-empty values
 * 
 * @param array $drills Array of drill data
 * @return array Deduplicated array of drills
 */
function deduplicateDrillsByTitle($drills) {
    if (empty($drills) || !is_array($drills)) {
        return [];
    }
    
    $uniqueDrills = [];
    $titleIndex = []; // Maps normalized title to index in uniqueDrills
    
    foreach ($drills as $drill) {
        $title = trim($drill['title'] ?? '');
        if (empty($title)) {
            continue; // Skip drills without titles
        }
        
        // Normalize title for comparison (lowercase, trim whitespace)
        $normalizedTitle = strtolower(preg_replace('/\s+/', ' ', $title));
        
        if (isset($titleIndex[$normalizedTitle])) {
            // Drill with this title already exists - merge fields
            $existingIndex = $titleIndex[$normalizedTitle];
            $uniqueDrills[$existingIndex] = mergeDrillFields($uniqueDrills[$existingIndex], $drill);
        } else {
            // New unique drill
            $titleIndex[$normalizedTitle] = count($uniqueDrills);
            $uniqueDrills[] = $drill;
        }
    }
    
    return array_values($uniqueDrills);
}

/**
 * Merge two drill arrays, preserving non-empty values from both
 * The first drill (original) takes precedence for title and image,
 * but text fields are merged/appended if the new drill has unique content
 * 
 * @param array $original Original drill data
 * @param array $new New drill data to merge in
 * @return array Merged drill data
 */
function mergeDrillFields($original, $new) {
    $textFields = ['description', 'setup', 'coaching_points', 'progression'];
    
    foreach ($textFields as $field) {
        $origValue = trim($original[$field] ?? '');
        $newValue = trim($new[$field] ?? '');
        
        if (empty($origValue) && !empty($newValue)) {
            // Original is empty, use new value
            $original[$field] = $newValue;
        } elseif (!empty($origValue) && !empty($newValue) && $origValue !== $newValue) {
            // Both have values and they're different - check if content is unique
            // Avoid duplicating content that's already present (check both directions)
            $isNewSubstringOfOrig = (strpos($origValue, $newValue) !== false);
            $isOrigSubstringOfNew = (strpos($newValue, $origValue) !== false);
            
            if ($isOrigSubstringOfNew) {
                // Original is contained in new - use new (more complete) value
                $original[$field] = $newValue;
            } elseif (!$isNewSubstringOfOrig) {
                // Neither is a substring of the other - append new content
                $original[$field] = $origValue . "\n\n" . $newValue;
            }
            // If new is a substring of original, keep original (no change needed)
        }
        // If original has value and new is empty, keep original (no change needed)
    }
    
    // For rink_image: prefer the first non-empty value
    if (empty($original['rink_image']) && !empty($new['rink_image'])) {
        $original['rink_image'] = $new['rink_image'];
    }
    
    // For duration: prefer the first non-empty value
    if (empty($original['duration']) && !empty($new['duration'])) {
        $original['duration'] = $new['duration'];
    }
    
    return $original;
}

/**
 * Download an image from URL and save it locally
 */
function downloadAndSaveDrillImage($image_url, $user_id) {
    // Create upload directory if it doesn't exist
    $upload_dir = __DIR__ . '/uploads/drills/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0750, true);
    }
    
    // Download the image first to validate its content
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $image_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $image_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 || empty($image_data)) {
        return '';
    }
    
    // Validate it's actually an image and determine the extension from content
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($image_data);
    
    // Map MIME types to extensions
    $mime_to_ext = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    
    if (!isset($mime_to_ext[$mime])) {
        // Not a supported image type
        return '';
    }
    
    $extension = $mime_to_ext[$mime];
    
    // Generate a unique filename using only random bytes for security
    $filename = 'drill_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (file_put_contents($filepath, $image_data)) {
        return 'uploads/drills/' . $filename;
    }
    
    return '';
}

// =========================================================
// IMPORT PRACTICE PLANS FROM JSON FILE
// =========================================================
if ($action === 'import_json') {
    requirePermission($pdo, $user_id, $user_role, 'create_practice_plans');
    
    header('Content-Type: application/json');
    
    try {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed');
        }
        
        $file = $_FILES['import_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'json') {
            throw new Exception('Invalid file type. Only .json files are allowed.');
        }
        
        $content = file_get_contents($file['tmp_name']);
        if (empty($content)) {
            throw new Exception('File is empty');
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON format: ' . json_last_error_msg());
        }
        
        if (!isset($data['export_type']) || $data['export_type'] !== 'practice_plans') {
            throw new Exception('Invalid export file. Expected practice_plans export.');
        }
        
        $imported_plans = 0;
        $imported_drills = 0;
        $skipped = 0;
        $category_map = []; // old_id => new_id for drill categories
        
        $pdo->beginTransaction();
        
        // Import drill categories first if present
        if (!empty($data['drill_categories'])) {
            foreach ($data['drill_categories'] as $cat) {
                $check = $pdo->prepare("SELECT id FROM drill_categories WHERE name = ?");
                $check->execute([$cat['name']]);
                $existing = $check->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $category_map[$cat['id']] = $existing['id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO drill_categories (name, description, position_type) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $cat['name'],
                        $cat['description'] ?? null,
                        $cat['position_type'] ?? 'both'
                    ]);
                    $category_map[$cat['id']] = $pdo->lastInsertId();
                }
            }
        }
        
        // Import practice plans
        if (!empty($data['practice_plans'])) {
            $skip_duplicates = !empty($_POST['skip_duplicates']);
            
            foreach ($data['practice_plans'] as $plan) {
                $plan_name = $plan['name'] ?? $plan['title'] ?? '';
                
                if (empty($plan_name)) {
                    $skipped++;
                    continue;
                }
                
                // Check for duplicate by name if skip_duplicates is set
                if ($skip_duplicates) {
                    $check = $pdo->prepare("SELECT id FROM practice_plans WHERE name = ?");
                    $check->execute([$plan_name]);
                    if ($check->fetch()) {
                        $skipped++;
                        continue;
                    }
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO practice_plans (name, description, focus_area, age_group,
                        duration_minutes, difficulty_level, created_by, total_duration, title)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $plan_name,
                    $plan['description'] ?? null,
                    $plan['focus_area'] ?? null,
                    $plan['age_group'] ?? null,
                    $plan['duration_minutes'] ?? 60,
                    $plan['difficulty_level'] ?? 'intermediate',
                    $user_id,
                    $plan['total_duration'] ?? 60,
                    $plan['title'] ?? null
                ]);
                
                $new_plan_id = $pdo->lastInsertId();
                
                // Import associated drills
                if (!empty($plan['drills'])) {
                    foreach ($plan['drills'] as $drill_assoc) {
                        $drill_title = $drill_assoc['drill_title'] ?? '';
                        
                        if (empty($drill_title)) continue;
                        
                        // Find or create the drill
                        $drill_check = $pdo->prepare("SELECT id FROM drills WHERE title = ?");
                        $drill_check->execute([$drill_title]);
                        $existing_drill = $drill_check->fetch(PDO::FETCH_ASSOC);
                        
                        if ($existing_drill) {
                            $drill_id = $existing_drill['id'];
                        } else {
                            // Create the drill
                            $drill_stmt = $pdo->prepare("
                                INSERT INTO drills (title, description, setup, coaching_points, progression,
                                    created_by, diagram_data, video_url, ihs_source_url)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $drill_stmt->execute([
                                $drill_title,
                                $drill_assoc['drill_description'] ?? null,
                                $drill_assoc['setup'] ?? null,
                                $drill_assoc['coaching_points'] ?? null,
                                $drill_assoc['progression'] ?? null,
                                $user_id,
                                $drill_assoc['diagram_data'] ?? null,
                                $drill_assoc['video_url'] ?? null,
                                $drill_assoc['ihs_source_url'] ?? null
                            ]);
                            $drill_id = $pdo->lastInsertId();
                            $imported_drills++;
                        }
                        
                        // Create the plan-drill association
                        $assoc_stmt = $pdo->prepare("
                            INSERT INTO practice_plan_drills (practice_plan_id, drill_id, drill_order, duration_minutes, notes)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $assoc_stmt->execute([
                            $new_plan_id,
                            $drill_id,
                            $drill_assoc['drill_order'] ?? 0,
                            $drill_assoc['duration_minutes'] ?? null,
                            $drill_assoc['notes'] ?? null
                        ]);
                    }
                }
                
                $imported_plans++;
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Import complete: {$imported_plans} practice plans imported" . ($imported_drills > 0 ? ", {$imported_drills} new drills created" : '') . ($skipped > 0 ? ", {$skipped} skipped" : ''),
            'imported_plans' => $imported_plans,
            'imported_drills' => $imported_drills,
            'skipped' => $skipped
        ]);
        exit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Fallback
header("Location: dashboard.php?page=practice_plans");
exit();
