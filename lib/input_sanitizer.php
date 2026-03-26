<?php
/**
 * Input Sanitization Library
 * Centralized input sanitization and validation for security
 */

class InputSanitizer {
    
    /**
     * Sanitize text input
     */
    public static function sanitizeText($input, $strip_tags = true) {
        if ($input === null) {
            return null;
        }
        
        $sanitized = trim($input);
        
        if ($strip_tags) {
            $sanitized = strip_tags($sanitized);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize email address
     */
    public static function sanitizeEmail($email) {
        if ($email === null) {
            return null;
        }
        
        $email = trim($email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        // Validate
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        
        return strtolower($email);
    }
    
    /**
     * Sanitize integer input
     */
    public static function sanitizeInt($input) {
        if ($input === null || $input === '') {
            return null;
        }
        
        return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }
    
    /**
     * Sanitize float input
     */
    public static function sanitizeFloat($input) {
        if ($input === null || $input === '') {
            return null;
        }
        
        return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
    
    /**
     * Sanitize URL
     */
    public static function sanitizeURL($url) {
        if ($url === null) {
            return null;
        }
        
        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        // Validate
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        
        return $url;
    }
    
    /**
     * Sanitize HTML (allows specific tags).
     * Strips disallowed tags via strip_tags(), then removes dangerous
     * attributes (event handlers, javascript: URIs) from the remaining
     * tags to prevent XSS through allowed elements like <a> or <p>.
     */
    public static function sanitizeHTML($html, $allowed_tags = '<p><br><strong><em><ul><ol><li><a>') {
        if ($html === null) {
            return null;
        }
        
        $html = strip_tags($html, $allowed_tags);
        
        // Remove event handler attributes (on*) and javascript: URIs from allowed tags
        // This prevents attacks like <p onclick="alert(1)"> or <a href="javascript:alert(1)">
        $html = preg_replace('/\s+on[a-z]+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\s+on[a-z]+\s*=\s*\S+/i', '', $html);
        $html = preg_replace('/href\s*=\s*["\']?\s*javascript\s*:/i', 'href="', $html);
        $html = preg_replace('/src\s*=\s*["\']?\s*javascript\s*:/i', 'src="', $html);
        
        return $html;
    }
    
    /**
     * Sanitize filename
     */
    public static function sanitizeFilename($filename) {
        if ($filename === null) {
            return null;
        }
        
        // Remove path components
        $filename = basename($filename);
        
        // Remove special characters, keep alphanumeric, dash, underscore, dot
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Prevent double extensions and other tricks
        $filename = preg_replace('/\.+/', '.', $filename);
        
        return $filename;
    }
    
    /**
     * Sanitize phone number
     */
    public static function sanitizePhone($phone) {
        if ($phone === null) {
            return null;
        }
        
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        return $phone;
    }
    
    /**
     * Sanitize date (expects YYYY-MM-DD)
     */
    public static function sanitizeDate($date) {
        if ($date === null || $date === '') {
            return null;
        }
        
        // Validate date format
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            return null;
        }
        
        return $date;
    }
    
    /**
     * Sanitize array of values
     */
    public static function sanitizeArray($array, $type = 'text') {
        if (!is_array($array)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($array as $key => $value) {
            $sanitized_key = self::sanitizeText($key);
            
            switch ($type) {
                case 'int':
                    $sanitized[$sanitized_key] = self::sanitizeInt($value);
                    break;
                case 'float':
                    $sanitized[$sanitized_key] = self::sanitizeFloat($value);
                    break;
                case 'email':
                    $sanitized[$sanitized_key] = self::sanitizeEmail($value);
                    break;
                default:
                    $sanitized[$sanitized_key] = self::sanitizeText($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize $_POST data
     */
    public static function sanitizePOST($fields = null) {
        $sanitized = [];
        
        $data = $fields !== null ? array_intersect_key($_POST, array_flip($fields)) : $_POST;
        
        foreach ($data as $key => $value) {
            $sanitized[$key] = is_array($value) 
                ? self::sanitizeArray($value) 
                : self::sanitizeText($value);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize $_GET data
     */
    public static function sanitizeGET($fields = null) {
        $sanitized = [];
        
        $data = $fields !== null ? array_intersect_key($_GET, array_flip($fields)) : $_GET;
        
        foreach ($data as $key => $value) {
            $sanitized[$key] = is_array($value) 
                ? self::sanitizeArray($value) 
                : self::sanitizeText($value);
        }
        
        return $sanitized;
    }
}
?>
