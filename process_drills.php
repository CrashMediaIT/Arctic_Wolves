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
// IMPORT FROM URL (Ice Hockey Systems URL) - Legacy
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
        'www.icehockeysystems.com'
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

// =========================================================
// FETCH IHS DRILL DATA (AJAX - returns JSON)
// =========================================================
if ($action === 'fetch_ihs_drill') {
    requirePermission($pdo, $user_id, $user_role, 'create_drills');
    
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
            error_log("IHS Fetch Error: HTTP $http_code - $curl_error");
            echo json_encode(['success' => false, 'message' => 'Failed to fetch page content']);
            exit();
        }
        
        // Parse the HTML to extract drill information
        $drill_data = parseIHSDrillPage($html, $ihs_url);
        
        if (!$drill_data) {
            echo json_encode(['success' => false, 'message' => 'Could not parse drill data from the page']);
            exit();
        }
        
        echo json_encode(['success' => true, 'drill' => $drill_data]);
        exit();
        
    } catch (Exception $e) {
        error_log("IHS Fetch Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while fetching the drill']);
        exit();
    }
}

// =========================================================
// IMPORT IHS DRILL FROM FETCHED DATA
// =========================================================
if ($action === 'import_ihs_url') {
    requirePermission($pdo, $user_id, $user_role, 'create_drills');
    
    $ihs_url = trim($_POST['ihs_url'] ?? '');
    $title = trim($_POST['drill_title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_name = trim($_POST['category'] ?? '');
    $setup = trim($_POST['setup'] ?? '');
    $coaching_points = trim($_POST['coaching_points'] ?? '');
    $progression = trim($_POST['progression'] ?? '');
    $rink_image_url = trim($_POST['rink_image_url'] ?? '');
    
    if (empty($title)) {
        header("Location: dashboard.php?page=import_drill&error=title_required");
        exit();
    }
    
    // Check if this URL has already been imported
    if (!empty($ihs_url)) {
        $stmt = $pdo->prepare("SELECT id FROM drills WHERE ihs_source_url = ?");
        $stmt->execute([$ihs_url]);
        if ($stmt->fetch()) {
            header("Location: dashboard.php?page=import_drill&error=already_imported");
            exit();
        }
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
                $stmt = $pdo->prepare("INSERT INTO drill_categories (name) VALUES (?)");
                $stmt->execute([$category_name]);
                $category_id = $pdo->lastInsertId();
            }
        }
        
        // Download and save the rink image if available
        $custom_image = '';
        if (!empty($rink_image_url) && filter_var($rink_image_url, FILTER_VALIDATE_URL)) {
            $custom_image = downloadAndSaveImage($rink_image_url, $user_id);
        }
        
        // Insert the drill with sections in their own columns
        $stmt = $pdo->prepare("
            INSERT INTO drills (title, description, setup, coaching_points, progression, category_id, custom_image, ihs_source_url, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$title, $description, $setup, $coaching_points, $progression, $category_id, $custom_image, $ihs_url, $user_id]);
        
        header("Location: dashboard.php?page=drill_library&status=drill_imported");
        exit();
        
    } catch (PDOException $e) {
        error_log("IHS Import Error: " . $e->getMessage());
        header("Location: dashboard.php?page=import_drill&error=import_failed");
        exit();
    }
}

/**
 * Parse IHS drill page HTML to extract drill information
 */
