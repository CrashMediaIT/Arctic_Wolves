<?php
/**
 * Image Helper - Validates image paths/URLs
 * 
 * All images are stored in RustFS S3 and served via URLs.
 * No local file storage or restoration.
 */

require_once __DIR__ . '/../cloud_config.php';

/**
 * Validate that an image path/URL is safe
 */
function isValidImagePath($path) {
    if (empty($path)) return false;
    if (preg_match('#^https?://#', $path)) return true;
    return false;
}

/**
 * No-op — kept for backward compatibility of function signature.
 */
function tryRestoreFromPersistent($local_path, $subfolder, $filename = null, $pdo = null) {
    return false;
}

/**
 * Return the profile image path if it's a valid URL.
 */
function resolveProfileImage($pdo, $user_id, $path) {
    if (empty($path)) return null;
    if (preg_match('#^https?://#', $path)) return $path;
    return null;
}

/**
 * Return the evaluation media path if it's a valid URL.
 */
function resolveEvaluationMedia($pdo, $media_id, $path) {
    if (empty($path)) return null;
    if (preg_match('#^https?://#', $path)) return $path;
    return null;
}

/**
 * Return the drill image path if it's a valid URL.
 */
function resolveDrillImage($pdo, $drill_id, $path) {
    if (empty($path)) return null;
    if (preg_match('#^https?://#', $path)) return $path;
    return null;
}
