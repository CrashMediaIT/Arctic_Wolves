<?php
/**
 * File Upload Validation Library
 * Centralized, secure file upload validation for Crash Hockey
 */

class FileUploadValidator {
    
    /**
     * Validates an uploaded file
     * 
     * @param array $file The $_FILES array element
     * @param array $allowedExtensions Array of allowed extensions (e.g., ['jpg', 'png', 'pdf'])
     * @param int $maxSizeBytes Maximum file size in bytes
     * @param array $allowedMimeTypes Array of allowed MIME types
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validate($file, $allowedExtensions, $maxSizeBytes, $allowedMimeTypes = null) {
        // Check if file was uploaded
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['valid' => false, 'error' => 'Invalid file upload'];
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => self::getUploadErrorMessage($file['error'])];
        }
        
        // Validate file size
        if ($file['size'] > $maxSizeBytes) {
            $maxSizeMB = round($maxSizeBytes / (1024 * 1024), 2);
            return ['valid' => false, 'error' => "File size exceeds maximum of {$maxSizeMB}MB"];
        }
        
        // Validate file size minimum (protect against 0-byte files)
        if ($file['size'] === 0) {
            return ['valid' => false, 'error' => 'File is empty'];
        }
        
        // Validate extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            $allowed = implode(', ', $allowedExtensions);
            return ['valid' => false, 'error' => "File type not allowed. Allowed types: {$allowed}"];
        }
        
        // Validate MIME type if provided
        if ($allowedMimeTypes !== null) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedMimeTypes)) {
                return ['valid' => false, 'error' => 'File MIME type not allowed'];
            }
        }
        
        // Additional security: Check if file is actually uploaded
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'Security validation failed'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validates image upload
     */
    public static function validateImage($file, $maxSizeMB = 5) {
        return self::validate(
            $file,
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            $maxSizeMB * 1024 * 1024,
            ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
        );
    }
    
    /**
     * Validates video upload
     */
    public static function validateVideo($file, $maxSizeMB = 100) {
        return self::validate(
            $file,
            ['mp4', 'mov', 'avi', 'webm'],
            $maxSizeMB * 1024 * 1024,
            ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm']
        );
    }
    
    /**
     * Validates document upload
     */
    public static function validateDocument($file, $maxSizeMB = 10) {
        return self::validate(
            $file,
            ['pdf', 'doc', 'docx', 'txt'],
            $maxSizeMB * 1024 * 1024,
            ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain']
        );
    }
    
    /**
     * Validates backup/zip upload
     */
    public static function validateArchive($file, $maxSizeMB = 500) {
        return self::validate(
            $file,
            ['zip', 'sql', 'gz'],
            $maxSizeMB * 1024 * 1024,
            ['application/zip', 'application/x-zip-compressed', 'application/sql', 'application/x-sql', 'text/plain', 'application/gzip']
        );
    }
    
    /**
     * Sanitizes filename to prevent directory traversal
     */
    public static function sanitizeFilename($filename) {
        // Remove any path components
        $filename = basename($filename);
        
        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 255) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 250);
            $filename = $name . '.' . $ext;
        }
        
        return $filename;
    }
    
    /**
     * Generates a unique filename
     */
    public static function generateUniqueFilename($originalFilename) {
        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $timestamp = date('YmdHis');
        $random = bin2hex(random_bytes(8));
        return "{$timestamp}_{$random}.{$ext}";
    }
    
    /**
     * Gets human-readable upload error message
     */
    private static function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'File is too large';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload was stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
}
