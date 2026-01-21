<?php
/**
 * File Upload Validator
 * Validates file uploads for security
 */

class FileUploadValidator {
    
    // Allowed MIME types
    private static $allowedTypes = [
        'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'video' => ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'],
        'document' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'receipt' => ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']
    ];
    
    // Maximum file sizes (in bytes)
    private static $maxSizes = [
        'image' => 100 * 1024 * 1024,      // 100MB
        'video' => 10 * 1024 * 1024 * 1024, // 10GB
        'document' => 500 * 1024 * 1024,   // 500MB
        'receipt' => 50 * 1024 * 1024      // 50MB
    ];
    
    /**
     * Validate uploaded file
     */
    public static function validate($file, $type = 'image') {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['error']) || is_array($file['error'])) {
            $errors[] = 'Invalid file upload';
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check for upload errors
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'No file was uploaded';
                return ['valid' => false, 'errors' => $errors];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'File exceeds maximum size';
                return ['valid' => false, 'errors' => $errors];
            default:
                $errors[] = 'Unknown upload error';
                return ['valid' => false, 'errors' => $errors];
        }
        
        // Check file size
        if (!isset(self::$maxSizes[$type])) {
            $errors[] = 'Invalid file type category';
            return ['valid' => false, 'errors' => $errors];
        }
        
        if ($file['size'] > self::$maxSizes[$type]) {
            $maxMB = self::$maxSizes[$type] / 1024 / 1024;
            $errors[] = "File exceeds maximum size of {$maxMB}MB";
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, self::$allowedTypes[$type])) {
            $errors[] = 'Invalid file type. Allowed types: ' . implode(', ', self::$allowedTypes[$type]);
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Additional security checks
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'pht', 'phar', 'exe', 'sh', 'bat'];
        
        if (in_array($fileExtension, $dangerousExtensions)) {
            $errors[] = 'Dangerous file extension detected';
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check for embedded PHP code in images
        if (in_array($mimeType, self::$allowedTypes['image'])) {
            $content = file_get_contents($file['tmp_name']);
            if (preg_match('/<\?php/i', $content)) {
                $errors[] = 'Malicious content detected in file';
                return ['valid' => false, 'errors' => $errors];
            }
        }
        
        return ['valid' => true, 'errors' => []];
    }
    
    /**
     * Generate safe filename
     */
    public static function generateSafeFilename($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $safeName = bin2hex(random_bytes(16)) . '_' . time();
        return $safeName . '.' . $extension;
    }
    
    /**
     * Get allowed types for a category
     */
    public static function getAllowedTypes($type) {
        return self::$allowedTypes[$type] ?? [];
    }
    
    /**
     * Get max size for a category (in MB)
     */
    public static function getMaxSize($type) {
        $bytes = self::$maxSizes[$type] ?? 0;
        return $bytes / 1024 / 1024;
    }
}
