<?php
/**
 * Image Helper - Handles persistent image resolution
 * 
 * When images are stored both locally and in Nextcloud, this helper
 * ensures images are available locally by restoring from persistent
 * local storage first, then falling back to Nextcloud when needed.
 */

require_once __DIR__ . '/../cloud_config.php';

/**
 * Validate that a file path is safe (within uploads directory, no traversal)
 * 
 * @param string $path The path to validate
 * @return bool True if path is safe
 */
function isValidImagePath($path) {
    if (empty($path)) return false;
    // Must start with uploads/ and not contain path traversal
    if (strpos($path, 'uploads/') !== 0) return false;
    if (strpos($path, '..') !== false) return false;
    // Must have a valid image/video extension
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi', 'webm'];
    return in_array($ext, $allowed);
}

/**
 * Try to restore a file from persistent local storage based on known subfolder and filename.
 * This is a quick local check that doesn't require Nextcloud credentials.
 * 
 * @param string $local_path The local path to restore to
 * @param string $subfolder The subfolder (e.g., 'profiles', 'evaluations/123')
 * @param string|null $filename Override filename, otherwise uses basename of local_path
 * @return bool True if restored successfully
 */
function tryRestoreFromPersistent($local_path, $subfolder, $filename = null) {
    if (empty($subfolder)) return false;
    if ($filename === null) {
        $filename = basename($local_path);
    }
    if (empty($filename)) return false;
    
    return restoreFromPersistentStorage($subfolder, $filename, $local_path);
}

/**
 * Resolve a profile image path, restoring from persistent storage or Nextcloud if needed.
 * Call this before displaying a profile image to ensure the local file exists.
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id The user ID
 * @param string $local_path The local file path from the database
 * @return string|null The resolved local path, or null if unavailable
 */
