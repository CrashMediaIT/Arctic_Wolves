<?php
/**
 * Process Theme Settings
 * Handles comprehensive theme, branding, hero section, and training program settings
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/cloud_config.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

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
        $local_url = 'uploads/theme/' . $filename;
        
        // Always save to persistent storage (survives app re-deploys)
        // Also upload to Nextcloud if configured
        global $pdo;
        if ($pdo) {
            try {
                // Use absolute path for persistent storage and Nextcloud upload
                $absolute_path = $filepath;
                
                $nc_settings = getNextcloudSettings($pdo);
                if (!empty($nc_settings['nextcloud_url'])) {
                    if (!empty($nc_settings['nextcloud_password'])) {
                        $decrypted = decryptPassword($nc_settings['nextcloud_password']);
                        if (!empty($decrypted)) {
                            $nc_settings['nextcloud_password'] = $decrypted;
                        }
                    }
                    // uploadImageToNextcloud also saves to persistent storage
                    uploadImageToNextcloud($pdo, $nc_settings, $absolute_path, 'theme', $filename);
                } else {
                    // No Nextcloud — still save to persistent storage
                    saveToPersistentStorage($absolute_path, 'theme', $filename, $pdo);
                }
            } catch (\Throwable $e) {
                error_log("Theme image persistent/Nextcloud upload failed: " . $e->getMessage());
                // Try persistent storage as a fallback
                try { saveToPersistentStorage($filepath, 'theme', $filename, $pdo); } catch (\Throwable $ps) { error_log("Persistent storage fallback also failed: " . $ps->getMessage()); }
            }
        }
        
        return ['success' => true, 'url' => $local_url];
    }
    
    return ['success' => false, 'message' => 'Failed to save file'];
}

try {
    switch ($action) {
        case 'update_colors':
            $colors = [
                'primary_color' => $_POST['primary_color'] ?? '#6B46C1',
                'secondary_color' => $_POST['secondary_color'] ?? '#c0c0c0',
                'background_color' => $_POST['background_color'] ?? '#0A0A0F',
                'card_background_color' => $_POST['card_background_color'] ?? '#16161F',
                'text_color' => $_POST['text_color'] ?? '#ffffff',
                'text_muted_color' => $_POST['text_muted_color'] ?? '#A8A8B8',
                'border_color' => $_POST['border_color'] ?? '#2D2D3F',
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
            
            Auditor::log($pdo, $user_id, 'update', 'theme_settings', null, ['action' => 'Theme colors updated']);
            
            $redirect = 'dashboard.php?page=admin_theme_settings&tab=colors&success=1';
            if (!empty($invalid_colors)) {
                ErrorLogger::error("Invalid color values for: " . implode(', ', $invalid_colors));
                $redirect .= '&warning=' . urlencode('Some color values were invalid and were not saved.');
            }
            header('Location: ' . $redirect);
            exit;
            
        case 'update_branding':
        case 'update_branding_all':
            // Handle logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $result = handleFileUpload($_FILES['logo'], 'logo');
                if ($result['success']) {
                    updateThemeSetting($pdo, 'logo_url', $result['url']);
                    syncCenterIceLogoIfNeeded($pdo, $result['url']);
                }
            } elseif (!empty($_POST['logo_url_input'])) {
                updateThemeSetting($pdo, 'logo_url', $_POST['logo_url_input']);
                syncCenterIceLogoIfNeeded($pdo, $_POST['logo_url_input']);
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
            
            // Handle center ice logo (from consolidated form)
            if (isset($_FILES['center_ice_logo']) && $_FILES['center_ice_logo']['error'] === UPLOAD_ERR_OK) {
                $result = handleFileUpload($_FILES['center_ice_logo'], 'center_ice');
                if ($result['success']) {
                    updateThemeSetting($pdo, 'center_ice_logo_url', $result['url']);
                }
            } elseif (!empty($_POST['center_ice_logo_url_input'])) {
                updateThemeSetting($pdo, 'center_ice_logo_url', $_POST['center_ice_logo_url_input']);
            }
            
            // Handle business card backgrounds (from consolidated form)
            if (isset($_FILES['bc_front_bg']) && $_FILES['bc_front_bg']['error'] === UPLOAD_ERR_OK) {
                $front_bg_result = handleFileUpload($_FILES['bc_front_bg'], 'bc_front_bg');
                if ($front_bg_result['success']) {
                    updateThemeSetting($pdo, 'business_card_front_bg_url', $front_bg_result['url']);
                } else {
                    ErrorLogger::error("Front card background upload failed: " . ($front_bg_result['message'] ?? 'Unknown error'));
                }
            }
            if (isset($_FILES['bc_back_bg']) && $_FILES['bc_back_bg']['error'] === UPLOAD_ERR_OK) {
                $back_bg_result = handleFileUpload($_FILES['bc_back_bg'], 'bc_back_bg');
                if ($back_bg_result['success']) {
                    updateThemeSetting($pdo, 'business_card_back_bg_url', $back_bg_result['url']);
                } else {
                    ErrorLogger::error("Back card background upload failed: " . ($back_bg_result['message'] ?? 'Unknown error'));
                }
            }
            
            Auditor::log($pdo, $user_id, 'update', 'theme_settings', null, ['action' => 'Branding settings updated']);
            
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
            
        case 'update_business_card_backgrounds':
            // Handle front card background upload
            if (isset($_FILES['bc_front_bg']) && $_FILES['bc_front_bg']['error'] === UPLOAD_ERR_OK) {
                $front_bg_result = handleFileUpload($_FILES['bc_front_bg'], 'bc_front_bg');
                if ($front_bg_result['success']) {
                    updateThemeSetting($pdo, 'business_card_front_bg_url', $front_bg_result['url']);
                } else {
                    ErrorLogger::error("Front card background upload failed: " . ($front_bg_result['message'] ?? 'Unknown error'));
                }
            }
            
            // Handle back card background upload
            if (isset($_FILES['bc_back_bg']) && $_FILES['bc_back_bg']['error'] === UPLOAD_ERR_OK) {
                $back_bg_result = handleFileUpload($_FILES['bc_back_bg'], 'bc_back_bg');
                if ($back_bg_result['success']) {
                    updateThemeSetting($pdo, 'business_card_back_bg_url', $back_bg_result['url']);
                } else {
                    ErrorLogger::error("Back card background upload failed: " . ($back_bg_result['message'] ?? 'Unknown error'));
                }
            }
            
            Auditor::log($pdo, $user_id, 'update', 'theme_settings', null, ['action' => 'Business card backgrounds updated']);
            
            // Redirect back to the correct page
            $redirect_page = $_POST['redirect_page'] ?? 'admin_theme_settings';
            if ($redirect_page === 'system_tools') {
                header('Location: dashboard.php?page=system_tools&tab=theme&success=1');
            } else {
                header('Location: dashboard.php?page=admin_theme_settings&tab=branding&success=1');
            }
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
            
            Auditor::log($pdo, $user_id, $program_id > 0 ? 'update' : 'create', 'training_programs', $program_id > 0 ? $program_id : intval($pdo->lastInsertId()), ['action' => $program_id > 0 ? 'Training program updated' : 'Training program created']);
            
            header('Location: dashboard.php?page=admin_theme_settings&tab=programs&success=1');
            exit;
            
        case 'delete_program':
            $program_id = intval($_POST['program_id'] ?? 0);
            if ($program_id > 0) {
                $stmt = $pdo->prepare("DELETE FROM training_programs WHERE id = ?");
                $stmt->execute([$program_id]);
            }
            
            Auditor::log($pdo, $user_id, 'delete', 'training_programs', $program_id, ['action' => 'Training program deleted']);
            
            header('Location: dashboard.php?page=admin_theme_settings&tab=programs&success=1');
            exit;
            
        case 'reset_colors':
            // Reset to default colors
            $defaults = [
                'primary_color' => '#6B46C1',
                'secondary_color' => '#c0c0c0',
                'background_color' => '#0A0A0F',
                'card_background_color' => '#16161F',
                'text_color' => '#ffffff',
                'text_muted_color' => '#A8A8B8',
                'border_color' => '#2D2D3F',
                'sidebar_color' => '#020305',
                'button_hover_color' => '#a78bfa',
                'success_color' => '#22c55e',
                'error_color' => '#ef4444',
                'warning_color' => '#f59e0b'
            ];
            
            foreach ($defaults as $name => $value) {
                updateThemeSetting($pdo, $name, $value);
            }
            
            Auditor::log($pdo, $user_id, 'update', 'theme_settings', null, ['action' => 'Theme colors reset to defaults']);
            
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
            
            // Handle business card background uploads (unified into theme form)
            if (isset($_FILES['bc_front_bg']) && $_FILES['bc_front_bg']['error'] === UPLOAD_ERR_OK) {
                $front_bg_result = handleFileUpload($_FILES['bc_front_bg'], 'bc_front_bg');
                if ($front_bg_result['success']) {
                    updateThemeSetting($pdo, 'business_card_front_bg_url', $front_bg_result['url']);
                } else {
                    ErrorLogger::error("Front card background upload failed: " . ($front_bg_result['message'] ?? 'Unknown error'));
                }
            } elseif (isset($_FILES['bc_front_bg']) && $_FILES['bc_front_bg']['error'] !== UPLOAD_ERR_NO_FILE) {
                ErrorLogger::error("Front card background file error code: " . $_FILES['bc_front_bg']['error']);
            }
            if (isset($_FILES['bc_back_bg']) && $_FILES['bc_back_bg']['error'] === UPLOAD_ERR_OK) {
                $back_bg_result = handleFileUpload($_FILES['bc_back_bg'], 'bc_back_bg');
                if ($back_bg_result['success']) {
                    updateThemeSetting($pdo, 'business_card_back_bg_url', $back_bg_result['url']);
                } else {
                    ErrorLogger::error("Back card background upload failed: " . ($back_bg_result['message'] ?? 'Unknown error'));
                }
            }
            
            Auditor::log($pdo, $user_id, 'update', 'theme_settings', null, ['action' => 'Theme settings updated']);
            
            // Redirect back to system_tools theme tab
            header('Location: dashboard.php?page=system_tools&tab=theme&success=1');
            exit;
            
        case 'update_all_theme_settings':
            // Unified handler: save colors + branding + hero in one request
            
            // 1. Process colors
            $color_names = [
                'primary_color', 'secondary_color', 'background_color',
                'card_background_color', 'text_color', 'text_muted_color',
                'border_color', 'sidebar_color', 'button_hover_color',
                'success_color', 'error_color', 'warning_color'
            ];
            foreach ($color_names as $name) {
                $value = $_POST[$name] ?? null;
                if ($value !== null && preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
                    updateThemeSetting($pdo, $name, $value);
                }
            }
            
            // 2. Process logo/branding
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $result = handleFileUpload($_FILES['logo'], 'logo');
                if ($result['success']) {
                    updateThemeSetting($pdo, 'logo_url', $result['url']);
                    syncCenterIceLogoIfNeeded($pdo, $result['url']);
                }
            } elseif (!empty($_POST['logo_url_input'])) {
                updateThemeSetting($pdo, 'logo_url', $_POST['logo_url_input']);
                syncCenterIceLogoIfNeeded($pdo, $_POST['logo_url_input']);
            }
            
            if (isset($_POST['site_title'])) {
                updateThemeSetting($pdo, 'site_title', trim($_POST['site_title']));
            }
            if (isset($_POST['site_description'])) {
                updateThemeSetting($pdo, 'site_description', trim($_POST['site_description']));
            }
            
            // Center ice logo
            if (isset($_FILES['center_ice_logo']) && $_FILES['center_ice_logo']['error'] === UPLOAD_ERR_OK) {
                $result = handleFileUpload($_FILES['center_ice_logo'], 'center_ice');
                if ($result['success']) {
                    updateThemeSetting($pdo, 'center_ice_logo_url', $result['url']);
                }
            } elseif (!empty($_POST['center_ice_logo_url_input'])) {
                updateThemeSetting($pdo, 'center_ice_logo_url', $_POST['center_ice_logo_url_input']);
            }
            
            // Business card backgrounds
            $bc_upload_warnings = [];
            if (isset($_FILES['bc_front_bg']) && $_FILES['bc_front_bg']['error'] === UPLOAD_ERR_OK) {
                $front_bg_result = handleFileUpload($_FILES['bc_front_bg'], 'bc_front_bg');
                if ($front_bg_result['success']) {
                    updateThemeSetting($pdo, 'business_card_front_bg_url', $front_bg_result['url']);
                } else {
                    $msg = "Front card background upload failed: " . ($front_bg_result['message'] ?? 'Unknown error');
                    ErrorLogger::error($msg);
                    $bc_upload_warnings[] = $msg;
                }
            } elseif (isset($_FILES['bc_front_bg']) && $_FILES['bc_front_bg']['error'] !== UPLOAD_ERR_NO_FILE) {
                $err = $_FILES['bc_front_bg']['error'];
                $msg = "Front card background file error (code $err): " . match($err) {
                    UPLOAD_ERR_INI_SIZE => 'File exceeds server upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing server temp folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
                    UPLOAD_ERR_EXTENSION => 'Upload stopped by PHP extension',
                    default => 'Unknown upload error'
                };
                ErrorLogger::error($msg);
                $bc_upload_warnings[] = $msg;
            }
            if (isset($_FILES['bc_back_bg']) && $_FILES['bc_back_bg']['error'] === UPLOAD_ERR_OK) {
                $back_bg_result = handleFileUpload($_FILES['bc_back_bg'], 'bc_back_bg');
                if ($back_bg_result['success']) {
                    updateThemeSetting($pdo, 'business_card_back_bg_url', $back_bg_result['url']);
                } else {
                    $msg = "Back card background upload failed: " . ($back_bg_result['message'] ?? 'Unknown error');
                    ErrorLogger::error($msg);
                    $bc_upload_warnings[] = $msg;
                }
            } elseif (isset($_FILES['bc_back_bg']) && $_FILES['bc_back_bg']['error'] !== UPLOAD_ERR_NO_FILE) {
                $err = $_FILES['bc_back_bg']['error'];
                $msg = "Back card background file error (code $err)";
                ErrorLogger::error($msg);
                $bc_upload_warnings[] = $msg;
            }
            
            // 3. Process hero section
            if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
                $result = handleFileUpload($_FILES['hero_image'], 'hero');
                if ($result['success']) {
                    updateThemeSetting($pdo, 'hero_image_url', $result['url']);
                }
            } elseif (!empty($_POST['hero_image_url'])) {
                updateThemeSetting($pdo, 'hero_image_url', $_POST['hero_image_url']);
            }
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
            
            Auditor::log($pdo, $user_id, 'update', 'theme_settings', null, ['action' => 'All theme settings updated']);
            
            // Return JSON for AJAX requests, redirect otherwise
            if (!empty($_POST['_ajax'])) {
                header('Content-Type: application/json');
                $response = ['success' => true, 'message' => 'All theme settings saved!'];
                if (!empty($bc_upload_warnings)) {
                    $response['warnings'] = $bc_upload_warnings;
                    $response['message'] = 'Settings saved with warnings: ' . implode('; ', $bc_upload_warnings);
                }
                echo json_encode($response);
            } else {
                header('Location: dashboard.php?page=admin_theme_settings&tab=colors&success=1');
            }
            exit;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (\Throwable $e) {
    ErrorLogger::error("Theme settings error: " . $e->getMessage());
    // Return JSON for AJAX requests
    if (!empty($_POST['_ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    // Check if redirect_page was set to determine where to redirect on error
    $redirect_page = $_POST['redirect_page'] ?? 'admin_theme_settings';
    if ($redirect_page === 'system_tools') {
        header('Location: dashboard.php?page=system_tools&tab=theme&error=' . urlencode($e->getMessage()));
    } else {
        header('Location: dashboard.php?page=admin_theme_settings&error=' . urlencode($e->getMessage()));
    }
    exit;
}
