<?php
/**
 * Process Theme Settings
 * Handles comprehensive theme, branding, hero section, and training program settings
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';

// Set security headers
setSecurityHeaders();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Validate CSRF token
checkCsrfToken();

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

/**
 * Update theme setting in database
 */
function updateThemeSetting($pdo, $name, $value) {
    // Check if setting exists
    $stmt = $pdo->prepare("SELECT id FROM theme_settings WHERE setting_name = ?");
    $stmt->execute([$name]);
    
    if ($stmt->fetch()) {
        // Update existing
        $update = $pdo->prepare("UPDATE theme_settings SET setting_value = ?, updated_at = NOW() WHERE setting_name = ?");
        $update->execute([$value, $name]);
    } else {
        // Insert new
        $insert = $pdo->prepare("INSERT INTO theme_settings (setting_name, setting_value, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $insert->execute([$name, $value]);
    }
}

/**
 * Sync center_ice_logo_url with logo_url if not separately set
 */
function syncCenterIceLogoIfNeeded($pdo, $logoUrl) {
    $stmt = $pdo->prepare("SELECT setting_value FROM theme_settings WHERE setting_name = 'center_ice_logo_url'");
    $stmt->execute();
    $existingCenterLogo = $stmt->fetchColumn();
    if (empty($existingCenterLogo)) {
        updateThemeSetting($pdo, 'center_ice_logo_url', $logoUrl);
    }
}

/**
 * Handle file upload
 */
function handleFileUpload($file, $type = 'image') {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error: ' . $file['error']];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large. Maximum size is 5MB.'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP, SVG'];
    }
    
    // Create uploads directory if not exists
    $upload_dir = __DIR__ . '/uploads/theme/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $type . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'url' => 'uploads/theme/' . $filename];
    }
    
    return ['success' => false, 'message' => 'Failed to save file'];
}