function parseIHSDrillPage($html, $url) {
    $drill = [
        'title' => '',
        'description' => '',
        'setup' => '',
        'coaching_points' => '',
        'progression' => '',
        'rink_image' => '',
        'category' => ''
    ];
    
    // Suppress HTML parsing warnings
    libxml_use_internal_errors(true);
    
    $dom = new DOMDocument();
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);
    
    // Try to extract title from various possible locations
    // Common patterns: h1, .drill-title, .drill-name, meta og:title
    $titleNodes = $xpath->query('//h1');
    if ($titleNodes->length > 0) {
        $drill['title'] = trim($titleNodes->item(0)->textContent);
    }
    
    // Try og:title as fallback
    if (empty($drill['title'])) {
        $ogTitle = $xpath->query('//meta[@property="og:title"]/@content');
        if ($ogTitle->length > 0) {
            $drill['title'] = trim($ogTitle->item(0)->textContent);
        }
    }
    
    // Extract meta description
    $metaDesc = $xpath->query('//meta[@name="description"]/@content');
    if ($metaDesc->length > 0) {
        $drill['description'] = trim($metaDesc->item(0)->textContent);
    }
    
    // Try og:description as fallback
    if (empty($drill['description'])) {
        $ogDesc = $xpath->query('//meta[@property="og:description"]/@content');
        if ($ogDesc->length > 0) {
            $drill['description'] = trim($ogDesc->item(0)->textContent);
        }
    }
    
    // Try to find rink diagram image
    // First, look for IHS-specific drill images from files.icehockeysystems.com with img-responsive class
    $ihsImages = $xpath->query('//img[contains(@class, "img-responsive")]/@src');
    if ($ihsImages->length > 0) {
        foreach ($ihsImages as $imgNode) {
            $imageSrc = trim($imgNode->textContent);
            // Prioritize images from files.icehockeysystems.com/files/drills/
            if (strpos($imageSrc, 'files.icehockeysystems.com') !== false) {
                $drill['rink_image'] = $imageSrc;
                break;
            }
        }
    }
    
    // If no IHS-specific image found, fall back to other patterns
    if (empty($drill['rink_image'])) {
        $imagePatterns = [
            '//img[contains(@class, "rink")]/@src',
            '//img[contains(@class, "diagram")]/@src',
            '//img[contains(@class, "drill")]/@src',
            '//img[contains(@alt, "rink")]/@src',
            '//img[contains(@alt, "drill")]/@src',
            '//img[contains(@alt, "diagram")]/@src',
            '//div[contains(@class, "drill")]//img/@src',
            '//article//img/@src',
            '//meta[@property="og:image"]/@content'
        ];
        
        foreach ($imagePatterns as $pattern) {
            $images = $xpath->query($pattern);
            if ($images->length > 0) {
                $imageSrc = trim($images->item(0)->textContent);
                // Make sure it's an absolute URL
                if (!empty($imageSrc)) {
                    if (strpos($imageSrc, 'http') !== 0) {
                        $url_parts = parse_url($url);
                        $base = $url_parts['scheme'] . '://' . $url_parts['host'];
                        $imageSrc = $base . (strpos($imageSrc, '/') === 0 ? '' : '/') . $imageSrc;
                    }
                    $drill['rink_image'] = $imageSrc;
                    break;
                }
            }
        }
    }
    
    // Extract content sections (Setup, Coaching Points, Progression)
    // These are often in divs with specific headers or classes
    $sectionPatterns = [
        'setup' => ['setup', 'set-up', 'organization', 'drill setup'],
        'coaching_points' => ['coaching points', 'coaching-points', 'key points', 'teaching points', 'focus points'],
        'progression' => ['progression', 'progressions', 'variations', 'drill progression']
    ];
    
    foreach ($sectionPatterns as $field => $keywords) {
        foreach ($keywords as $keyword) {
            // Look for headers containing the keyword
            $headers = $xpath->query('//*[self::h2 or self::h3 or self::h4 or self::strong or self::b][contains(translate(., "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "' . $keyword . '")]');
            if ($headers->length > 0) {
                $header = $headers->item(0);
                // Get the next sibling or parent's next content
                $nextSibling = $header->nextSibling;
                $content = '';
                while ($nextSibling) {
                    if ($nextSibling->nodeType === XML_ELEMENT_NODE) {
                        $tagName = strtolower($nextSibling->nodeName);
                        // Stop if we hit another header
                        if (in_array($tagName, ['h2', 'h3', 'h4'])) {
                            break;
                        }
                        $content .= trim($nextSibling->textContent) . "\n";
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
    
    // Try to determine category from URL or page content
    $category_keywords = [
        'skating' => 'Skating',
        'shooting' => 'Shooting',
        'passing' => 'Passing',
        'stickhandling' => 'Stickhandling',
        'puck-control' => 'Puck Control',
        'defensive' => 'Defensive',
        'offensive' => 'Offensive',
        'goalie' => 'Goalie',
        'conditioning' => 'Conditioning',
        'team' => 'Team Play',
        'power-play' => 'Power Play',
        'penalty-kill' => 'Penalty Kill',
        'warm-up' => 'Warm Up',
        'battle' => 'Battle Drills'
    ];
    
    $lower_url = strtolower($url);
    $lower_title = strtolower($drill['title']);
    
    foreach ($category_keywords as $keyword => $cat_name) {
        if (strpos($lower_url, $keyword) !== false || strpos($lower_title, str_replace('-', ' ', $keyword)) !== false) {
            $drill['category'] = $cat_name;
            break;
        }
    }
    
    libxml_clear_errors();
    
    // Only return if we have at least a title
    if (!empty($drill['title'])) {
        return $drill;
    }
    
    return null;
}

/**
 * Download an image from URL and save it locally
 */
function downloadAndSaveImage($image_url, $user_id) {
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

// Fallback
header("Location: dashboard.php?page=drills");
exit();