function resolveProfileImage($pdo, $user_id, $local_path) {
    // Validate path to prevent directory traversal
    if (!empty($local_path) && !isValidImagePath($local_path)) {
        return null;
    }
    
    // If local file exists, nothing to do
    if (!empty($local_path) && file_exists($local_path)) {
        return $local_path;
    }
    
    // Try persistent local storage first (fast, no network)
    if (!empty($local_path)) {
        $filename = basename($local_path);
        if (tryRestoreFromPersistent($local_path, 'profiles', $filename)) {
            return $local_path;
        }
    }
    
    // Try to restore from Nextcloud
    try {
        $stmt = $pdo->prepare("SELECT nextcloud_image_path FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $nextcloud_path = $stmt->fetchColumn();
        
        if (empty($nextcloud_path)) {
            return null;
        }
        
        $nc_settings = getNextcloudSettings($pdo);
        if (empty($nc_settings['nextcloud_url'])) {
            return null;
        }
        
        // Decrypt password
        if (!empty($nc_settings['nextcloud_password'])) {
            $decrypted = decryptPassword($nc_settings['nextcloud_password']);
            if (!empty($decrypted)) {
                $nc_settings['nextcloud_password'] = $decrypted;
            }
        }
        
        // Determine local path to restore to
        if (empty($local_path)) {
            $ext = pathinfo($nextcloud_path, PATHINFO_EXTENSION) ?: 'jpg';
            $local_path = "uploads/avatar_" . intval($user_id) . "_restored." . $ext;
        }
        
        // Final safety check on restore path
        if (!isValidImagePath($local_path)) {
            return null;
        }
        
        $restored = restoreImageFromNextcloud($pdo, $nc_settings, $nextcloud_path, $local_path);
        if ($restored) {
            // Update the local path in database
            $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute([$local_path, $user_id]);
            return $local_path;
        }
    } catch (Exception $e) {
        error_log("Failed to restore profile image from Nextcloud for user $user_id: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Resolve an evaluation media file path, restoring from persistent storage or Nextcloud if needed.
 * 
 * @param PDO $pdo Database connection
 * @param int $media_id The evaluation_media record ID
 * @param string $local_path The local file path from the database
 * @return string|null The resolved local path, or null if unavailable
 */
function resolveEvaluationMedia($pdo, $media_id, $local_path) {
    // Validate path to prevent directory traversal
    if (!empty($local_path) && !isValidImagePath($local_path)) {
        return null;
    }
    
    // If local file exists, nothing to do
    if (!empty($local_path) && file_exists($local_path)) {
        return $local_path;
    }
    
    // Try persistent local storage first (fast, no network)
    if (!empty($local_path)) {
        // Extract subfolder from path like "uploads/evaluations/123/file.jpg"
        $relative = (strpos($local_path, 'uploads/') === 0) ? substr($local_path, strlen('uploads/')) : $local_path;
        $subfolder = dirname($relative);
        $filename = basename($local_path);
        if (!empty($subfolder) && $subfolder !== '.' && tryRestoreFromPersistent($local_path, $subfolder, $filename)) {
            return $local_path;
        }
    }
    
    // Try to restore from Nextcloud
    try {
        $stmt = $pdo->prepare("SELECT nextcloud_path FROM evaluation_media WHERE id = ?");
        $stmt->execute([$media_id]);
        $nextcloud_path = $stmt->fetchColumn();
        
        if (empty($nextcloud_path)) {
            return null;
        }
        
        $nc_settings = getNextcloudSettings($pdo);
        if (empty($nc_settings['nextcloud_url'])) {
            return null;
        }
        
        // Decrypt password
        if (!empty($nc_settings['nextcloud_password'])) {
            $decrypted = decryptPassword($nc_settings['nextcloud_password']);
            if (!empty($decrypted)) {
                $nc_settings['nextcloud_password'] = $decrypted;
            }
        }
        
        // Determine local path
        if (empty($local_path)) {
            $ext = pathinfo($nextcloud_path, PATHINFO_EXTENSION) ?: 'jpg';
            $local_path = "uploads/evaluations/restored_" . intval($media_id) . "." . $ext;
        }
        
        // Final safety check on restore path
        if (!isValidImagePath($local_path)) {
            return null;
        }
        
        $restored = restoreImageFromNextcloud($pdo, $nc_settings, $nextcloud_path, $local_path);
        if ($restored) {
            // Update the local path in database
            $pdo->prepare("UPDATE evaluation_media SET media_url = ? WHERE id = ?")->execute([$local_path, $media_id]);
            return $local_path;
        }
    } catch (Exception $e) {
        error_log("Failed to restore evaluation media from Nextcloud for media $media_id: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Resolve a drill image path, restoring from persistent storage or Nextcloud if needed.
 * Call this before displaying a drill image to ensure the local file exists.
 * 
 * @param PDO $pdo Database connection
 * @param int $drill_id The drill ID
 * @param string $local_path The local file path (custom_image) from the database
 * @return string|null The resolved local path, or null if unavailable
 */
function resolveDrillImage($pdo, $drill_id, $local_path) {
    // Validate path to prevent directory traversal
    if (!empty($local_path) && !isValidImagePath($local_path)) {
        return null;
    }
    
    // If local file exists, nothing to do
    if (!empty($local_path) && file_exists($local_path)) {
        return $local_path;
    }
    
    // Try persistent local storage first (fast, no network)
    if (!empty($local_path)) {
        $filename = basename($local_path);
        if (tryRestoreFromPersistent($local_path, 'drills', $filename)) {
            return $local_path;
        }
    }
    
    // Try to restore from Nextcloud
    try {
        $stmt = $pdo->prepare("SELECT nextcloud_image_path FROM drills WHERE id = ?");
        $stmt->execute([$drill_id]);
        $nextcloud_path = $stmt->fetchColumn();
        
        if (empty($nextcloud_path)) {
            return null;
        }
        
        $nc_settings = getNextcloudSettings($pdo);
        if (empty($nc_settings['nextcloud_url'])) {
            return null;
        }
        
        // Decrypt password
        if (!empty($nc_settings['nextcloud_password'])) {
            $decrypted = decryptPassword($nc_settings['nextcloud_password']);
            if (!empty($decrypted)) {
                $nc_settings['nextcloud_password'] = $decrypted;
            }
        }
        
        // Determine local path to restore to
        if (empty($local_path)) {
            $ext = pathinfo($nextcloud_path, PATHINFO_EXTENSION) ?: 'jpg';
            $local_path = "uploads/drills/drill_" . intval($drill_id) . "_restored." . $ext;
        }
        
        // Final safety check on restore path
        if (!isValidImagePath($local_path)) {
            return null;
        }
        
        $restored = restoreImageFromNextcloud($pdo, $nc_settings, $nextcloud_path, $local_path);
        if ($restored) {
            // Update the local path in database
            $pdo->prepare("UPDATE drills SET custom_image = ? WHERE id = ?")->execute([$local_path, $drill_id]);
            return $local_path;
        }
    } catch (Exception $e) {
        error_log("Failed to restore drill image from Nextcloud for drill $drill_id: " . $e->getMessage());
    }
    
    return null;
}