try {
    switch ($action) {
        case 'update_colors':
            $colors = [
                'primary_color' => $_POST['primary_color'] ?? '#7000a4',
                'secondary_color' => $_POST['secondary_color'] ?? '#c0c0c0',
                'background_color' => $_POST['background_color'] ?? '#06080b',
                'card_background_color' => $_POST['card_background_color'] ?? '#0d1117',
                'text_color' => $_POST['text_color'] ?? '#ffffff',
                'text_muted_color' => $_POST['text_muted_color'] ?? '#94a3b8',
                'border_color' => $_POST['border_color'] ?? '#1e293b',
                'sidebar_color' => $_POST['sidebar_color'] ?? '#020305',
                'button_hover_color' => $_POST['button_hover_color'] ?? '#a78bfa',
                'success_color' => $_POST['success_color'] ?? '#22c55e',
                'error_color' => $_POST['error_color'] ?? '#ef4444',
                'warning_color' => $_POST['warning_color'] ?? '#f59e0b'
            ];
            
            $invalid_colors = [];
            foreach ($colors as $name => $value) {
                // Validate hex color
                if (preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
                    updateThemeSetting($pdo, $name, $value);
                } else {
                    $invalid_colors[] = $name;
                }
            }
            
            $redirect = 'dashboard.php?page=admin_theme_settings&tab=colors&success=1';
            if (!empty($invalid_colors)) {
                error_log("Invalid color values for: " . implode(', ', $invalid_colors));
                $redirect .= '&warning=' . urlencode('Some color values were invalid and were not saved.');
            }
            header('Location: ' . $redirect);
            exit;
            
        case 'update_branding':
            // Handle logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $result = handleFileUpload($_FILES['logo'], 'logo');
                if ($result['success']) {
                    updateThemeSetting($pdo, 'logo_url', $result['url']);
                    syncCenterIceLogoIfNeeded($pdo, $result['url']);
                }
            } elseif (!empty($_POST['logo_url'])) {
                updateThemeSetting($pdo, 'logo_url', $_POST['logo_url']);
                syncCenterIceLogoIfNeeded($pdo, $_POST['logo_url']);
            }
            
            // Handle favicon upload
            if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                $result = handleFileUpload($_FILES['favicon'], 'favicon');
                if ($result['success']) {
                    updateThemeSetting($pdo, 'favicon_url', $result['url']);
                }
            } elseif (!empty($_POST['favicon_url'])) {
                updateThemeSetting($pdo, 'favicon_url', $_POST['favicon_url']);
            }
            
            // Update text settings
            if (isset($_POST['site_title'])) {
                updateThemeSetting($pdo, 'site_title', trim($_POST['site_title']));
            }
            if (isset($_POST['site_description'])) {
                updateThemeSetting($pdo, 'site_description', trim($_POST['site_description']));
            }
            
            header('Location: dashboard.php?page=admin_theme_settings&tab=branding&success=1');
            exit;
            
        case 'update_center_ice_logo':
            // Handle center ice logo upload for drill designer
            if (isset($_FILES['center_ice_logo']) && $_FILES['center_ice_logo']['error'] === UPLOAD_ERR_OK) {
                $result = handleFileUpload($_FILES['center_ice_logo'], 'center_ice');
                if ($result['success']) {
                    updateThemeSetting($pdo, 'center_ice_logo_url', $result['url']);
                }
            } elseif (!empty($_POST['center_ice_logo_url_input'])) {
                updateThemeSetting($pdo, 'center_ice_logo_url', $_POST['center_ice_logo_url_input']);
            }
            
            header('Location: dashboard.php?page=admin_theme_settings&tab=branding&success=1');
            exit;
            
        case 'remove_center_ice_logo':
            // Remove center ice logo
            updateThemeSetting($pdo, 'center_ice_logo_url', '');
            
            header('Location: dashboard.php?page=admin_theme_settings&tab=branding&success=1');
            exit;
            
        case 'update_hero':
            // Handle hero image upload
            if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
                $result = handleFileUpload($_FILES['hero_image'], 'hero');
                if ($result['success']) {
                    updateThemeSetting($pdo, 'hero_image_url', $result['url']);
                }
            } elseif (!empty($_POST['hero_image_url'])) {
                updateThemeSetting($pdo, 'hero_image_url', $_POST['hero_image_url']);
            }
            
            // Update hero text
            if (isset($_POST['hero_title'])) {
                updateThemeSetting($pdo, 'hero_title', trim($_POST['hero_title']));
            }
            if (isset($_POST['hero_subtitle'])) {
                updateThemeSetting($pdo, 'hero_subtitle', trim($_POST['hero_subtitle']));
            }
            if (isset($_POST['hero_cta_text'])) {
                updateThemeSetting($pdo, 'hero_cta_text', trim($_POST['hero_cta_text']));
            }
            if (isset($_POST['hero_cta_url'])) {
                updateThemeSetting($pdo, 'hero_cta_url', trim($_POST['hero_cta_url']));
            }
            
            header('Location: dashboard.php?page=admin_theme_settings&tab=hero&success=1');
            exit;
            
        case 'add_program':
        case 'update_program':
            $program_id = isset($_POST['program_id']) ? intval($_POST['program_id']) : 0;
            $title = trim($_POST['program_title'] ?? '');
            $description = trim($_POST['program_description'] ?? '');
            $price = floatval($_POST['program_price'] ?? 0);
            $features = trim($_POST['program_features'] ?? '');
            $display_order = intval($_POST['display_order'] ?? 0);
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($title)) {
                throw new Exception('Program title is required');
            }
            
            // Handle program image upload
            $image_url = $_POST['program_image_url'] ?? '';
            if (isset($_FILES['program_image']) && $_FILES['program_image']['error'] === UPLOAD_ERR_OK) {
                $result = handleFileUpload($_FILES['program_image'], 'program');
                if ($result['success']) {
                    $image_url = $result['url'];
                }
            }
            
            if ($program_id > 0) {
                // Update existing program
                $stmt = $pdo->prepare("
                    UPDATE training_programs 
                    SET title = ?, description = ?, price = ?, features = ?, 
                        image_url = ?, display_order = ?, is_featured = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $price, $features, $image_url, $display_order, $is_featured, $is_active, $program_id]);
            } else {
                // Insert new program
                $stmt = $pdo->prepare("
                    INSERT INTO training_programs 
                    (title, description, price, features, image_url, display_order, is_featured, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$title, $description, $price, $features, $image_url, $display_order, $is_featured, $is_active]);
            }
            
            header('Location: dashboard.php?page=admin_theme_settings&tab=programs&success=1');
            exit;
            
        case 'delete_program':
            $program_id = intval($_POST['program_id'] ?? 0);
            if ($program_id > 0) {
                $stmt = $pdo->prepare("DELETE FROM training_programs WHERE id = ?");
                $stmt->execute([$program_id]);
            }
            
            header('Location: dashboard.php?page=admin_theme_settings&tab=programs&success=1');
            exit;
            
        case 'reset_colors':
            // Reset to default colors
            $defaults = [
                'primary_color' => '#7000a4',
                'secondary_color' => '#c0c0c0',
                'background_color' => '#06080b',
                'card_background_color' => '#0d1117',
                'text_color' => '#ffffff',
                'text_muted_color' => '#94a3b8',
                'border_color' => '#1e293b',
                'sidebar_color' => '#020305',
                'button_hover_color' => '#a78bfa',
                'success_color' => '#22c55e',
                'error_color' => '#ef4444',
                'warning_color' => '#f59e0b'
            ];
            
            foreach ($defaults as $name => $value) {
                updateThemeSetting($pdo, $name, $value);
            }
            
            header('Location: dashboard.php?page=admin_theme_settings&tab=colors&success=1&reset=1');
            exit;
            
        case 'update_theme':
        case 'save_theme':
            // Handle theme update from system_tools theme tab
            // Process colors - check for both 'background_color' and 'bg_color' keys
            $colors = [
                'primary_color' => $_POST['primary_color'] ?? null,
                'secondary_color' => $_POST['secondary_color'] ?? null,
                'background_color' => $_POST['background_color'] ?? $_POST['bg_color'] ?? null
            ];
            
            foreach ($colors as $name => $value) {
                if ($value !== null && preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
                    updateThemeSetting($pdo, $name, $value);
                }
            }
            
            // Handle logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $result = handleFileUpload($_FILES['logo'], 'logo');
                if ($result['success']) {
                    updateThemeSetting($pdo, 'logo_url', $result['url']);
                    updateThemeSetting($pdo, 'logo_method', 'upload');
                    syncCenterIceLogoIfNeeded($pdo, $result['url']);
                }
            } elseif (!empty($_POST['logo_url'])) {
                updateThemeSetting($pdo, 'logo_url', $_POST['logo_url']);
                updateThemeSetting($pdo, 'logo_method', 'url');
                syncCenterIceLogoIfNeeded($pdo, $_POST['logo_url']);
            }
            
            // Save logo method preference
            if (isset($_POST['logo_method'])) {
                updateThemeSetting($pdo, 'logo_method', $_POST['logo_method']);
            }
            
            // Save favicon preference
            if (isset($_POST['use_logo_as_favicon'])) {
                updateThemeSetting($pdo, 'use_logo_as_favicon', '1');
            } else {
                updateThemeSetting($pdo, 'use_logo_as_favicon', '0');
            }
            
            // Handle center ice logo upload/URL
            $center_ice_upload_attempted = isset($_FILES['center_ice_logo']) && $_FILES['center_ice_logo']['error'] !== UPLOAD_ERR_NO_FILE;
            if ($center_ice_upload_attempted && $_FILES['center_ice_logo']['error'] === UPLOAD_ERR_OK) {
                $result = handleFileUpload($_FILES['center_ice_logo'], 'center_ice');
                if ($result['success']) {
                    updateThemeSetting($pdo, 'center_ice_logo_url', $result['url']);
                    updateThemeSetting($pdo, 'center_ice_logo_method', 'upload');
                }
                // If upload failed, don't fall through to URL - let user know via no change
            } elseif (!$center_ice_upload_attempted && !empty($_POST['center_ice_logo_url_input'])) {
                // Only use URL if no file upload was attempted
                updateThemeSetting($pdo, 'center_ice_logo_url', $_POST['center_ice_logo_url_input']);
                updateThemeSetting($pdo, 'center_ice_logo_method', 'url');
            }
            
            // Save center ice logo method preference
            if (isset($_POST['center_ice_logo_method'])) {
                updateThemeSetting($pdo, 'center_ice_logo_method', $_POST['center_ice_logo_method']);
            }
            
            // Redirect back to system_tools theme tab
            header('Location: dashboard.php?page=system_tools&tab=theme&success=1');
            exit;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log("Theme settings error: " . $e->getMessage());
    // Check if redirect_page was set to determine where to redirect on error
    $redirect_page = $_POST['redirect_page'] ?? 'admin_theme_settings';
    if ($redirect_page === 'system_tools') {
        header('Location: dashboard.php?page=system_tools&tab=theme&error=' . urlencode($e->getMessage()));
    } else {
        header('Location: dashboard.php?page=admin_theme_settings&error=' . urlencode($e->getMessage()));
    }
    exit;
}
