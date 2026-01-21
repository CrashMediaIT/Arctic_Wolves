<?php
/**
 * CSRF Token Generator and Validator
 * Provides CSRF protection for all forms
 */

class CSRFProtection {
    
    /**
     * Generate CSRF token and store in session
     */
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Get HTML input field with CSRF token
     */
    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Validate CSRF token from POST request
     */
    public static function validateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        if (!isset($_POST['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    
    /**
     * Validate or die with error message
     */
    public static function validate() {
        if (!self::validateToken()) {
            http_response_code(403);
            die(json_encode([
                'success' => false,
                'error' => 'Invalid CSRF token. Please refresh and try again.'
            ]));
        }
    }
}
