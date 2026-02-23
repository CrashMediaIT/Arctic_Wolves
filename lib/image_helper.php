<?php
/**
 * Image Helper - Handles persistent image resolution
 * 
 * When images are stored both locally and in Nextcloud, this helper
 * ensures images are available locally by restoring from Nextcloud
 * when the local file is missing (e.g., after a directory wipe).
 */

require_once __DIR__ . '/../cloud_config.php';

/**
 * Resolve a profile image path, restoring from Nextcloud if needed.
 * Call this before displaying a profile image to ensure the local file exists.
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id The user ID
 * @param string $local_path The local file path from the database
 * @return string|null The resolved local path, or null if unavailable
 */
function resolveProfileImage($pdo, $user_id, $local_path) {
    // If local file exists, nothing to do
    if (!empty($local_path) && file_exists($local_path)) {
        return $local_path;
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
            $local_path = "uploads/avatar_" . $user_id . "_restored." . $ext;
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
 * Resolve an evaluation media file path, restoring from Nextcloud if needed.
 * 
 * @param PDO $pdo Database connection
 * @param int $media_id The evaluation_media record ID
 * @param string $local_path The local file path from the database
 * @return string|null The resolved local path, or null if unavailable
 */
function resolveEvaluationMedia($pdo, $media_id, $local_path) {
    // If local file exists, nothing to do
    if (!empty($local_path) && file_exists($local_path)) {
        return $local_path;
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
            $local_path = "uploads/evaluations/restored_" . $media_id . "." . $ext;
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
